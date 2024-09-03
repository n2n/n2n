<?php

namespace n2n\core\cache\impl;

use n2n\cache\CacheStorePool;
use n2n\core\VarStore;
use n2n\cache\impl\CacheStorePools;
use n2n\core\N2N;

class N2nCacheStorePools {

	const APP_CACHE_DIR = 'appcache';

	static function varStore(VarStore $varStore, bool $shared): CacheStorePool {
		return CacheStorePools::file(
				$varStore->requestDirFsPath(VarStore::CATEGORY_TMP, N2N::NS,
						self::APP_CACHE_DIR, shared: $shared),
				$varStore->getDirPerm(), $varStore->getFilePerm());
	}
}
