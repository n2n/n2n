<?php

namespace n2n\core\container\impl;

class PhpVars {

	function __construct(readonly array &$server, readonly array &$get, readonly array &$post,
			readonly array &$files, readonly array &$session) {
	}

	static function fromEnv(): PhpVars {
		return new PhpVars($_SERVER, $_GET, $_POST, $_FILES, $_SESSION);
	}

}