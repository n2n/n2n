<?php
/*
 * Copyright (c) 2012-2016, Hofmänner New Media.
 * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS FILE HEADER.
 *
 * This file is part of the N2N FRAMEWORK.
 *
 * The N2N FRAMEWORK is free software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * N2N is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
 * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details: http://www.gnu.org/licenses/
 *
 * The following people participated in this project:
 *
 * Andreas von Burg.....: Architect, Lead Developer
 * Bert Hofmänner.......: Idea, Frontend UI, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\core\container\impl;

use n2n\core\ShutdownListener;
use n2n\web\http\Request;
use n2n\web\http\Response;
use n2n\l10n\N2nLocale;
use n2n\context\LookupManager;
use n2n\reflection\ReflectionUtils;
use n2n\web\http\HttpContextNotAvailableException;
use n2n\core\module\UnknownModuleException;
use n2n\l10n\DynamicTextCollection;
use n2n\context\LookupFailedException;
use n2n\core\config\AppConfig;
use n2n\core\VarStore;
use n2n\core\container\N2nContext;
use n2n\core\container\TransactionManager;
use n2n\core\module\ModuleManager;
use n2n\web\http\HttpContext;
use n2n\core\container\AppCache;
use n2n\util\ex\IllegalStateException;
use n2n\core\config\GeneralConfig;
use n2n\core\config\WebConfig;
use n2n\core\config\MailConfig;
use n2n\core\config\IoConfig;
use n2n\core\config\FilesConfig;
use n2n\core\config\ErrorConfig;
use n2n\core\config\DbConfig;
use n2n\core\config\OrmConfig;
use n2n\core\config\N2nLocaleConfig;
use n2n\persistence\orm\EntityManagerFactory;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\ext\PdoPool;
use n2n\web\http\Session;
use n2n\util\magic\MagicObjectUnavailableException;
use n2n\util\type\ArgUtils;
use n2n\core\util\N2nUtil;
use n2n\context\config\LookupSession;
use n2n\util\cache\CacheStore;
use n2n\context\LookupableNotFoundException;
use n2n\util\magic\MagicLookupFailedException;
use n2n\util\magic\MagicContext;
use n2n\core\ext\N2nHttp;
use n2n\core\module\Module;
use n2n\core\ext\N2nMonitor;

class AppN2nContext implements N2nContext, ShutdownListener {
	private ModuleManager $moduleManager;
	private AppCache $appCache;
	private VarStore $varStore;
	private AppConfig $appConfig;
	private array $moduleConfigs = array();
	private N2nLocale $n2nLocale;
	private ?LookupManager $lookupManager;
	private array $injectedObjects = [];

	private \SplObjectStorage $addOnContexts;

	private \SplObjectStorage $finalizeCallbacks;
	private ?N2nHttp $http = null;
	private ?N2nMonitor $monitor = null;
	private readonly PhpVars $phpVars;

	public function __construct(private TransactionManager $transactionManager, ModuleManager $moduleManager, AppCache $appCache,
			VarStore $varStore, AppConfig $appConfig, PhpVars $phpVars = null) {
		$this->transactionManager = $transactionManager;
		$this->moduleManager = $moduleManager;
		$this->appCache = $appCache;
		$this->varStore = $varStore;
		$this->appConfig = $appConfig;
		$this->n2nLocale = N2nLocale::getDefault();
		$this->phpVars = $phpVars ?? PhpVars::fromEnv();

		$this->addOnContexts = new \SplObjectStorage();
		$this->finalizeCallbacks = new \SplObjectStorage();
	}

	function util(): N2nUtil {
		return new N2nUtil($this);
	}

	function getAppConfig(): AppConfig {
		return $this->appConfig;
	}

	function getPhpVars(): PhpVars {
		return $this->phpVars;
	}

	function getTransactionManager(): TransactionManager {
		return $this->transactionManager;
	}

	/**
	 * @param LookupManager $lookupManager
	 */
	public function setLookupManager(LookupManager $lookupManager) {
		$this->lookupManager = $lookupManager;
	}

	/**
	 * @throws IllegalStateException
	 * @return \n2n\context\LookupManager
	 */
	public function getLookupManager(): LookupManager {
		if ($this->lookupManager !== null) {
			return $this->lookupManager;
		}

		throw new IllegalStateException('No LookupManager defined.');
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\core\container\N2nContext::getModuleManager()
	 */
	public function getModuleManager(): ModuleManager {
		return $this->moduleManager;
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\core\container\N2nContext::getModuleConfig($namespace)
	 */
	public function getModuleConfig(string $namespace) {
		if (array_key_exists($namespace, $this->moduleConfigs)) {
			return $this->moduleConfigs[$namespace];
		}

		$module = $this->moduleManager->getModuleByNs($namespace);
		if ($module->hasConfigDescriber()) {
			return $this->moduleConfigs[$namespace] = $module->createConfigDescriber($this)->buildCustomConfig();
		}

		return $this->moduleConfigs[$namespace] = null;
	}

	function setHttp(?N2nHttp $http): void {
		$this->http = $http;
	}

	function getHttp(): ?N2nHttp {
		return $this->http;
	}

	public function isHttpContextAvailable(): bool {
		return $this->http !== null;
	}

	function getMonitor(): ?N2nMonitor {
		return $this->monitor;
	}

	function setMonitor(?N2nMonitor $monitor) {
		$this->monitor = $monitor;
	}

	/**
	 * @deprecated
	 */
	public function getHttpContext(): HttpContext {
		if ($this->http !== null) {
			return $this->lookup(HttpContext::class);
		}

		throw new HttpContextNotAvailableException();
	}


	/**
	 * {@inheritDoc}
	 * @see \n2n\core\container\N2nContext::getVarStore()
	 */
	public function getVarStore(): VarStore {
		return $this->varStore;
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\core\container\N2nContext::getAppCache()
	 */
	public function getAppCache(): AppCache {
		return $this->appCache;
	}

	/**
	 * @return N2nLocale
	 */
	public function getN2nLocale(): N2nLocale {
		return $this->n2nLocale;
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\core\container\N2nContext::setN2nLocale($n2nLocale)
	 */
	public function setN2nLocale(N2nLocale $n2nLocale) {
		$this->n2nLocale = $n2nLocale;
	}

	function addAddonContext(AddOnContext $addOnContext) {
		$this->addOnContexts->attach($addOnContext);
	}

	function getAddonContexts(): array {
		return iterator_to_array($this->addOnContexts);
	}

	function removeAddonContext(AddOnContext $addOnContext) {
		$this->addOnContexts->detach($addOnContext);
	}

	function removeAddonContextByType(string $className): bool {
		foreach ($this->addOnContexts as $addOnContext) {
			if ($addOnContext instanceof $className)  {
				$this->removeAddonContext($addOnContext);
				return true;
			}
		}

		return false;
	}

	function putLookupInjection(string $id, object $obj): void {
		ArgUtils::valType($obj, $id, false, 'obj');

		$this->injectedObjects[$id] = $obj;
	}

	function removeLookupInjection(string $id): void {
		unset($this->injectedObjects[$id]);
	}

	function clearLookupInjections(): void {
		$this->injectedObjects = [];
	}

	public function get(string $id) {
		return $this->lookup($id, true);
	}

	public function has(string|\ReflectionClass $id): bool {
		if ($id instanceof \ReflectionClass) {
			$id = $id->getName();
		}

		if (isset($this->injectedObjects[$id])) {
			return true;
		}

		foreach ($this->addOnContexts as $magicContext) {
			if ($magicContext->hasMagicObject($id)) {
				return true;
			}
		}

		switch ($id) {
			case N2nContext::class:
			case N2nUtil::class:
			case LookupManager::class:
			case N2nLocale::class:
			case EntityManager::class:
			case EntityManagerFactory::class:
			case TransactionManager::class:
			case VarStore::class:
			case AppCache::class:
			case AppConfig::class:
			case GeneralConfig::class:
			case WebConfig::class:
			case MailConfig::class:
			case IoConfig::class:
			case FilesConfig::class:
			case ErrorConfig::class:
			case DbConfig::class:
			case OrmConfig::class:
			case N2nLocaleConfig::class:
			case DynamicTextCollection::class:
				return true;
			default:
				return $this->getLookupManager()->has($id);
		}
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\util\magic\MagicContext::lookup()
	 */
	public function lookup(string|\ReflectionClass $id, bool $required = true, string $contextNamespace = null): mixed {
		if ($id instanceof \ReflectionClass) {
			$id = $id->getName();
		}

		if (isset($this->injectedObjects[$id])) {
			return $this->injectedObjects[$id];
		}

		foreach ($this->addOnContexts as $magicContext) {
			if (null !== ($result = $magicContext->lookupMagicObject($id, $required, $contextNamespace))) {
				return $result;
			}
		}

		switch ($id) {
			case N2nContext::class:
			case MagicContext::class:
				return $this;
			case N2nUtil::class:
				return $this->util();
			case LookupManager::class:
				return $this->getLookupManager();
			case N2nLocale::class:
				return $this->getN2nLocale();
			case TransactionManager::class:
				return $this->transactionManager;
			case VarStore::class:
				return $this->varStore;
			case AppCache::class:
				return $this->appCache;
			case AppConfig::class:
				return $this->appConfig;
			case GeneralConfig::class:
				return $this->appConfig->general();
			case WebConfig::class:
				return $this->appConfig->web();
			case MailConfig::class:
				return $this->appConfig->mail();
			case IoConfig::class:
				return $this->appConfig->io();
			case FilesConfig::class:
				return $this->appConfig->files();
			case ErrorConfig::class:
				return $this->appConfig->error();
			case DbConfig::class:
				return $this->appConfig->db();
			case OrmConfig::class:
				return $this->appConfig->orm();
			case N2nLocaleConfig::class:
				return $this->appConfig->locale();
			case DynamicTextCollection::class:
				if ($contextNamespace !== null) {
					return $this->lookupDynamicTextCollection($id, $required, $contextNamespace);
				}
				throw new MagicLookupFailedException('Context namespace required to lookup: ' . $id);
			case Module::class:
				if ($contextNamespace !== null) {
					return $this->lookupModule($id, $required, $contextNamespace);
				}
				throw new MagicLookupFailedException('Context namespace required to lookup: ' . $id);
			default:
				if (!$required && !$this->getLookupManager()->has($id)) {
					return null;
				}

				try {
					return $this->getLookupManager()->lookup($id);
				} catch (LookupableNotFoundException $e) {
					throw new MagicObjectUnavailableException('Could not lookup object with name: ' . $id, 0, $e);
				} catch (LookupFailedException $e) {
					throw new MagicLookupFailedException('Could not lookup object with name: ' . $id, 0, $e);
				}
		}
	}

	private function lookupDynamicTextCollection(string $id, bool $required, string $contextNamespace): ?DynamicTextCollection {
		$module = null;
		try {
			$module = $this->moduleManager->getModuleOfTypeName($contextNamespace, $required);
		} catch (UnknownModuleException $e) {
			throw new MagicLookupFailedException('Could not determine module for DynamicTextCollection.', 0, $e);
		}

		if ($module === null) return null;

		return new DynamicTextCollection($module, $this->getN2nLocale());
	}

	private function lookupModule(string $id, bool $required, string $contextNamespace): ?Module {
		try {
			return $this->moduleManager->getModuleOfTypeName($contextNamespace, $required);
		} catch (UnknownModuleException $e) {
			throw new MagicLookupFailedException('Could not determine module.', 0, $e);
		}
	}


	function onFinalize(\Closure $callback): void {
		$this->finalizeCallbacks->attach($callback);
	}

	function offFinalize(\Closure $callback): void {
		$this->finalizeCallbacks->detach($callback);
	}

	function finalize(): void {
		$this->finalizeCallbacks->rewind();
		while ($this->finalizeCallbacks->valid()) {
			$this->finalizeCallbacks->current()($this);
			$this->finalizeCallbacks->next();
		}
		$this->finalizeCallbacks->removeAll($this->finalizeCallbacks);

		$this->addOnContexts->rewind();
		while ($this->addOnContexts->valid()) {
			$this->addOnContexts->current()->finalize($this);
			$this->addOnContexts->next();
		}

		if ($this->lookupManager !== null) {
			if ($this->lookupManager->contains(PdoPool::class)) {
				$this->lookupManager->lookup(PdoPool::class)->clear();
			}

			$this->lookupManager->shutdown();
			$this->lookupManager->clear();
		}
	}

	function onShutdown(): void {
		$this->finalize();
	}

// 	public function magicInit($object) {
// 		MagicUtils::init($object, $this);
// 	}

	function copy(LookupSession $lookupSession = null,
			CacheStore $applicationCacheStore = null, bool $keepTransactionContext = true): AppN2nContext {
		$transactionManager = null;
		if ($keepTransactionContext) {
			$transactionManager = $this->getTransactionManager();
		} else {
			$transactionManager = new TransactionManager();
		}

		$appN2nContext = new AppN2nContext($transactionManager, $this->getModuleManager(),
				$this->getAppCache(), $this->getVarStore(), $this->lookup(AppConfig::class),
				$this->getPhpVars());


		$appN2nContext->setLookupManager(new LookupManager(
				$lookupSession ?? $this->getLookupManager()->getLookupSession(),
				$applicationCacheStore ?? $this->getLookupManager()->getApplicationCacheStore(),
				$appN2nContext));

		$appN2nContext->setN2nLocale($this->getN2nLocale());

		foreach ($this->addOnContexts as $addOnContext) {
			$addOnContext->copyTo($appN2nContext);
		}

		return $appN2nContext;
	}

}
