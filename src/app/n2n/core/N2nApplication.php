<?php

namespace n2n\core;

use n2n\core\module\ModuleManager;
use n2n\util\io\fs\FsPath;
use n2n\core\config\AppConfig;
use n2n\core\ext\N2nExtension;
use n2n\core\container\TransactionManager;
use n2n\core\container\impl\AppN2nContext;
use n2n\core\container\impl\PhpVars;
use n2n\context\config\SimpleLookupSession;
use n2n\context\LookupManager;
use n2n\core\cache\AppCache;
use n2n\util\magic\MagicContext;
use n2n\core\cache\AppCacheSupplier;

class N2nApplication {
	private array $n2nExtensions = [];

	function __construct(private VarStore $varStore, private ModuleManager $moduleManager,
			private AppCacheSupplier $appCacheSupplier, private AppConfig $appConfig, private ?FsPath $publicFsPath) {
	}

	function getVarStore(): VarStore {
		return $this->varStore;
	}

	function getModuleManager(): ModuleManager {
		return $this->moduleManager;
	}

	function getAppConfig(): AppConfig {
		return $this->appConfig;
	}

	function getPublicFsPath(): ?FsPath {
		return $this->publicFsPath;
	}

	function registerN2nExtension(N2nExtension $n2nExtension): void {
		$this->n2nExtensions[spl_object_hash($n2nExtension)] = $n2nExtension;
	}

	/**
	 * @template T
	 * @param class-string<T> $className
	 * @return T|null
	 */
	function getN2nExtensionByClassName(string $className): ?N2nExtension {
		foreach ($this->n2nExtensions as $n2nExtension) {
			if ($n2nExtension instanceof $className) {
				return $n2nExtension;
			}
		}

		return null;
	}

	function createN2nContext(?TransactionManager $transactionManager = null, ?PhpVars $phpVars = null): AppN2nContext {
		$n2nContext = new AppN2nContext($transactionManager ?? new TransactionManager(),
				$this, $phpVars ?? PhpVars::fromEnv());

		foreach ($this->n2nExtensions as $n2nExtension) {
			$n2nExtension->applyToN2nContext($n2nContext);
		}

		$appCache = $n2nContext->getAppCache();
		$appCache->setLocalCacheStorePool($this->appCacheSupplier->getLocalAppCacheStorePool($n2nContext));
		$appCache->setSharedCacheStorePool($this->appCacheSupplier->getSharedAppCacheStorePool($n2nContext));

		$lookupSession = $n2nContext->getHttp()?->getLookupSession() ?? new SimpleLookupSession();
		$lookupManager = new LookupManager($lookupSession,
				$n2nContext->getAppCache()->lookupCacheStore(LookupManager::class, true),
				$n2nContext);
		$n2nContext->setLookupManager($lookupManager);

		return $n2nContext;
	}
}
