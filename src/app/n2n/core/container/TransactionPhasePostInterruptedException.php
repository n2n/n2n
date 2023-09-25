<?php

namespace n2n\core\container;

class TransactionPhasePostInterruptedException extends \Exception {
	/**
	 * @throws TransactionPhasePostInterruptedException
	 */
	static function try(string $callbackName, \Closure $closure): mixed {
		try {
			return $closure();
		} catch (\Throwable $t) {
			throw new TransactionPhasePostInterruptedException(
					'Callback ' . $callbackName . ' caused error: ' . $t->getMessage(),
					previous: $t);
		}
	}
}