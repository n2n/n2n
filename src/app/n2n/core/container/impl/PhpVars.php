<?php

namespace n2n\core\container\impl;

class PhpVars {

	function __construct(public array &$server, public array &$get, public array &$post, public array &$files) {
	}

	static function fromEnv(): PhpVars {
		return new PhpVars($_SERVER, $_GET, $_POST, $_FILES);
	}

}