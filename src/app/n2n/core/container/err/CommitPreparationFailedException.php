<?php

namespace n2n\core\container\err;

class CommitPreparationFailedException extends TransactionPhaseException {


	function __construct(string $message = null, int $code = null, ?\Throwable $previous = null,
			private bool $deadlock = false) {
		parent::__construct($message ?? '', $code ?? 0, $previous);
	}

	function markAsDeadlock(): void {
		$this->deadlock = true;
	}

	function isDeadlock(): bool {
		return $this->deadlock;
	}

	/**
	 * @throws CommitPreparationFailedException
	 */
	static function try(\Closure $closure): mixed {
		try {
			return $closure();
		} catch (CommitPreparationFailedException $e) {
			throw $e;
		} catch (\Throwable $t) {
			throw new CommitPreparationFailedException($t->getMessage(), previous: $t);
		}
	}

}