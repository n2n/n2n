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
use n2n\util\ex\IllegalStateException;
use n2n\util\EnumUtils;

class TransactionManager extends ObjectAdapter {
	/**
	 * @var TransactionalResource[]
	 */
	private $transactionalResources = array();
	private $commitListeners = array();
	private $tRef = 1;
	
	private ?Transaction $rootTransaction = null;
	private int $currentLevel = 0;
	private ?bool $readOnly = null;
	private bool $rollingBack = false;
	private array $transactions = array();

	private $phase = TransactionPhase::CLOSED;

	private bool $commitPreparationExtended = false;
	private int $commitPreparationsNum = 0;

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

		throw new TransactionStateException('No active transaction.');
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
			
		try {
			if ($this->rollingBack) {
				$this->rollBack();
				return;
			}

			$this->prepareCommit();
			$this->commit();
		} catch (CommitPreparationFailedException $e) {
			$this->rollBack();
			throw new UnexpectedRollbackException(
					'Failure in transaction commit phase caused an unexpected rollback.', 0, $e);
		} catch (CommitFailedException $e) {
			throw new IllegalStateException('Transaction commit failed. State could be corrupted!', 0, $e);
		} finally {
			$this->reset();
		}
	}
	
	private function reset() {
		$this->rootTransaction = null;
		$this->rollingBack = false;
		$this->readOnly = null;
		$this->phase = TransactionPhase::CLOSED;
		$this->commitPreparationExtended = false;
		$this->commitPreparationsNum = 0;
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

	/**
	 * @throws CommitPreparationFailedException
	 */
	private function prepareCommit(): void {
		$this->tRef++;
		$this->phase = TransactionPhase::PREPARE_COMMIT;

		do {
			$this->commitPreparationExtended = false;
			$this->commitPreparationsNum++;

			foreach ($this->transactionalResources as $resource) {
				$resource->prepareCommit($this->rootTransaction);
				if ($this->commitPreparationExtended) {
					break;
				}
			}
		} while ($this->commitPreparationExtended);
	}

	private function commit() {
		$this->tRef++;
		$this->phase = TransactionPhase::COMMIT;

		$transaction = $this->rootTransaction;
		
		foreach ($this->commitListeners as $commitListener) {
			$commitListener->preCommit($transaction);
		}
		
		try {
			foreach ($this->transactionalResources as $resource) {
				$resource->commit($transaction);
			}

			$this->reset();
		} catch (CommitFailedException $e) {
			$this->reset();

			$tsm = array();
			foreach ($this->commitListeners as $commitListener) {
				try {
					$commitListener->commitFailed($transaction, $e);
				} catch (\Throwable $t) {
					$tsm[] = get_class($t) . ': ' . $t->getMessage();
				}
			}
			
			if (empty($tsm)) {
				throw $e;
			}
			
			throw new CommitFailedException('Commit failed with CommitListener exceptions: ' . implode(', ', $tsm), 
					0, $e);
		}

		
		foreach ($this->commitListeners as $commitListener) {
			$commitListener->postCommit($transaction);
		}
	}

	private function rollBack() {
		$this->tRef++;
		$this->phase = TransactionPhase::ROLLBACK;

		foreach ($this->transactionalResources as $listener) {
			$listener->rollBack($this->rootTransaction);
		}
	}

	function releaseResources(): void {
		$this->ensureNoTransactionOpen();

		foreach ($this->transactionalResources as $resource) {
			$resource->release();
		}
	}

	public function registerResource(TransactionalResource $resource) {
		if (!in_array($this->phase, [TransactionPhase::CLOSED, TransactionPhase::OPEN])) {
			throw new TransactionStateException('Can not register a new TransactionalResource in '
					. EnumUtils::unitToBacked($this->phase) . ' phase.');
		}

		$this->transactionalResources[spl_object_hash($resource)] = $resource;
		
		if ($this->hasOpenTransaction()) {
			$resource->beginTransaction($this->rootTransaction);
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

