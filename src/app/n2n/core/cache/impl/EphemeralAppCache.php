<?php

namespace n2n\core\cache\impl;

use n2n\core\cache\AppCache;
use n2n\cache\CacheStore;
use n2n\cache\impl\ephemeral\EphemeralCacheStore;

class EphemeralAppCache implements AppCache {

	private array $cacheStores = [];

	public function lookupCacheStore(string $namespace, bool $shared = true): CacheStore {
		return $this->cacheStores[$namespace] ?? $this->cacheStores[$namespace] = new EphemeralCacheStore();
	}

	public function clear(): void {
		$this->cacheStores = [];
	}
}