<?php

namespace n2n\core\config\build;

use n2n\util\attr\DataSet;
use n2n\config\InvalidConfigurationException;

class EnvAttributeInjector {
	public function __construct(private array $attrs, private string $configSourceName) {

	}

	public function injectEnv(): DataSet {
		$attrs = $this->attrs;
		array_walk_recursive($attrs, function (&$value) {
			if (is_string($value)) {
				$value = $this->replaceEnvOrUseFallback($value);
			}
		});

		return new DataSet($attrs);
	}


	private function replaceEnvOrUseFallback(string $value): string {
		return preg_replace_callback(
				'/\{env:([^|}]+)(?:\|([^}]*))?}/',
				function(array $matches): string {
					$name = $matches[1];
					$fallback = $matches[2] ?? null;

					$env = getenv($name);

					if ($env !== false) {
						return $env;
					}

					if ($fallback !== null) {
						return $fallback;
					}

					throw new InvalidConfigurationException('Environment variable "' . $name
							. '" is not defined. ConfigSource: ' . $this->configSourceName);
				},
				$value);
	}
}