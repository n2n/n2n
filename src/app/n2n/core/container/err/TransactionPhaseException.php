<?php

namespace n2n\core\container\err;

class TransactionPhaseException extends \Exception {

	function isDeadlock(): bool {
		return false;
	}
}