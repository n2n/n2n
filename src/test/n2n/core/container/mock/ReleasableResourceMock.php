<?php

namespace n2n\core\container\mock;

use n2n\core\container\TransactionalResource;
use n2n\core\container\Transaction;
use n2n\core\container\TransactionManager;
use n2n\core\container\ReleasableResource;

class ReleasableResourceMock implements ReleasableResource {

	public array $callMethods = [];
	public array $callTransactions = [];


	function release(): void {
		$this->callMethods[] = 'release';
		$this->callTransactions[] = null;
	}
}