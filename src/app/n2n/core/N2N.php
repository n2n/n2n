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
namespace n2n\core;

use n2n\context\config\SimpleLookupSession;
use n2n\core\container\TransactionManager;
use n2n\log4php\Logger;
use n2n\core\module\Module;
use n2n\core\err\ExceptionHandler;
use n2n\l10n\N2nLocale;
use n2n\util\io\IoUtils;
use n2n\batch\BatchJobRegistry;
use n2n\context\LookupManager;
use n2n\web\http\controller\ControllerRegistry;
use n2n\core\config\AppConfig;
use n2n\core\module\ModuleFactory;
use n2n\core\module\impl\LazyModule;
use n2n\util\io\fs\FsPath;
use n2n\core\config\build\CombinedConfigSource;
use n2n\core\config\build\AppConfigFactory;
use n2n\l10n\L10n;
use n2n\core\module\ModuleManager;
use n2n\core\container\impl\AppN2nContext;
use n2n\util\type\CastUtils;
use n2n\core\module\impl\EtcModuleFactory;
use n2n\l10n\MessageContainer;
use n2n\web\dispatch\DispatchContext;
use n2n\util\StringUtils;
use n2n\core\module\UnknownModuleException;
use n2n\core\err\LogMailer;
use n2n\core\container\impl\PhpVars;
use n2n\core\ext\N2nExtension;
use n2n\config\InvalidConfigurationException;
use n2n\core\cache\N2NCache;
use n2n\core\container\N2nContext;
use n2n\util\ex\IllegalStateException;
use n2n\core\cache\impl\N2nCaches;
use n2n\core\ext\ConfigN2nExtension;
use n2n\cache\CharacteristicsList;

define('N2N_CRLF', "\r\n");

class N2N {
	const VERSION = '7.4.4';
	const LOG4PHP_CONFIG_FILE = 'log4php.xml'; 
	const LOG_EXCEPTION_DETAIL_DIR = 'exceptions';
	const LOG_MAIL_BUFFER_DIR = 'log-mail-buffer';
	const LOG_ERR_FILE = 'err.log';
	const SRV_BATCH_DIR = 'batch';
 	const NS = 'n2n';
	const CHARSET = 'utf-8';
	const CHARSET_MIN = 'utf8';
	const CONFIG_CACHE_NAME = 'cache';
	const SYNC_DIR = 'sync';
	
	const STAGE_DEVELOPMENT = 'development';
	const STAGE_TEST = 'test';
	const STAGE_LIVE = 'live';
	
	protected $publicDirPath;
	protected $varStore;
	protected $combinedConfigSource;
	protected ModuleManager $moduleManager;
	protected AppConfig $appConfig;
	protected N2NCache $n2nCache;

	/**
	 * @var N2nExtension[] $n2nExtensions
	 */
	protected array $n2nExtensions = [];
	
	private static $initialized = false;

	private function __construct(FsPath $publicDirPath, FsPath $varDirPath) {
		$this->publicDirPath = $publicDirPath;


	}
	
	private static function initModules(ModuleFactory $moduleFactory, VarStore $varStore,
			CombinedConfigSource $combinedConfigSource): ModuleManager {
		$moduleFactory->init($varStore);
		
		$combinedConfigSource->setMain($moduleFactory->getMainAppConfigSource());
		$moduleManager = new ModuleManager();
		
		foreach ($moduleFactory->getModules() as $module) {
			$moduleManager->registerModule($module);
			if (null !== ($appConfigSource = $module->getAppConfigSource())) {
				$combinedConfigSource->putAdditional((string) $module, $appConfigSource);
			}
		}
		
		if (!$moduleManager->containsModuleNs(self::NS)) {
			$moduleManager->registerModule(new LazyModule(self::NS));
		}

		return $moduleManager;
	}

