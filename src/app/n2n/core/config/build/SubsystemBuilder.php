<?php

namespace n2n\core\config\build;

use n2n\web\http\SubsystemMatcher;

class SubsystemBuilder {
	/**
	 * @var Subsystem[]
	 */
	private array $subsystems = [];

	function __construct() {
	}

	function addSchema(string $matcherName, ?string $subsystemName, ?string $hostName, ?string $contextPath, array $n2nLocales) {
		$key = $subsystemName ?? $matcherName;

		if (!isset($this->subsystems[$key])) {
			$this->subsystems[$key] = new Subsystem($key, $subsystemName);
		}

		$subsystem = $this->subsystems[$key];
		$subsystem->addMatcher(new SubsystemMatcher($matcherName, $hostName, $contextPath, $n2nLocales));
	}

	/**
	 * @return Subsystem[]
	 */
	function getSubsystems() {
		return $this->subsystems;
	}
}