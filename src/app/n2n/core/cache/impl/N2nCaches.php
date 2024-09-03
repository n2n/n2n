<?php

namespace n2n\core\cache\impl;

use n2n\core\cache\StartupCacheSupplier;
use n2n\core\cache\AppCacheSupplier;

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

	static function combined(StartupCacheSupplier $startupCacheSupplier,
			\Closure $localAppCacheStorePoolClosure, \Closure $sharedAppCacheStorePoolClosure): CombinedN2nCache {
		return new CombinedN2nCache($startupCacheSupplier, $localAppCacheStorePoolClosure, $sharedAppCacheStorePoolClosure);
	}
}