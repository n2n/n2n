<?php

namespace n2n\core\container\err;

class TransactionPhasePreInterruptedException extends TransactionPhaseException {
	/**
	 * @throws TransactionPhasePreInterruptedException
	 */
	static function try(string $callbackName, \Closure $closure): mixed {
		try {
			return $closure();
		} catch (\Throwable $t) {
			throw new TransactionPhasePreInterruptedException(
					'Callback ' . $callbackName . ' caused error: ' . $t->getMessage(),
					previous: $t);
		}
	}
}