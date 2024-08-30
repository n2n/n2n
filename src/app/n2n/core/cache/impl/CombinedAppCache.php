<?php

namespace n2n\core\cache\impl;

use n2n\core\cache\AppCache;
use n2n\cache\CacheStore;
use n2n\cache\CacheStorePool;

class CombinedAppCache implements AppCache {

	function __construct(private readonly CacheStorePool $localCacheStorePool,
			private readonly CacheStorePool $sharedCacheStorePool) {
	}

	public function lookupCacheStore(string $namespace, bool $shared = true): CacheStore {
		if ($shared) {
			return $this->sharedCacheStorePool->lookupCacheStore($namespace);
		}
		return $this->localCacheStorePool->lookupCacheStore($namespace);
	}

	public function clear(): void {
		$this->localCacheStorePool->clear();
		if ($this->localCacheStorePool !== $this->sharedCacheStorePool) {
			$this->sharedCacheStorePool->clear();
		}
	}
}