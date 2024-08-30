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

class CombinedN2nCache implements N2NCache {


	function __construct(private StartupCacheSupplier $startupCacheSupplier,
			private AppCacheSupplier|\Closure $appCacheSupplierOrClosure) {

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


	function applyToN2nContext(AppN2nContext $n2nContext): void {
		if ($this->appCacheSupplierOrClosure instanceof AppCacheSupplier) {
			$this->appCacheSupplierOrClosure->applyToN2nContext($n2nContext);
			return;
		}

		$mmi = new MagicMethodInvoker($n2nContext);
		$mmi->setClosure($this->appCacheSupplierOrClosure);
		$mmi->setReturnTypeConstraint(TypeConstraints::type(AppCache::class));
		$n2nContext->setAppCache($mmi->invoke());
	}

}