<?php

namespace n2n\core\container\err;

class BeginFailedException extends TransactionPhaseException {
	/**
	 * @throws BeginFailedException
	 */
	static function try(\Closure $closure): mixed {
		try {
			return $closure();
		} catch (BeginFailedException $e) {
			throw $e;
		} catch (\Throwable $t) {
			throw new BeginFailedException($t->getMessage(), previous: $t);
		}
	}
}