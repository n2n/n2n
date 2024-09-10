<?php

namespace n2n\core\err;

class DispatchedException extends \RuntimeException {
	private bool $alreadyRendered = false;
	private array $throwables = [];

	function __construct(\Throwable ...$throwables) {
		$this->throwables = $throwables;
		parent::__construct(previous: $throwables[0] ?? null);
	}

	function setAlreadyRendered(bool $alreadyRendered): void {
		$this->alreadyRendered = $alreadyRendered;
	}

	function isAlreadyRendered(): bool {
		return $this->alreadyRendered;
	}

	/**
	 * @return \Throwable[]
	 */
	public final function getThrowables(): array {
		return $this->throwables;
	}

	function addThrowable(\Throwable $throwable): void {
		$this->throwables[] = $throwable;
	}

	static function try(\Closure $closure, \Throwable ...$ts): void {
		try {
			$closure->__invoke();
		} catch (\Throwable $e) {
			throw new DispatchedException($e, ...$ts);
		}
	}

}