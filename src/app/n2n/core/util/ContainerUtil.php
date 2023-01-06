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
use n2n\util\ex\IllegalStateException;
use n2n\reflection\magic\MagicMethodInvoker;

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
	function createTransaction(bool $readOnly = false) {
		return $this->getTransactionManager()->createTransaction($readOnly);
	}

	/**
	 * @param \Closure $closure
	 * @return MagicMethodInvoker
	 */
	private function createMmiFromClosure(\Closure $closure) {
		$mmi = new MagicMethodInvoker($this->n2nContext);
		$mmi->setMethod(new \ReflectionFunction($closure));
		return $mmi;
	}

	/**
	 * @param \Closure $callback
	 * @return void
	 */
	function outsideTransaction(\Closure $callback, bool $invokeOnFaliedCommit = false) {
		if (!$this->hasOpenTransaction()) {
			$this->createMmiFromClosure($callback)->invoke();
			return;
		}

		$this->postCommit($callback);

		if ($invokeOnFaliedCommit) {
			$this->failedCommit($callback);
		}
	}

	/**
	 * @return ClosureCommitListener
	 */
	private function createClosureCommitListener() {
		$tm = $this->getTransactionManager();
		$tm->ensureTransactionOpen();

		$commitListener = new ClosureCommitListener();
		$commitListener->setFinallyCallback(function () use ($tm, $commitListener) {
			$tm->unregisterCommitListener($commitListener);
		});

		$tm->registerCommitListener($commitListener);

		return $commitListener;
	}

	function preCommit(\Closure $callback) {
		$mmi = $this->createMmiFromClosure($callback);
		$commitListener = $this->createClosureCommitListener();
		$commitListener->setPreCommitCallback(function () use ($commitListener, $mmi) {
			$this->getTransactionManager()->unregisterCommitListener($commitListener);
			$mmi->invoke();
		});
	}

	function postCommit(\Closure $callback) {
		$mmi = $this->createMmiFromClosure($callback);
		$commitListener = $this->createClosureCommitListener();
		$commitListener->setPostCommitCallback(function () use ($commitListener, $mmi) {
			$this->getTransactionManager()->unregisterCommitListener($commitListener);
			$mmi->invoke();
		});
	}

	function failedCommit(\Closure $callback) {
		$mmi = $this->createMmiFromClosure($callback);
		$commitListener = $this->createClosureCommitListener();
		$commitListener->setCommitFailedCallback(function () use ($commitListener, $mmi) {
			$this->getTransactionManager()->unregisterCommitListener($commitListener);
			$mmi->invoke();
		});
	}
}