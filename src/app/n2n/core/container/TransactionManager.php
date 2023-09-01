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

class TransactionManager extends ObjectAdapter {
	/**
	 * @var TransactionalResource[]
	 */
	private $transactionalResources = array();
	/**
	 * @var CommitListener[]
	 */
	private $commitListeners = array();
	private $tRef = 1;
	
	private ?Transaction $rootTransaction = null;
	private int $currentLevel = 0;
	private ?bool $readOnly = null;
	private bool $rollingBack = false;
	private array $transactions = array();

	private TransactionPhase $phase = TransactionPhase::CLOSED;

	private bool $commitPreparationExtended = false;
	private int $commitPreparationsNum = 0;
	private ?array $pendingCommitPreparations = null;

	public function createTransaction($readOnly = false): Transaction {
		$this->currentLevel++;

		if (!in_array($this->phase, [TransactionPhase::CLOSED, TransactionPhase::OPEN])) {
			throw new TransactionStateException('Can not create transaction in '
					. EnumUtils::unitToBacked($this->phase) . ' phase.');
		}

		$transaction = new Transaction($this, $this->currentLevel, $this->tRef, $readOnly);
		if ($this->currentLevel == 1) {
			$this->readOnly = $readOnly;
			$this->begin($transaction);
		} else if ($this->readOnly && !$readOnly) {
			throw new TransactionStateException(
					'Cannot create non readonly transaction in readonly transaction.');
		}
		
		return $this->transactions[$this->currentLevel] = $transaction;
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
	 * @return bool true or false if a transaction is open, otherwise null.
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
	 * @return \n2n\core\container\Transaction
	 * @throws TransactionStateException if no transaction is open.
	 */
	public function getRootTransaction() {
		$this->ensureTransactionOpen();

		return $this->rootTransaction;
	}
	
	/**
	 * @return \n2n\core\container\Transaction
	 * @throws TransactionStateException if no transaction is open.
	 */
	public function getCurrentTransaction() { 
		if (false !== ($transaction = end($this->transactions))) {
			return $transaction;
		}
		
		if ($this->rootTransaction !== null) {
			return $this->rootTransaction;
		}

		throw new TransactionStateException('No active transaction.');
	}

	public function closeLevel($level, $tRef, bool $commit) {
		if ($this->tRef != $tRef || $level > $this->currentLevel) {
			throw new TransactionStateException('Transaction is already closed.');
		}
		
		if (!$commit) {
			$this->rollingBack = true;
		} else if ($this->rollingBack === true) {
			throw new TransactionStateException(
					'Transaction cannot be committed because sub transaction was rolled back');
		}

		foreach (array_keys($this->transactions) as $tlevel) {
			if ($level > $tlevel) continue;
			
			unset($this->transactions[$tlevel]);
			$this->currentLevel = $level - 1;
		}
		
		if (!empty($this->transactions)) return;

		if ($this->rollingBack) {
			$this->endByRollingBack();
			return;
		}

		$this->endByCommit();
	}

	private function endByRollingBack(): void {
		try {
			$this->rollBack();
		} catch (RollbackFailedException $e) {
			$this->failWithEnterCorruptedState('Transaction rollback failed.', $e);
		} catch (TransactionPhasePreInterruptedException $e) {
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
			$this->commit();
		} catch (CommitRequestFailedException $e) {
			$this->failWithUnexpectedRollBackAndClose($e);
		} catch (CommitFailedException $e) {
			$this->failWithEnterCorruptedState('Transaction commit failed.', $e);
		} catch (TransactionPhasePreInterruptedException $e) {
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

	private function failWithUnexpectedRollBackAndClose(CommitRequestFailedException $previous): void {
		try {
			$this->rollBack();
			$this->close();

			throw new UnexpectedRollbackException(
					'Failure in transaction commit request phase caused an unexpected rollback.', 0, $previous);
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
		$this->phase = TransactionPhase::OPEN;

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
	private function enterCorruptedState(TransactionPhaseException $e) {
		$this->phase = TransactionPhase::CORRUPTED_STATE;

		$transaction = $this->rootTransaction;
		foreach ($this->commitListeners as $commitListener) {
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
		$this->readOnly = null;
		$this->phase = TransactionPhase::CLOSED;
		$this->cleanUp();

		foreach ($this->commitListeners as $commitListener) {
			TransactionPhasePostInterruptedException::try('postClose',
					fn () => $commitListener->postClose($transaction));
		}
	}

	private function reopen(): void {
		IllegalStateException::assertTrue($this->phase === TransactionPhase::PREPARE_COMMIT
				|| $this->phase === TransactionPhase::COMMIT
				|| $this->phase === TransactionPhase::ROLLBACK);

		$this->phase = TransactionPhase::OPEN;

		$this->cleanUp();
	}

	private function cleanUpPrepare(): void {
		$this->commitPreparationExtended = false;
		$this->commitPreparationsNum = 0;
		$this->pendingCommitPreparations = null;
	}

	private function cleanUp(): void {
		$this->rollingBack = false;
		$this->cleanUpPrepare();
	}
	
	private function begin(Transaction $transaction) {
		$this->rootTransaction = $transaction;
		$this->phase = TransactionPhase::OPEN;

		foreach ($this->transactionalResources as $resource) {
			$resource->beginTransaction($transaction);
		}
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

		$this->tRef++;
		$this->phase = TransactionPhase::PREPARE_COMMIT;

		$transaction = $this->rootTransaction;
		foreach ($this->commitListeners as $commitListener) {
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
			foreach ($this->commitListeners as $commitListener) {
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

		$this->tRef++;
		$this->phase = TransactionPhase::COMMIT;

		$transaction = $this->rootTransaction;

		foreach ($this->commitListeners as $commitListener) {
			TransactionPhasePreInterruptedException::try('preCommit',
					fn () => $commitListener->preCommit($transaction));
		}

		foreach ($this->transactionalResources as $resource) {
			CommitRequestFailedException::try(fn () => $resource->requestCommit($transaction));
		}

		foreach ($this->transactionalResources as $resource) {
			CommitFailedException::try(fn () => $resource->commit($transaction));
		}
		
		foreach ($this->commitListeners as $commitListener) {
			TransactionPhasePostInterruptedException::try('postCommit', fn () => $commitListener->postCommit($transaction));
		}
	}

	/**
	 * @throws RollbackFailedException
	 * @throws TransactionPhasePostInterruptedException|TransactionPhasePreInterruptedException
	 */
	private function rollBack(): void {
		$this->tRef++;
		$this->phase = TransactionPhase::ROLLBACK;

		$transaction = $this->rootTransaction;

		foreach ($this->commitListeners as $commitListener) {
			TransactionPhasePreInterruptedException::try('preRollback',
					fn () => $commitListener->preRollback($transaction));
		}

		foreach ($this->transactionalResources as $listener) {
			RollbackFailedException::try(fn () => $listener->rollBack($this->rootTransaction));
		}

		foreach ($this->commitListeners as $commitListener) {
			TransactionPhasePostInterruptedException::try('postRollback',
					fn () => $commitListener->postRollback($transaction));
		}
	}

	function releaseResources(): void {
		$this->ensureNoTransactionOpen();

		foreach ($this->transactionalResources as $resource) {
			$resource->release();
		}
	}

	/**
	 * @return TransactionalResource[]
	 */
	function getResources(): array {
		return $this->transactionalResources;
	}

	public function registerResource(TransactionalResource $resource) {
		if (!in_array($this->phase, [TransactionPhase::CLOSED, TransactionPhase::OPEN, TransactionPhase::PREPARE_COMMIT])) {
			throw new TransactionStateException('Can not register a new TransactionalResource in '
					. EnumUtils::unitToBacked($this->phase) . ' phase.');
		}

		$this->transactionalResources[spl_object_hash($resource)] = $resource;
		
		if ($this->hasOpenTransaction()) {
			$resource->beginTransaction($this->rootTransaction);
		}

		if ($this->phase === TransactionPhase::PREPARE_COMMIT) {
			$this->pendingCommitPreparations[] = $resource;
		}
	}

	public function unregisterResource(TransactionalResource $resource) {
		unset($this->transactionalResources[spl_object_hash($resource)]);
	}
	
	public function registerCommitListener(CommitListener $commitListener) {
		$this->commitListeners[spl_object_hash($commitListener)] = $commitListener;
	}
	
	public function unregisterCommitListener(CommitListener $commitListener) {
		unset($this->commitListeners[spl_object_hash($commitListener)]);
	}
}

