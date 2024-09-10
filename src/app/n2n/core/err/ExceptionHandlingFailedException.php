<?php

namespace n2n\core\err;

class ExceptionHandlingFailedException extends \RuntimeException {
	private array $throwables = [];

	function __construct(private \Throwable $exceptionHandlingException, \Throwable ...$throwables) {
		$this->throwables = $throwables;
		parent::__construct(previous: $exceptionHandlingException);
	}

	public function getExceptionHandlingException(): \Throwable {
		return $this->exceptionHandlingException;
	}

	/**
	 * @return \Throwable[]
	 */
	public final function getThrowables(): array {
		return $this->throwables;
	}

	static function try(\Closure $closure, \Throwable ...$ts): void {
		try {
			$closure->__invoke();
		} catch (\Throwable $e) {
			throw new ExceptionHandlingFailedException($e, ...$ts);
		}
	}

}