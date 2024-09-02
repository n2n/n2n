<?php

namespace n2n\core\cache\impl;

use n2n\cache\CacheStorePool;

class AppCaches {

	static function combined(CacheStorePool $cacheStorePool, CacheStorePool $sharedCacheStorePool): CombinedAppCache {
		return new CombinedAppCache($cacheStorePool, $sharedCacheStorePool);
	}
}