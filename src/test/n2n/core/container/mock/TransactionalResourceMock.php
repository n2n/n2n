<?php

namespace n2n\core\container\mock;

use n2n\core\container\TransactionalResource;
use n2n\core\container\Transaction;
use n2n\core\container\TransactionManager;

class TransactionalResourceMock implements TransactionalResource {

	public array $callMethods = [];
	public array $callTransactions = [];

	public \Closure|null $prepareOnce = null;
	public \Closure|null $commitOnce = null;

	public function beginTransaction(Transaction $transaction): void {
		$this->callMethods[] = 'beginTransaction';
		$this->callTransactions[] = $transaction;
	}

	public function prepareCommit(Transaction $transaction): void {
		$this->callMethods[] = 'prepareCommit';
		$this->callTransactions[] = $transaction;

		if ($this->prepareOnce === null) {
			return;
		}

		$c = $this->prepareOnce;
		$this->prepareOnce = null;
		$c();
	}

	public function commit(Transaction $transaction): void {
		$this->callMethods[] = 'commit';
		$this->callTransactions[] = $transaction;

		if ($this->commitOnce === null) {
			return;
		}

		$c = $this->commitOnce;
		$this->commitOnce = null;
		$c();
	}

	public function rollBack(Transaction $transaction): void {
		$this->callMethods[] = 'rollBack';
		$this->callTransactions[] = $transaction;
	}

	function release(): void {
		$this->callMethods[] = 'release';
		$this->callTransactions[] = null;
	}
}