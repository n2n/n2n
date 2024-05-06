<?php
/*
 * Copyright (c) 2012-2016, Hofmänner New Media.
 * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS FILE HEADER.
 *
 * This file is part of the N2N FRAMEWORK.
 *
 * The N2N FRAMEWORK is free software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * N2N is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
 * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details: http://www.gnu.org/licenses/
 *
 * The following people participated in this project:
 *
 * Andreas von Burg.....: Architect, Lead Developer
 * Bert Hofmänner.......: Idea, Frontend UI, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\core\container;

use n2n\reflection\ObjectAdapter;
use n2n\util\EnumUtils;
use n2n\util\ex\IllegalStateException;
use n2n\core\container\err\CommitFailedException;
use n2n\core\container\err\CommitRequestFailedException;
use n2n\core\container\err\RollbackFailedException;
use n2n\core\container\err\TransactionStateException;
use n2n\core\container\err\UnexpectedRollbackException;
use n2n\core\container\err\TransactionPhaseException;
use n2n\core\container\err\BeginFailedException;

class TransactionManager extends ObjectAdapter {
	/**
	 * @var TransactionalResource[]
	 */
	private array $transactionalResources = array();
	/**
	 * @var ReleasableResource[]
	 */
	private array $releasableResources = array();

	private ?array $begunTransactionalResources = null;
	/**
	 * @var CommitListener[]
	 */
	private $commitListeners = array();
	private $tRef = 1;

	private ?Transaction $rootTransaction = null;
	private int $currentLevel = 0;
	private ?bool $readOnly = null;
	private bool $rollingBack = false;
	private array $subTransactions = array();

	private TransactionPhase $phase = TransactionPhase::CLOSED;

	private bool $commitPreparationExtended = false;
	private int $commitPreparationsNum = 0;
	private ?array $pendingCommitPreparations = null;

	public function createTransaction($readOnly = false): Transaction {
		if (!in_array($this->phase, [TransactionPhase::CLOSED, TransactionPhase::OPEN])) {
			throw new TransactionStateException('Can not create transaction in '
					. EnumUtils::unitToBacked($this->phase) . ' phase.');
		}

		$this->currentLevel++;

		$transaction = new Transaction($this, $this->currentLevel, $this->tRef, $readOnly);

		if ($this->currentLevel > 1) {
			if ($this->readOnly && !$readOnly) {
				throw new TransactionStateException(
						'Cannot create non readonly transaction in readonly transaction.');
			}

			return $this->subTransactions[$this->currentLevel] = $transaction;
		}

		$this->readOnly = $readOnly;
		$this->rootTransaction = $transaction;
		try {
			$this->begin($transaction);
		} catch (BeginFailedException $e) {
			$this->failWithUnexpectedRollBackAndClose($e);
		} finally {
			$this->cleanUpBegin();
		}
		return $transaction;
	}


	/**
	 * Returns true if there is an open transaction
	 * @return bool
	 */
	public function hasOpenTransaction(): bool {
		return $this->rootTransaction !== null;
	}

	function getPhase(): TransactionPhase {
		return $this->phase;
	}



	/**
	 * Returns true if there is an open read only transaction.
	 * @return bool|null true or false if a transaction is open, otherwise null.
	 */
	public function isReadyOnly(): ?bool {
		return $this->readOnly;
	}

	function ensureTransactionOpen(): void {
		if ($this->rootTransaction !== null) {
			return;
		}

		throw new TransactionStateException('No active transaction.');
	}

	private function ensureNoTransactionOpen(): void {
		if ($this->rootTransaction === null) {
			return;
		}

		throw new TransactionStateException('Transaction open.');
	}

	/**
	 * @return Transaction
	 * @throws TransactionStateException if no transaction is open.
	 */
	public function getRootTransaction(): Transaction {
		$this->ensureTransactionOpen();

		return $this->rootTransaction;
	}

	/**
	 * @return Transaction
	 * @throws TransactionStateException if no transaction is open.
	 */
	public function getCurrentTransaction(): ?Transaction {
		if (false !== ($transaction = end($this->subTransactions))) {
			return $transaction;
		}

		if ($this->rootTransaction !== null) {
			return $this->rootTransaction;
		}

		throw new TransactionStateException('No active transaction.');
	}

	public function isLevelOpen(int $level, int $tRef): bool {
		return !($this->tRef != $tRef || $level > $this->currentLevel);
	}

	public function closeLevel(int $level, int $tRef, bool $commit): void {
		if ($this->tRef != $tRef || $level > $this->currentLevel) {
			throw new TransactionStateException('Transaction is already closed.');
		}

		if (!$commit) {
			$this->rollingBack = true;
		} else if ($this->rollingBack === true) {
			throw new TransactionStateException(
					'Transaction cannot be committed because sub transaction was rolled back');
		}

		foreach (array_keys($this->subTransactions) as $tlevel) {
			if ($level > $tlevel) {
				continue;
			}

			unset($this->subTransactions[$tlevel]);
			$this->currentLevel = $level - 1;
		}

		if (!empty($this->subTransactions) || $level !== 1) {
			return;
		}

		if ($this->rollingBack) {
			$this->endByRollingBack();
			return;
		}

		$this->endByCommit();
	}

	private function endByRollingBack(): void {
		try {
			$this->tRef++;
			$this->rollBack();
		} catch (RollbackFailedException $e) {
			$this->failWithEnterCorruptedState('Transaction rollback failed.', $e);
		} catch (TransactionPhasePreInterruptedException $e) {
			$this->tRef--;
			$this->failWithReopen('Transaction rollback interrupted.', $e);
		} catch (TransactionPhasePostInterruptedException $e) {
			$this->failWithClose('Transaction rollback interrupted.', $e);
		}

		try {
			$this->close();
		} catch (TransactionPhasePostInterruptedException $e) {
			$this->fail('Transaction close interrupted.', $e);
		}
	}

	private function endByCommit(): void {
		try {
			$this->prepareCommit();
		} finally {
			$this->cleanUpPrepare();
		}

		try {
			$this->tRef++;
			$this->commit();
		} catch (CommitRequestFailedException $e) {
			$this->failWithUnexpectedRollBackAndClose($e);
		} catch (CommitFailedException $e) {
			$this->failWithEnterCorruptedState('Transaction commit failed.', $e);
		} catch (TransactionPhasePreInterruptedException $e) {
			$this->tRef--;
			$this->failWithReopen('Transaction commit interrupted.', $e);
		} catch (TransactionPhasePostInterruptedException $e) {
			$this->failWithClose('Transaction commit interrupted.', $e);
		}

		try {
			$this->close();
		} catch (TransactionPhasePostInterruptedException $e) {
			$this->fail('Transaction close interrupted.', $e);
		}
	}

	private function failWithUnexpectedRollBackAndClose(TransactionPhaseException $previous): void {
		try {
			$this->rollBack();
			$this->close();

			throw new UnexpectedRollbackException(
					'Failure in transaction phase caused unexpected rollback.', 0, $previous);
		} catch (RollbackFailedException|TransactionPhasePreInterruptedException|TransactionPhasePostInterruptedException $e) {
			$this->failWithEnterCorruptedState('Unexpected rollback threw an exception "'
					. get_class($previous) . ': ' . $previous->getMessage()
					. '" failed. State could be corrupted!', $e);
		}
	}

	private function failWithClose(string $reason, TransactionPhasePostInterruptedException $previous): void {
		try {
			$this->close();

			$this->fail('Unexpected transaction close: ' . $reason, $previous);
		} catch (RollbackFailedException|TransactionPhasePreInterruptedException|TransactionPhasePostInterruptedException $e) {
			$this->failWithEnterCorruptedState('Unexpected rollback threw an exception "'
					. get_class($previous) . ': ' . $previous->getMessage()
					. '" failed. State could be corrupted!', $e);
		}
	}


	private function fail(string $message, \Throwable $previous): void {
		throw new TransactionStateException($message, previous: $previous);
	}

	private function failWithReopen(string $reason, \Throwable $previous): void {
		$this->reopen();

		throw new TransactionStateException('Transaction unexpectedly reopened. Reason: ' . $reason,
				previous: $previous);
	}

	private function failWithEnterCorruptedState(string $reason, TransactionPhaseException $previous): void {
		try {
			$this->enterCorruptedState($previous);
		} catch (TransactionPhasePostInterruptedException $e) {
			$this->fail('Transaction entered corrupted state (Reason: ' . $e->getMessage() . ') and was 
					then interrupted by a callback.', $e);
		}

		$this->fail('TransactionManager state could be corrupted. Reason: ' . $reason,
				previous: $previous);
	}

	/**
	 * @throws TransactionPhasePostInterruptedException
	 */
	private function enterCorruptedState(TransactionPhaseException $e): void {
		$this->phase = TransactionPhase::CORRUPTED_STATE;

		$transaction = $this->rootTransaction;
		while (null !== ($commitListener = $this->walkCommitListeners())) {
			TransactionPhasePostInterruptedException::try('postCorruptedState',
					fn () => $commitListener->postCorruptedState($transaction, $e));
		}
	}

	/**
	 * @throws TransactionPhasePostInterruptedException
	 */
	private function close(): void {
		$transaction = $this->rootTransaction;

		$this->rootTransaction = null;
		$this->rollingBack = false;
		$this->currentLevel = 0;
		$this->readOnly = null;
		$this->phase = TransactionPhase::CLOSED;

		while (null !== ($commitListener = $this->walkCommitListeners())) {
			TransactionPhasePostInterruptedException::try('postClose',
					fn () => $commitListener->postClose($transaction));
		}
	}

	private function reopen(): void {
		IllegalStateException::assertTrue($this->phase === TransactionPhase::PREPARE_COMMIT
				|| $this->phase === TransactionPhase::COMMIT
				|| $this->phase === TransactionPhase::ROLLBACK);

		$this->phase = TransactionPhase::OPEN;
	}

	private function cleanUpPrepare(): void {
		$this->commitPreparationExtended = false;
		$this->commitPreparationsNum = 0;
		$this->pendingCommitPreparations = null;
	}

	/**
	 * @throws BeginFailedException
	 */
	private function begin(Transaction $transaction): void {
		$this->phase = TransactionPhase::OPEN;

		$this->begunTransactionalResources = [];
		foreach ($this->transactionalResources as $resource) {
			BeginFailedException::try(fn () => $resource->beginTransaction($transaction));
			$this->begunTransactionalResources[] = $resource;
		}
	}

	private function cleanUpBegin(): void {
		$this->begunTransactionalResources = null;
	}

	function isCommitPreparationExtended(): bool {
		return $this->commitPreparationExtended;
	}

	/**
	 * Starts the commit preparation phase for all transactional resources all over again.
	 *
	 * @return void
	 */
	function extendCommitPreparation(): void {
		if ($this->phase === TransactionPhase::PREPARE_COMMIT) {
			$this->commitPreparationExtended = true;
			return;
		}

		throw new TransactionStateException('Can not extend commit preparation in phase '
				. EnumUtils::unitToBacked($this->phase));
	}

	function getCommitPreparationsNum(): int {
		return $this->commitPreparationsNum;
	}

	private function prepareCommit(): void {
		IllegalStateException::assertTrue($this->phase === TransactionPhase::OPEN);

		$this->phase = TransactionPhase::PREPARE_COMMIT;

		$transaction = $this->rootTransaction;
		while (null !== ($commitListener = $this->walkCommitListeners())) {
			$commitListener->prePrepare($transaction);
		}

		do {
			$this->pendingCommitPreparations = $this->transactionalResources;
			$this->commitPreparationExtended = false;
			$this->commitPreparationsNum++;

			while (null !== ($resource = array_shift($this->pendingCommitPreparations))) {
				$resource->prepareCommit($this->rootTransaction);
				if ($this->commitPreparationExtended) {
					break;
				}
			}

			$transaction = $this->rootTransaction;
			while (null !== ($commitListener = $this->walkCommitListeners())) {
				$commitListener->postPrepare($transaction);
			}
		} while ($this->commitPreparationExtended);
	}

	/**
	 * @throws CommitFailedException
	 * @throws CommitRequestFailedException
	 * @throws TransactionPhasePreInterruptedException
	 * @throws TransactionPhasePostInterruptedException
	 */
	private function commit(): void {
		IllegalStateException::assertTrue($this->phase === TransactionPhase::PREPARE_COMMIT);

		$this->phase = TransactionPhase::COMMIT;

		$transaction = $this->rootTransaction;

		while (null !== ($commitListener = $this->walkCommitListeners())) {
			TransactionPhasePreInterruptedException::try('preCommit',
					fn () => $commitListener->preCommit($transaction));
		}

		foreach ($this->transactionalResources as $resource) {
			CommitRequestFailedException::try(fn () => $resource->requestCommit($transaction));
		}

		foreach ($this->transactionalResources as $resource) {
			CommitFailedException::try(fn () => $resource->commit($transaction));
		}

		while (null !== ($commitListener = $this->walkCommitListeners())) {
			TransactionPhasePostInterruptedException::try('postCommit', fn () => $commitListener->postCommit($transaction));
		}
	}

	/**
	 * @throws RollbackFailedException
	 * @throws TransactionPhasePostInterruptedException|TransactionPhasePreInterruptedException
	 */
	private function rollBack(): void {
		$this->phase = TransactionPhase::ROLLBACK;

		$transaction = $this->rootTransaction;

		while (null !== ($commitListener = $this->walkCommitListeners())) {
			TransactionPhasePreInterruptedException::try('preRollback',
					fn () => $commitListener->preRollback($transaction));
		}

		foreach ($this->begunTransactionalResources ?? $this->transactionalResources as $listener) {
			RollbackFailedException::try(fn () => $listener->rollBack($this->rootTransaction));
		}

		while (null !== ($commitListener = $this->walkCommitListeners())) {
			TransactionPhasePostInterruptedException::try('postRollback',
					fn () => $commitListener->postRollback($transaction));
		}
	}

	function releaseResources(): void {
		$this->ensureNoTransactionOpen();

		foreach ($this->releasableResources as $resource) {
			$resource->release();
		}
	}

	/**
	 * @return TransactionalResource[]
	 */
	function getResources(): array {
		return $this->transactionalResources;
	}

	public function registerResource(TransactionalResource|ReleasableResource $resource): void {
		if (!in_array($this->phase, [TransactionPhase::CLOSED, TransactionPhase::OPEN, TransactionPhase::PREPARE_COMMIT])) {
			throw new TransactionStateException('Can not register a new TransactionalResource in '
					. EnumUtils::unitToBacked($this->phase) . ' phase.');
		}

		$objHash = spl_object_hash($resource);
		$this->releasableResources[$objHash] = $resource;

		if (!($resource instanceof TransactionalResource)) {
			return;
		}

		$this->transactionalResources[$objHash] = $resource;


		if ($this->hasOpenTransaction()) {
			$resource->beginTransaction($this->rootTransaction);
		}

		if ($this->phase === TransactionPhase::PREPARE_COMMIT) {
			$this->pendingCommitPreparations[] = $resource;
		}
	}

	public function unregisterResource(TransactionalResource $resource): void {
		$objHash = spl_object_hash($resource);
		unset($this->transactionalResources[$objHash]);
		unset($this->releasableResources[$objHash]);
	}

	public function registerCommitListener(CommitListener $commitListener, bool $prioritize = false): void {
		if ($prioritize) {
			$this->commitListeners = [spl_object_hash($commitListener) => $commitListener] + $this->commitListeners;
		} else {
			$this->commitListeners[spl_object_hash($commitListener)] = $commitListener;
		}

		$this->walkingCommitListenersIterator?->append($commitListener);
	}

	public function unregisterCommitListener(CommitListener $commitListener): void {
		unset($this->commitListeners[spl_object_hash($commitListener)]);
	}

	private ?\ArrayIterator $walkingCommitListenersIterator = null;

	private function walkCommitListeners(): ?CommitListener {
		if ($this->walkingCommitListenersIterator === null) {
			$this->walkingCommitListenersIterator = (new \ArrayObject($this->commitListeners))->getIterator();
		}

		if (!$this->walkingCommitListenersIterator->valid()) {
			$this->walkingCommitListenersIterator = null;
			return null;
		}

		$commitListener = $this->walkingCommitListenersIterator->current();
		$this->walkingCommitListenersIterator->next();
		return $commitListener;
	}
}
