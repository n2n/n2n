<?php

namespace n2n\core\cache\impl;

use n2n\core\cache\AppCache;
use n2n\cache\CacheStore;

class CombinedAppCache implements AppCache {

	function __construct(private readonly AppCache $appCache, private readonly AppCache $sharedAppCache) {
	}

	public function lookupCacheStore(string $namespace, bool $shared = true): CacheStore {
		if ($shared) {
			return $this->sharedAppCache->lookupCacheStore($namespace, $shared);
		}
		return $this->appCache->lookupCacheStore($namespace, $shared);
	}

	public function clear(): void {
		$this->appCache->clear();
		if ($this->appCache !== $this->sharedAppCache) {
			$this->sharedAppCache->clear();
		}
	}
}