	/**
	 *
	 * @param N2NCache $n2nCache
	 * @param VarStore $varStore
	 * @return AppConfig
	 */
	private static function initConfiguration(CombinedConfigSource $combinedConfigSource, N2NCache $n2nCache,
			VarStore $varStore, FsPath $publicDirPath): AppConfig {
		$n2nCache->varStoreInitialized($varStore);

		$cacheStore = $n2nCache->getStartupCacheStore();
		$hashCode = null;
		if ($cacheStore === null || null === ($hashCode = $combinedConfigSource->hashCode())) {
			$appConfigFactory = new AppConfigFactory($publicDirPath);
			$appConfig = $appConfigFactory->create($combinedConfigSource, N2N::getStage());
			self::applyConfiguration($appConfig, $n2nCache, $varStore);
			return $appConfig;
		}

		$characteristicsList = CharacteristicsList::fromArg(array('version' => N2N::VERSION, 'stage' => N2N::getStage(),
				'hashCode' => $hashCode, 'publicDir' => $publicDirPath));
		if (null !== ($cacheItem = $cacheStore->get(self::CONFIG_CACHE_NAME, $characteristicsList))) {
			$cachedAppConfig = $cacheItem->getData();
			if ($cachedAppConfig instanceof AppConfig) {
				$appConfig = $cachedAppConfig;
				self::applyConfiguration($appConfig, $n2nCache, $varStore);
				return $appConfig;
			}
		}

		$appConfigFactory = new AppConfigFactory($publicDirPath);
		$appConfig = $appConfigFactory->create($combinedConfigSource, N2N::getStage());
		self::applyConfiguration($appConfig, $n2nCache, $varStore);
		$cacheStore->removeAll(self::CONFIG_CACHE_NAME);
		$cacheStore->store(self::CONFIG_CACHE_NAME, $characteristicsList, $appConfig);

		return $appConfig;
	}

	private static function applyConfiguration(AppConfig $appConfig, N2NCache $n2nCache, VarStore $varStore): void {
		$errorConfig = $appConfig->error();
		self::$exceptionHandler?->setStrictAttitude($errorConfig->isStrictAttitudeEnabled());
		self::$exceptionHandler?->setDetectStartupErrorsEnabled($errorConfig->isDetectStartupErrorsEnabled());

		if ($errorConfig->isLogSendMailEnabled()) {
			self::$exceptionHandler?->setLogMailRecipient($errorConfig->getLogMailRecipient(),
					$appConfig->mail()->getDefaultAddresser());
		}

		$varStore->setSharedEnabled($appConfig->general()->isApplicationReplicatable());
		$ioConfig = $appConfig->io();
		$varStore->setDirPerm($ioConfig->getPrivateDirPermission());
		$varStore->setFilePerm($ioConfig->getPrivateFilePermission());
		
		$n2nLocaleConfig = $appConfig->locale();
		L10n::setPeclIntlEnabled($appConfig->l10n()->isEnabled());
		N2nLocale::setDefault($n2nLocaleConfig->getDefaultN2nLocale());
		N2nLocale::setFallback($n2nLocaleConfig->getFallbackN2nLocale());
		N2nLocale::setAdmin($n2nLocaleConfig->getAdminN2nLocale());
		N2nLocale::setWebAliases($appConfig->web()->getAliasN2nLocales());
		
		L10n::setL10nConfig($appConfig->l10n());
		L10n::setPseudoL10nConfig($appConfig->pseudoL10n());
		
		$n2nCache->appConfigInitialized($appConfig);
	}

	private static function initExtensions(N2nApplication $n2nApplication): void {
		foreach ($n2nApplication->getAppConfig()->general()->getExtensionClassNames() as $extensionClassName) {
			self::setUpExtension($extensionClassName, $n2nApplication);
		}
	}

