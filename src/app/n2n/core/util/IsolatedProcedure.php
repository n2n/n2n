<?php

namespace n2n\core\util;

use n2n\core\container\TransactionManager;
use n2n\util\ex\IllegalStateException;
use n2n\reflection\magic\MagicMethodInvoker;
use n2n\util\type\ArgUtils;
use n2n\core\container\err\TransactionalProcessFailedException;
use n2n\core\container\TransactionExecutionException;

class IsolatedProcedure {

	private int $tries = 3;

	function __construct(private readonly TransactionManager $tm,
			private readonly MagicMethodInvoker $transactionalProcessInvoker,
			private readonly ?MagicMethodInvoker $deadlockHandler = null) {

	}

	function getTries(): int {
		return $this->tries;
	}

	function setTries(int $tries): static {
		ArgUtils::assertTrue($tries >= 1, 'Tries must be larger than 1. Given : ' . $tries);
		$this->tries = $tries;
		return $this;
	}

	function exec(bool $readOnly = false): mixed {
		if ($this->tm->hasOpenTransaction()) {
			throw new IllegalStateException('IsolatedProcedure must not be executed inside an already open '
					. ' transaction so it can create and possible recreate a root transaction.');
		}

		for ($i = 1; $i <= $this->tries; $i++) {
			$tx = $this->tm->createTransaction($readOnly);
			try {
				/**
				 * @return mixed
				 * @throws TransactionExecutionException
				 */
				$r = (fn () => $this->transactionalProcessInvoker->invoke())();
				$tx->commit();

				return $r;
			} catch (TransactionExecutionException $e) {
				if (!$e->isDeadlock()) {
					throw new TransactionalProcessFailedException(
							'IsolatedProcedure threw an exception: ' . $e->getMessage(), previous: $e);
				}

				$tx->rollBack();
				IllegalStateException::assertTrue(!$this->tm->hasOpenTransaction());
			}
		}

		if ($this->deadlockHandler === null) {
			throw new TransactionalProcessFailedException('IsolatedProcedure was aborted after '
					. $this->tries . ' attempts which all ended in deadlocks.', previous: $e);
		}

		return $this->deadlockHandler->invoke();
	}
}
