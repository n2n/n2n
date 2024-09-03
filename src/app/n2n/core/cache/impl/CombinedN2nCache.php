<?php

namespace n2n\core\cache\impl;

use n2n\core\cache\N2NCache;
use n2n\core\config\AppConfig;
use n2n\core\VarStore;
use n2n\cache\CacheStore;
use n2n\core\container\impl\AppN2nContext;
use n2n\core\cache\StartupCacheSupplier;
use n2n\core\cache\AppCacheSupplier;
use n2n\util\magic\impl\MagicMethodInvoker;
use n2n\core\cache\AppCache;
use n2n\util\type\TypeConstraints;
use n2n\cache\CacheStorePool;

class CombinedN2nCache implements N2NCache {

	function __construct(private readonly StartupCacheSupplier $startupCacheSupplier,
			private readonly \Closure $localAppCacheStorePoolClosure,
			private readonly \Closure $sharedAppCacheStorePoolClosure) {
	}

	public function varStoreInitialized(VarStore $varStore): void {
		$this->startupCacheSupplier->varStoreInitialized($varStore);
	}

	public function getStartupCacheStore(): ?CacheStore {
		return $this->startupCacheSupplier->getStartupCacheStore();
	}

	public function appConfigInitialized(AppConfig $appConfig): void {
		$this->startupCacheSupplier->appConfigInitialized($appConfig);
	}

	function getLocalAppCacheStorePool(AppN2nContext $n2nContext): CacheStorePool {
		$mmi = new MagicMethodInvoker($n2nContext);
		$mmi->setClosure($this->localAppCacheStorePoolClosure);
		$mmi->setReturnTypeConstraint(TypeConstraints::type(CacheStorePool::class));
		return $mmi->invoke();
	}

	function getSharedAppCacheStorePool(AppN2nContext $n2nContext): CacheStorePool {
		$mmi = new MagicMethodInvoker($n2nContext);
		$mmi->setClosure($this->sharedAppCacheStorePoolClosure);
		$mmi->setReturnTypeConstraint(TypeConstraints::type(CacheStorePool::class));
		return $mmi->invoke();
	}

}