	private static function setUpExtension(string $extensionClassName, N2nApplication $n2nApplication): void {
		try {
			$class = new \ReflectionClass($extensionClassName);
		} catch (\ReflectionException $e) {
			throw new N2nStartupException('Could not set up extension: ' . $extensionClassName, null, $e);
		}

		if (!$class->implementsInterface(ConfigN2nExtension::class)) {
			throw new N2nStartupException('Extension class must implement \'' . ConfigN2nExtension::class
					. '\': ' . $extensionClassName);
		}

		try {
			$n2nApplication->registerN2nExtension($class->newInstance($n2nApplication));
		} catch (\ReflectionException $e) {
			throw new InvalidConfigurationException('Invalid extension class: ' . $extensionClassName, 0,
					$e);
		}
	}

	
// 	private function initRegistry() {
// 		$this->batchJobRegistry = new BatchJobRegistry($this->n2nContext->getLookupManager(),
// 				$this->varStore->requestDirFsPath(VarStore::CATEGORY_TMP, self::NS, self::SRV_BATCH_DIR));
		
// 		foreach ($this->appConfig->general()->getBatchControllerClassNames() as $batchJobClassName) {
// 			$this->batchJobRegistry->registerBatchControllerClassName($batchJobClassName);
// 		}
		
// 		if (N2N::isHttpContextAvailable()) {
// 			$webConfig = $this->appConfig->web();
		
// 			$this->contextControllerRegistry = new ControllerRegistry();
// 			foreach ($webConfig->getFilterControllerDefs() as $contextControllerDef) {
// 				$this->contextControllerRegistry->registerFilterControllerDef($contextControllerDef);
// 			}
// 			foreach ($webConfig->getMainControllerDefs() as $contextControllerDef) {
// 				$this->contextControllerRegistry->registerMainControllerDef($contextControllerDef);
// 			}
// 			foreach ($this->appConfig->locale()->getN2nLocales() as $alias => $n2nLocale) {
// 				$this->contextControllerRegistry->setContextN2nLocale($alias, $n2nLocale);
// 			}
// 		}
// 	}
	/*
	 * STATIC
	 */

	private static ?ExceptionHandler $exceptionHandler = null;
	private static ?N2nApplication $n2nApplication = null;
	private static array $shutdownListeners = array();

	private static ?AppN2nContext $n2nContext = null;

	
	public static function setup(string $publicDirPath, string $varDirPath,
			?N2NCache $n2nCache = null, ?ModuleFactory $moduleFactory = null, bool $enableExceptionHandler = true,
			?LogMailer $logMailer = null): N2nApplication {

		// ignore if deprecated FileN2nCache from old projects
		if ($n2nCache instanceof FileN2nCache) {
			$n2nCache = null;
		}

		mb_internal_encoding(self::CHARSET);
		// 		ini_set('default_charset', self::CHARSET);
		
		if ($enableExceptionHandler) {
			self::setUpExceptionHandler($logMailer);
		}

		$publicDirFsPath = new FsPath(IoUtils::realpath($publicDirPath));
		$n2nCache = $n2nCache ?? (N2N::isTestStageOn() ? N2nCaches::ephemeral() : N2nCaches::file());
		$varStore = new VarStore(new FsPath(IoUtils::realpath($varDirPath)), null, null);
		$combinedConfigSource = new CombinedConfigSource();
		$moduleManager = self::initModules($moduleFactory ?? new EtcModuleFactory(), $varStore, $combinedConfigSource);
		$appConfig = self::initConfiguration($combinedConfigSource, $n2nCache, $varStore, $publicDirFsPath);

		Sync::init($varStore->requestDirFsPath(VarStore::CATEGORY_TMP, self::NS, self::SYNC_DIR));

		self::$n2nApplication = new N2nApplication($varStore, $moduleManager, $n2nCache,
				$appConfig, $publicDirFsPath);
		self::initExtensions(self::$n2nApplication);

		self::initLogging(self::$n2nApplication);

		return self::$n2nApplication;
	}

	private static function setUpExceptionHandler(?LogMailer $logMailer): void {
		self::$exceptionHandler = new ExceptionHandler(N2N::isDevelopmentModeOn());
		register_shutdown_function(array('n2n\core\N2N', 'shutdown'));

		if ($logMailer !== null) {
			self::$exceptionHandler->setLogMailer($logMailer);
		}
	}

