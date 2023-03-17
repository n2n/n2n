<?php

namespace n2n\core\cache\impl;

class N2nCaches {

	static function file(): FileN2nCache {
		return new FileN2nCache();
	}

	static function ephemeral(): EphemeralN2nCache {
		return new EphemeralN2nCache();
	}

	static function null(): NullN2nCache {
		return new NullN2nCache();
	}
}