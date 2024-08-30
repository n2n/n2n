<?php

namespace n2n\core\cache;

use n2n\core\VarStore;
use n2n\cache\CacheStore;
use n2n\core\config\AppConfig;

interface StartupCacheSupplier {

	/**
	 * @param VarStore $varStore
	 */
	public function varStoreInitialized(VarStore $varStore): void;

	/**
	 * @return null|CacheStore
	 */
	public function getStartupCacheStore(): ?CacheStore;

	/**
	 * @param AppConfig $appConfig
	 */
	public function appConfigInitialized(AppConfig $appConfig): void;
}