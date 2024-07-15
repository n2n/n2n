<?php

namespace n2n\core\container;

interface TransactionExecutionException extends \Throwable {
	function isDeadlock(): bool;
}