	public static function initialize(string $publicDirPath, string $varDirPath, 
			?N2NCache $n2nCache = null, ?ModuleFactory $moduleFactory = null, bool $enableExceptionHandler = true,
			?LogMailer $logMailer = null): void {
		if (self::$n2nApplication !== null) {
			throw new IllegalStateException('N2nApplication already initialized. Call N2N::initializeWithN2nContext() instead.');
		}

		self::setup($publicDirPath, $varDirPath, $n2nCache, $moduleFactory, $enableExceptionHandler, $logMailer);
		
		self::initializeWithN2nContext(self::$n2nApplication->createN2nContext());
//		self::registerShutdownListener(self::$n2nContext);

	}

	public static function initializeWithN2nContext(AppN2nContext $n2nContext): void {
		if (self::$n2nApplication === null) {
			throw new IllegalStateException('No N2nApplication was set up.');
		}

		self::$n2nContext = $n2nContext;
		self::$initialized = true;

		self::$exceptionHandler?->checkForStartupErrors();
	}

	private static function initLogging(N2nApplication $n2nApplication): void {
		if (self::$exceptionHandler === null) {
			return;
		}

		$errorConfig = $n2nApplication->getAppConfig()->error();
		
		if ($errorConfig->isLogSaveDetailInfoEnabled()) {
			self::$exceptionHandler->setLogDetailDirPath(
					(string) $n2nApplication->getVarStore()->requestDirFsPath(VarStore::CATEGORY_LOG, self::NS, self::LOG_EXCEPTION_DETAIL_DIR, true),
					$n2nApplication->getAppConfig()->io()->getPrivateFilePermission());
		}
		
		if ($errorConfig->isLogHandleStatusExceptionsEnabled()) {
			self::$exceptionHandler->setLogStatusExceptionsEnabled(true, 
					$errorConfig->getLogExcludedHttpStatus());
		} else {
			self::$exceptionHandler->setLogStatusExceptionsEnabled(false, array());
		}
		
		if ($errorConfig->isLogSendMailEnabled()) {
			self::$exceptionHandler->setLogMailBufferDirPath(
					$n2nApplication->getVarStore()->requestDirFsPath(VarStore::CATEGORY_TMP, self::NS, self::LOG_MAIL_BUFFER_DIR));
		}
		
		Logger::configure((string) $n2nApplication->getVarStore()->requestFileFsPath(
				VarStore::CATEGORY_ETC, null, null, self::LOG4PHP_CONFIG_FILE, true, false));
	
		$logLevel = $n2nApplication->getAppConfig()->general()->getApplicationLogLevel();
		
		if (isset($logLevel)) {
			Logger::getRootLogger()->setLevel($logLevel);
		}
		
//		self::$exceptionHandler->setLogger(Logger::getLogger(get_class(self::$exceptionHandler)));
	}
	/**
	 * 
	 * @return bool
	 */
	public static function isInitialized(): bool {
		return self::$initialized;
	}
	
	public static function finalize(): void {
		foreach (self::$shutdownListeners as $shutdownListener) {
			$shutdownListener->onShutdown();
		}
		
// 		self::shutdown();
	}

