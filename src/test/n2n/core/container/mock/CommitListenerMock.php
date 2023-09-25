<?php

namespace n2n\core\container\mock;

use n2n\core\container\TransactionalResource;
use n2n\core\container\Transaction;
use n2n\core\container\TransactionManager;
use n2n\core\container\CommitListener;
use n2n\core\container\err\TransactionPhaseException;

class CommitListenerMock implements CommitListener {

	public array $callMethods = [];
	public array $callTransactions = [];

	public \Closure|null $prePrepareOnce = null;
	public \Closure|null $postPrepareOnce = null;
	public \Closure|null $preCommitOnce = null;
	public \Closure|null $postCommitOnce = null;
	public \Closure|null $preRollbackOnce = null;
	public \Closure|null $postRollbackOnce = null;
	public \Closure|null $postCloseOnce = null;
	public \Closure|null $postCorruptedStateOnce = null;


	function prePrepare(Transaction $transaction): void {
		$this->callMethods[] = 'prePrepare';
		$this->callTransactions[] = $transaction;

		if ($this->prePrepareOnce === null) {
			return;
		}

		$c = $this->prePrepareOnce;
		$this->prePrepareOnce = null;
		$c();
	}

	function postPrepare(Transaction $transaction): void {
		$this->callMethods[] = 'postPrepare';
		$this->callTransactions[] = $transaction;

		if ($this->postPrepareOnce === null) {
			return;
		}

		$c = $this->postPrepareOnce;
		$this->postPrepareOnce = null;
		$c();
	}

	public function preCommit(Transaction $transaction): void {
		$this->callMethods[] = 'preCommit';
		$this->callTransactions[] = $transaction;

		if ($this->preCommitOnce === null) {
			return;
		}

		$c = $this->preCommitOnce;
		$this->preCommitOnce = null;
		$c();
	}

	public function postCommit(Transaction $transaction): void {
		$this->callMethods[] = 'postCommit';
		$this->callTransactions[] = $transaction;

		if ($this->postCommitOnce === null) {
			return;
		}

		$c = $this->postCommitOnce;
		$this->postCommitOnce = null;
		$c();
	}

	public function preRollback(Transaction $transaction): void {
		$this->callMethods[] = 'beginTransaction';
		$this->callTransactions[] = $transaction;

		if ($this->preRollbackOnce === null) {
			return;
		}

		$c = $this->preRollbackOnce;
		$this->preRollbackOnce = null;
		$c();
	}

	public function postRollback(Transaction $transaction): void {
		$this->callMethods[] = 'beginTransaction';
		$this->callTransactions[] = $transaction;

		if ($this->postRollbackOnce === null) {
			return;
		}

		$c = $this->postRollbackOnce;
		$this->postRollbackOnce = null;
		$c();
	}

	public function postClose(Transaction $transaction): void {
		$this->callMethods[] = 'postClose';
		$this->callTransactions[] = $transaction;

		if ($this->postCloseOnce === null) {
			return;
		}

		$c = $this->postCloseOnce;
		$this->postCloseOnce = null;
		$c();
	}

	public function postCorruptedState(?Transaction $transaction, TransactionPhaseException $e): void {
		$this->callMethods[] = 'postCorruptedState';
		$this->callTransactions[] = $transaction;

		if ($this->postCorruptedStateOnce === null) {
			return;
		}

		$c = $this->postCorruptedStateOnce;
		$this->postCorruptedStateOnce = null;
		$c();
	}
}