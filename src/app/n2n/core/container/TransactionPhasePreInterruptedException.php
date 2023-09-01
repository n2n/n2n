<?php

namespace n2n\core\container;

class TransactionPhasePreInterruptedException extends \Exception {
	/**
	 * @throws TransactionPhasePreInterruptedException
	 */
	static function try(string $callbackName, \Closure $closure): mixed {
		try {
			return $closure();
		} catch (\Throwable $t) {
			throw new TransactionPhasePreInterruptedException($pre,
					'Callback ' . $callbackName . ' caused error: ' . $t->getMessage(),
					previous: $t);
		}
	}
}