	static function forkN2nContext(bool $keepTransactionContext = false): AppN2nContext {
		$transactionManager = null;
		if ($keepTransactionContext && self::$n2nContext !== null) {
			$transactionManager = self::$n2nContext->getTransactionManager();
			IllegalStateException::assertTrue(!$transactionManager->hasOpenTransaction(),
					'Current TransactionManager can not be kept because it has open transactions.');
		}

		return self::_i()->createN2nContext($transactionManager);
	}
	/**
	 * 
	 */
	public static function shutdown(): void {
	    if (self::$exceptionHandler !== null) {
    		self::$exceptionHandler->checkForFatalErrors();
    		if (!self::$exceptionHandler->isStable()) return;
	    }

//		try {
//			if (!N2N::isInitialized()) return;
//
//			if (N2N::isHttpContextAvailable() && !N2N::getCurrentResponse()->isFlushed()) {
//				N2N::getCurrentResponse()->flush();
//			}
//		} catch (\Throwable $t) {
//		    if (self::$exceptionHandler === null) {
//		        throw $t;
//		    }
//
//			self::$exceptionHandler->handleThrowable($t);
//		}

		self::$n2nContext?->finalize();
	}
	/**
	 * @param \n2n\core\ShutdownListener $shutdownListener
	 * @deprecated unsafe can cause memory leaks
	 */
	public static function registerShutdownListener(ShutdownListener $shutdownListener): void {
		self::$shutdownListeners[spl_object_hash($shutdownListener)] = $shutdownListener;
	}
	/**
	 * 
	 * @param \n2n\core\ShutdownListener $shutdownListener
	 * @deprecated unsafe can cause memory leaks
	 */
	public static function unregisterShutdownListener(ShutdownListener $shutdownListener): void {
		unset(self::$shutdownListeners[spl_object_hash($shutdownListener)]);
	}
	/**
	 * @return N2nApplication
	 * @throws N2nHasNotYetBeenInitializedException
	 */
	protected static function _i(): N2nApplication {
		if (self::$n2nApplication === null) {
			throw new N2nHasNotYetBeenInitializedException('No N2N instance has been initialized for current thread.');
		}
		return self::$n2nApplication;
	}

	static function getN2nApplication(): N2nApplication {
		return self::_i();
	}

	/**
	 * @return bool
	 */
	public static function isDevelopmentModeOn(): bool {
		return defined('N2N_STAGE') && N2N_STAGE == self::STAGE_DEVELOPMENT;
	}
	/**
	 * @return bool
	 */
	public static function isLiveStageOn(): bool {
		return !defined('N2N_STAGE') || N2N_STAGE == self::STAGE_LIVE;
	}

	static function isTestStageOn(): bool {
		return defined('N2N_STAGE') && N2N_STAGE == self::STAGE_TEST;
	}
	
