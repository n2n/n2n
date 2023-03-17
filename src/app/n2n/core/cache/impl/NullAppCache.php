<?php

namespace n2n\core\cache\impl;

use n2n\core\cache\AppCache;
use n2n\util\cache\CacheStore;
use n2n\util\cache\impl\EphemeralCacheStore;

class NullAppCache implements AppCache {


	public function lookupCacheStore(string $namespace): CacheStore {
		return new EphemeralCacheStore();
	}

	public function clear() {
	}
}