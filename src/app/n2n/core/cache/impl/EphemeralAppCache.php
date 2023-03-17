<?php

namespace n2n\core\cache\impl;

use n2n\core\cache\AppCache;
use n2n\util\cache\CacheStore;
use n2n\util\cache\impl\EphemeralCacheStore;

class EphemeralAppCache implements AppCache {

	private array $cacheStores = [];

	public function lookupCacheStore(string $namespace): CacheStore {
		return $this->cacheStores[$namespace] ?? $this->cacheStores[$namespace] = new EphemeralCacheStore();
	}

	public function clear() {
		$this->cacheStores = [];
	}
}