	public static function getStage() {
		if (defined('N2N_STAGE')) {
			return N2N_STAGE;
		}
		
		return self::STAGE_LIVE;
	}
	/**
	 * 
	 * @return ExceptionHandler
	 */
	public static function getExceptionHandler(): ?ExceptionHandler {
		return self::$exceptionHandler;
	}
	/**
	 * 
	 * @return AppConfig
	 * @deprecated
	 */
	public static function getAppConfig(): AppConfig {
		return self::_i()->getAppConfig();
	}
	/**
	 * 
	 * @return FsPath|null
	 * @deprecated
	 */
	public static function getPublicDirPath(): ?FsPath {
		return self::_i()->getPublicFsPath();
	}
	/**
	 * 
	 * @return VarStore
	 * @deprecated
	 */
	public static function getVarStore(): VarStore {
		return self::_i()->getVarStore();
	}
	/**
	 * @deprecated use HttpContext
	 * @return \n2n\l10n\N2nLocale[]
	 */
	public static function getN2nLocales() {
		return self::_i()->getAppConfig()->routing()->getAllN2nLocales();
	}
	/**
	 * @deprecated use HttpContext
	 * @param string $n2nLocaleId
	 * @return boolean
	 */
	public static function hasN2nLocale($n2nLocaleId): bool {
		$n2nLocales = self::getN2nLocales();
		return isset($n2nLocales[(string) $n2nLocaleId]);
	}
	/**
	 * @deprecated use HttpContext
	 * @param string $n2nLocaleId
	 * @throws N2nLocaleNotFoundException
	 * @return \n2n\l10n\N2nLocale
	 */
	public static function getN2nLocaleById($n2nLocaleId): N2nLocale {
		$n2nLocales = self::getN2nLocales();
		if (isset($n2nLocales[(string) $n2nLocaleId])) {
			return $n2nLocales[(string) $n2nLocaleId];
		}
		
		throw new N2nLocaleNotFoundException('N2nLocale not found: ' . $n2nLocaleId);
	}
//	/**
//	 *
//	 * @return array
//	 */
//	public static function getN2nLocaleIds() {
//		return array_keys(self::_i()->n2nLocales);
//	}
//	/**
//	 *
//	 * @param \n2n\l10n\Language $language
//	 * @return array<\n2n\l10n\N2nLocale>
//	 */
//	public static function getN2nLocalesByLanguage(Language $language) {
//		return self::getN2nLocalesByLanguageId($language);
//	}
//	/**
//	 *
//	 * @param string $languageShort
//	 * @return array<\n2n\l10n\N2nLocale>
//	 */
//	public static function getN2nLocalesByLanguageId($languageShort) {
//		$languageShort = (string) $languageShort;
//		$n2nLocales = array();
//		foreach (self::getN2nLocales() as $n2nLocale) {
//			if ($n2nLocale->getLanguage()->getShort() == $languageShort) {
//				$n2nLocales[] = $n2nLocale;
//			}
//		}
//		return $n2nLocales;
//	}
//	/**
//	 *
//	 * @param \n2n\l10n\Region $region
//	 * @return array<\n2n\l10n\N2nLocale>
//	 */
//	public static function getN2nLocalesByRegion(Region $region) {
//		return self::getN2nLocalesByLanguageId($region);
//	}
//	/**
//	 *
//	 * @param string $regionShort
//	 * @return array<\n2n\l10n\N2nLocale>
//	 */
//	public static function getN2nLocalesByRegionShort($regionShort) {
//		$regionShort = (string) $regionShort;
//		$n2nLocales = array();
//		foreach (self::getN2nLocales() as $n2nLocale) {
//			if ($n2nLocale->getRegion()->getShort() == $regionShort) {
//				$n2nLocales[] = $n2nLocale;
//			}
//		}
//		return $n2nLocales;
//	}
//	/**
//	 *
//	 * @return array<\n2n\l10n\Language>
//	 */
//	public static function getLanguages() {
//		return self::_i()->languages;
//	}
//	/**
//	 *
//	 * @param string $languageShort
//	 * @return boolean
//	 */
//	public static function hasLanguage($languageShort) {
//		return isset(self::_i()->languages[(string) $languageShort]);
//	}
	/**
	 *
	 * @return \n2n\core\module\Module[]
	 */
	public static function getModules() {
		return self::_i()->getModuleManager()->getModules();
	}

	public static function registerModule(Module $module) {
		self::_i()->getModuleManager()->registerModule($module);
	}

	public static function unregisterModule($module) {
		$namespace = (string) $module;

		self::_i()->getModuleManager()->unregisterModuleByNamespace($namespace);
	}

	/**
	 * @deprecated
	 */
	public static function containsModule($module) {
		return self::_i()->getModuleManager()->containsModuleNs($module);
	}

	/**
	 * @deprecated
	 */
 	public static function getModuleByClassName(string $className) {
 		foreach (self::_i()->getModuleManager()->getModules() as $namespace => $module) {
 			if (StringUtils::startsWith($namespace, $className)) {
 				return $module;
 			}
 		}
		
 		throw new UnknownModuleException('Class does not belong to any module: ' . $className);
 	}
	
	public static function getN2nContext(): AppN2nContext {
		return self::$n2nContext;
	}
	/**
	 * 
	 * @return boolean
	 */
	public static function isHttpContextAvailable(): bool {
		return self::$n2nContext->isHttpContextAvailable();
	}

	/**
	 * @return \n2n\web\http\HttpContext
	 */
	public static function getHttpContext() {
		return self::$n2nContext->getHttpContext();
	}
	/**
	 * 
	 * @throws \n2n\web\http\HttpContextNotAvailableException
	 * @return \n2n\web\http\Request
	 */
	public static function getCurrentRequest() {
		return self::getHttpContext()->getRequest();
	}
	/**
	 * 
	 * @throws \n2n\web\http\HttpContextNotAvailableException
	 * @return \n2n\web\http\Response
	 */
	public static function getCurrentResponse() {
		return self::getHttpContext()->getResponse();
	}
	
//	public static function createControllingPlan($subsystemName = null) {
//		$request = self::$n2nContext->getHttpContext()->getRequest();
//		if ($subsystemName === null) {
//			$subsystemName = $request->getSubsystemName();
//		}
//
//		/**
//		 * @var ControllerRegistry
//		 */
//		$controllerRegistry = self::$n2nContext->lookup(ControllerRegistry::class);
//
//		return $controllerRegistry->createControllingPlan(
//				self::$n2nContext, $request->getCmdPath(), $subsystemName);
//	}
	
