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
namespace n2n\core\util;

use n2n\core\container\N2nContext;
use n2n\core\container\TransactionManager;
use n2n\reflection\magic\MagicMethodInvoker;
use n2n\core\container\err\TransactionStateException;
use n2n\core\container\TransactionPhase;

class ContainerUtil {
	function __construct(private N2nContext $n2nContext) {

	}

	private function getTransactionManager(): TransactionManager {
		return $this->n2nContext->getTransactionManager();
	}

	function hasOpenTransaction(): bool {
		return $this->getTransactionManager()->hasOpenTransaction();
	}

	/**
	 * @param bool $readOnly
	 * @return \n2n\core\container\Transaction
	 */
	function createTransaction(bool $readOnly = false): \n2n\core\container\Transaction {
		return $this->getTransactionManager()->createTransaction($readOnly);
	}

	/**
	 * @param \Closure $closure
	 * @return MagicMethodInvoker
	 */
	private function createMmiFromClosure(\Closure $closure): MagicMethodInvoker {
		$mmi = new MagicMethodInvoker($this->n2nContext);
		$mmi->setMethod(new \ReflectionFunction($closure));
		return $mmi;
	}

	/**
	 * @param \Closure $callback
	 * @return void
	 */
	function outsideTransaction(\Closure $callback, bool $invokeOnCorruptedState = false): void {
		if (!$this->hasOpenTransaction()) {
			$this->createMmiFromClosure($callback)->invoke();
			return;
		}

		$this->postClose($callback);

		if ($invokeOnCorruptedState) {
			$this->postCorruptedState($callback);
		}
	}

	/**
	 * @return ClosureCommitListener
	 */
	private function createClosureCommitListener(array $disallowedPhases = [], bool $prioritize = false): ClosureCommitListener {
		$tm = $this->getTransactionManager();
		$tm->ensureTransactionOpen();

		if (in_array($tm->getPhase(), $disallowedPhases)) {
			throw new TransactionStateException('Transaction is in ' . $tm->getPhase()->name . ' phase.');
		}

		$commitListener = new ClosureCommitListener();
		$commitListener->setFinallyCallback(function () use ($tm, $commitListener) {
			$tm->unregisterCommitListener($commitListener);
		});

		$tm->registerCommitListener($commitListener, $prioritize);

		return $commitListener;
	}

	function preCommit(\Closure $callback): void {
		$mmi = $this->createMmiFromClosure($callback);
		$commitListener = $this->createClosureCommitListener();
		$commitListener->setPreCommitCallback(function () use ($commitListener, $mmi) {
			$this->getTransactionManager()->unregisterCommitListener($commitListener);
			$mmi->invoke();
		});
	}

	function postCommit(\Closure $callback): void {
		$mmi = $this->createMmiFromClosure($callback);
		$commitListener = $this->createClosureCommitListener();
		$commitListener->setPostCommitCallback(function () use ($commitListener, $mmi) {
			$this->getTransactionManager()->unregisterCommitListener($commitListener);
			$mmi->invoke();
		});
	}

	function preRollback(\Closure $callback): void {
		$mmi = $this->createMmiFromClosure($callback);
		$rollbackListener = $this->createClosureCommitListener();
		$rollbackListener->setPreRollbackCallback(function () use ($rollbackListener, $mmi) {
			$this->getTransactionManager()->unregisterCommitListener($rollbackListener);
			$mmi->invoke();
		});
	}

	function postRollback(\Closure $callback): void {
		$mmi = $this->createMmiFromClosure($callback);
		$rollbackListener = $this->createClosureCommitListener();
		$rollbackListener->setPostRollbackCallback(function () use ($rollbackListener, $mmi) {
			$this->getTransactionManager()->unregisterCommitListener($rollbackListener);
			$mmi->invoke();
		});
	}

	function prePrepare(\Closure $callback): void {
		$mmi = $this->createMmiFromClosure($callback);
		$prepareListener = $this->createClosureCommitListener([TransactionPhase::PREPARE_COMMIT,
				TransactionPhase::COMMIT, TransactionPhase::ROLLBACK]);

		$prepareListener->setPrePrepareCallback(function () use ($prepareListener, $mmi) {
			$this->getTransactionManager()->unregisterCommitListener($prepareListener);
			$mmi->invoke();
		});
	}

	function prePrepareOrExtend(\Closure $closure): void {
		$tm = $this->getTransactionManager();
		if ($tm->getPhase()->isCompleting()) {
			$tm->extendCommitPreparation();
			$this->createMmiFromClosure($closure)->invoke();
			return;
		}

		$this->prePrepare($closure);
	}

	/**
	 * Callback of this kind will be called after all postPrepareAndExtend() callbacks.
	 *
	 * @param \Closure $callback
	 * @param bool $extend
	 * @return void
	 */
	function postPrepare(\Closure $callback, bool $extend = false): void {
		$mmi = $this->createMmiFromClosure($callback);
		$prepareListener = $this->createClosureCommitListener([TransactionPhase::COMMIT, TransactionPhase::ROLLBACK], $extend);

		$prepareListener->setPostPrepareCallback(function () use ($prepareListener, $mmi, $extend) {
			$tm = $this->getTransactionManager();
			if (!$extend && $tm->isCommitPreparationExtended()) {
				return;
			}

			$tm->unregisterCommitListener($prepareListener);
			if ($extend) {
				$tm->extendCommitPreparation();
			}
			$mmi->invoke();
		});
	}

	function postPrepareAndExtend(\Closure $callback): void {
		$this->postPrepare($callback, true);
	}

	function postCorruptedState(\Closure $callback): void {
		$mmi = $this->createMmiFromClosure($callback);
		$commitListener = $this->createClosureCommitListener();
		$commitListener->setPostCorruptedStateCallback(function () use ($commitListener, $mmi) {
			$this->getTransactionManager()->unregisterCommitListener($commitListener);
			$mmi->invoke();
		});
	}

	function postClose(\Closure $callback): void {
		$mmi = $this->createMmiFromClosure($callback);
		$commitListener = $this->createClosureCommitListener();
		$commitListener->setPostCloseCallback(function() use ($commitListener, $mmi) {
			$this->getTransactionManager()->unregisterCommitListener($commitListener);
			$mmi->invoke();
		});
	}

	/**
	 * Will call the closure inside a transaction. If a deadlock happens the closure will be called again in a
	 * new transaction.
	 *
	 * @param \Closure $closure
	 * @param int $tries
	 * @param \Closure|null $deadlockHandler
	 * @param bool $readOnly
	 * @return mixed
	 */
	function execIsolated(\Closure $closure, int $tries = 3, ?\Closure $deadlockHandler = null,
			bool $readOnly = false): mixed {
		$deadlockMmi = null;
		if ($deadlockHandler !== null) {
			$deadlockMmi = $this->createMmiFromClosure($deadlockHandler);
		}

		$restartableTransaction = new IsolatedProcedure($this->getTransactionManager(),
				$this->createMmiFromClosure($closure), $deadlockMmi);
		$restartableTransaction->setTries($tries);
		return $restartableTransaction->exec($readOnly);
	}
}
