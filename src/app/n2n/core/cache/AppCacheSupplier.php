<?php

namespace n2n\core\cache;

use n2n\core\container\impl\AppN2nContext;
use n2n\cache\CacheStorePool;

interface AppCacheSupplier {

	function getLocalAppCacheStorePool(AppN2nContext $n2nContext): CacheStorePool;

	function getSharedAppCacheStorePool(AppN2nContext $n2nContext): CacheStorePool;
}
