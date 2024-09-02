<?php

namespace n2n\core\cache\impl;

use n2n\cache\CacheStorePool;
use n2n\core\VarStore;
use n2n\cache\impl\CacheStorePools;
use n2n\core\N2N;

class AppCaches {

	const APP_CACHE_DIR = 'appcache';

	static function combined(CacheStorePool $cacheStorePool, CacheStorePool $sharedCacheStorePool): CombinedAppCache {
		return new CombinedAppCache($cacheStorePool, $sharedCacheStorePool);
	}

	static function varStoreWithSharedPool(VarStore $varStore,  CacheStorePool $sharedCacheStorePool): CombinedAppCache {
		return new CombinedAppCache(
				CacheStorePools::file(
						$varStore->requestDirFsPath(VarStore::CATEGORY_TMP, N2N::NS, self::APP_CACHE_DIR),
						$varStore->getDirPerm(), $varStore->getFilePerm()),
				$sharedCacheStorePool);
	}

	static function varStore(VarStore $varStore): CombinedAppCache {
		return new CombinedAppCache(
				CacheStorePools::file(
						$varStore->requestDirFsPath(VarStore::CATEGORY_TMP, N2N::NS, self::APP_CACHE_DIR),
						$varStore->getDirPerm(), $varStore->getFilePerm()),
				CacheStorePools::file(
						$varStore->requestDirFsPath(VarStore::CATEGORY_TMP, N2N::NS, self::APP_CACHE_DIR, shared: true),
						$varStore->getDirPerm(), $varStore->getFilePerm()));
	}
}