	public static function autoInvokeBatchJobs() {
		$n2nContext = self::$n2nContext;
		$batchJobRegistry = $n2nContext->lookup(BatchJobRegistry::class);
		CastUtils::assertTrue($batchJobRegistry instanceof BatchJobRegistry);
		$batchJobRegistry->trigger();
		
	}
	
	public static function autoInvokeControllers(): void {
		self::$n2nContext->getHttp()?->invokerControllers(true);
	}
	
//	public static function invokerControllers(?string $subsystemName = null, ?Path $cmdPath = null) {
//		$n2nContext = self::$n2nContext;
//		$httpContext = $n2nContext->getHttpContext();
//		$request = $httpContext->getRequest();
//
//        $subsystem = null;
//		if ($subsystemName !== null) {
//			$subsystem = $httpContext->getAvailableSubsystemByName($subsystemName);
//		}
//		$request->setSubsystem($subsystem);
//
//
//		$controllerRegistry = $n2nContext->lookup(ControllerRegistry::class);
//
//		if ($cmdPath === null) {
//			$cmdPath = $request->getCmdPath();
//		}
//		$controllerRegistry->createControllingPlan($request->getCmdPath(), $request->getSubsystemName())->execute();
//	}
	/**
	 * @return \n2n\context\LookupManager
	 */
	public static function getLookupManager() {
		return self::$n2nContext->getLookupManager();
	}
	/**
	 * @return \n2n\core\container\PdoPool
	 */
	public static function getPdoPool(): container\PdoPool {
		return self::$n2nContext->lookup(\n2n\core\container\PdoPool::class);
	}
	/**
	 * 
	 * @return \n2n\l10n\MessageContainer
	 */
	public static function getMessageContainer() {
		return self::$n2nContext->lookup(MessageContainer::class);
	}
	/**
	 *
	 * @return \n2n\web\dispatch\DispatchContext
	 */
	public static function getDispatchContext() {
		return self::$n2nContext->lookup(DispatchContext::class);
	}
	/**
	 * 
	 * @return \n2n\core\container\TransactionManager
	 */
	public static function getTransactionManager() {
		return self::$n2nContext->getTransactionManager();
	}
	
// 	public static function setTransactionContext(TransactionManager $transactionManager) {
// 		self::_i()->transactionalContext = $transactionManager;
// 	}


	/**
	 *
	 * @return array
	 */
	public static function getLastUserTracePoint($minBack = 0, $scriptPath = null/*, $outOfMdule = null*/) {
		$back = (int) $minBack;
		foreach(debug_backtrace(false) as $key => $tracePoint) {
			if (!$key || !isset($tracePoint['file'])) continue;
	
			if ($back-- > 0) continue;
				
			if (isset($scriptPath)) {
				if ($tracePoint['file'] == $scriptPath) {
					return $tracePoint;
				}
				continue;
			}
				
			// 			if (isset($outOfMdule)) {
			// 				if (TypeLoader::isFilePartOfNamespace($tracePoint['file'], (string) $outOfMdule)) {
			// 					continue;
			// 				} else {
			// 					return $tracePoint;
			// 				}
			// 			}
				
			//if (substr($tracePoint['file'], 0, mb_strlen($modulePath)) == $modulePath) {
			return $tracePoint;
			//}
		}
	
		return null;
	}
}

class N2nHasNotYetBeenInitializedException extends N2nRuntimeException {
	
}

class N2nLocaleNotFoundException extends N2nRuntimeException {
	
}
