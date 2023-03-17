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
use n2n\persistence\ext\PdoPool;
use n2n\core\module\Module;
use n2n\core\err\ExceptionHandler;
use n2n\l10n\N2nLocale;
use n2n\util\io\IoUtils;
use n2n\batch\BatchJobRegistry;
use n2n\context\LookupManager;
use n2n\web\http\controller\ControllerRegistry;
use n2n\core\config\AppConfig;
use n2n\web\http\Request;
use n2n\web\http\Session;
use n2n\web\http\VarsRequest;
use n2n\core\module\ModuleFactory;
use n2n\core\module\impl\LazyModule;
use n2n\util\io\fs\FsPath;
use n2n\core\config\build\CombinedConfigSource;
use n2n\core\config\build\AppConfigFactory;
use n2n\l10n\L10n;
use n2n\core\module\ModuleManager;
use n2n\core\container\impl\AppN2nContext;
use n2n\web\http\HttpContext;
use n2n\util\type\CastUtils;
use n2n\core\module\impl\EtcModuleFactory;
use n2n\web\http\Method;
use n2n\web\http\MethodNotAllowedException;
use n2n\l10n\MessageContainer;
use n2n\web\dispatch\DispatchContext;
use n2n\web\http\VarsSession;
use n2n\web\http\BadRequestException;
use n2n\util\StringUtils;
use n2n\core\module\UnknownModuleException;
use n2n\web\http\controller\ControllingPlan;
use n2n\core\err\LogMailer;
use n2n\core\container\impl\PhpVars;
use n2n\core\ext\N2nExtension;
use n2n\config\InvalidConfigurationException;
use n2n\core\cache\N2nCache;
use n2n\core\container\N2nContext;
use n2n\util\ex\IllegalStateException;

define('N2N_CRLF', "\r\n");

class N2N {
	const VERSION = '7.3';
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
	protected N2nCache $n2nCache;

	/**
	 * @var N2nExtension[] $n2nExtensions
	 */
	protected array $n2nExtensions = [];
	
	private static $initialized = false;
	/**
	 * 
	 * @param string $publicDirPath
	 * @param string $varDirPath
	 * @param array $moduleDirPaths
	 */
	private function __construct(FsPath $publicDirPath, FsPath $varDirPath) {
		$this->publicDirPath = $publicDirPath;
		$this->varStore = new VarStore($varDirPath, null, null);
		
		$this->combinedConfigSource = new CombinedConfigSource();
	}
	
	protected function initModules(ModuleFactory $moduleFactory): void {
		$moduleFactory->init($this->varStore);
		
		$this->combinedConfigSource->setMain($moduleFactory->getMainAppConfigSource());
		$this->moduleManager = new ModuleManager();
		
		foreach ($moduleFactory->getModules() as $module) {
			$this->moduleManager->registerModule($module);
			if (null !== ($appConfigSource = $module->getAppConfigSource())) {
				$this->combinedConfigSource->putAdditional((string) $module, $appConfigSource);
			}
		}
		
		if (!$this->moduleManager->containsModuleNs(self::NS)) {
			$this->moduleManager->registerModule(new LazyModule(self::NS));
		}
	}

	/**
	 * 
	 * @param \n2n\core\config\AppConfig
	 * @throws InvalidConfigurationException
	 */
	private function initConfiguration(N2nCache $n2nCache): void {
		$n2nCache->varStoreInitialized($this->varStore);

		$this->n2nCache = $n2nCache;
		$cacheStore = $n2nCache->getStartupCacheStore();
		$hashCode = null;
		if ($cacheStore === null || null === ($hashCode = $this->combinedConfigSource->hashCode())) {
			$appConfigFactory = new AppConfigFactory($this->publicDirPath);
			$this->appConfig = $appConfigFactory->create($this->combinedConfigSource, N2N::getStage());
			$this->applyConfiguration($n2nCache);
			return;
		}
		
		$characteristics = array('version' => N2N::VERSION, 'stage' => N2N::getStage(),
				'hashCode' => $hashCode, 'publicDir' => (string) $this->publicDirPath);
		if (null !== ($cacheItem = $cacheStore->get(self::CONFIG_CACHE_NAME, $characteristics))) {
			$cachedAppConfig = $cacheItem->getData();
			if ($cachedAppConfig instanceof AppConfig) {
				$this->appConfig = $cachedAppConfig;
				$this->applyConfiguration($n2nCache);
				return;
			}
		}

		$appConfigFactory = new AppConfigFactory($this->publicDirPath);
		$this->appConfig = $appConfigFactory->create($this->combinedConfigSource, N2N::getStage());
		$this->applyConfiguration($n2nCache);
		$cacheStore->removeAll(self::CONFIG_CACHE_NAME);
		$cacheStore->store(self::CONFIG_CACHE_NAME, $characteristics, $this->appConfig);
	}

	private function applyConfiguration(N2nCache $n2nCache): void {
		$errorConfig = $this->appConfig->error();
		self::$exceptionHandler?->setStrictAttitude($errorConfig->isStrictAttitudeEnabled());
		self::$exceptionHandler?->setDetectStartupErrorsEnabled($errorConfig->isDetectStartupErrorsEnabled());

		if ($errorConfig->isLogSendMailEnabled()) {
			self::$exceptionHandler?->setLogMailRecipient($errorConfig->getLogMailRecipient(),
					$this->appConfig->mail()->getDefaultAddresser());
		}

		$ioConfig = $this->appConfig->io();
		$this->varStore->setDirPerm($ioConfig->getPrivateDirPermission());
		$this->varStore->setFilePerm($ioConfig->getPrivateFilePermission());
		
		$n2nLocaleConfig = $this->appConfig->locale();
		L10n::setPeclIntlEnabled($this->appConfig->l10n()->isEnabled());
		N2nLocale::setDefault($n2nLocaleConfig->getDefaultN2nLocale());
		N2nLocale::setFallback($n2nLocaleConfig->getFallbackN2nLocale());
		N2nLocale::setAdmin($n2nLocaleConfig->getAdminN2nLocale());
		N2nLocale::setWebAliases($this->appConfig->web()->getAliasN2nLocales());
		
		L10n::setL10nConfig($this->appConfig->l10n());
		L10n::setPseudoL10nConfig($this->appConfig->pseudoL10n());
		
		$n2nCache->appConfigInitialized($this->appConfig);

		foreach ($this->appConfig->general()->getExtensionClassNames() as $extensionClassName) {
			$this->setUpExtension($extensionClassName);
		}
	}

	function createN2nContext(TransactionManager $transactionManager = null): AppN2nContext {
		$n2nContext = new AppN2nContext(new TransactionManager(), $this->moduleManager, $this->n2nCache->getAppCache(),
				$this->varStore, $this->appConfig, PhpVars::fromEnv());

		foreach ($this->n2nExtensions as $n2nExtension) {
			$n2nExtension->setUp($this->n2nContext);
		}

		$lookupSession = $this->n2nContext->getHttp()?->getLookupSession() ?? new SimpleLookupSession();
		$lookupManager = new LookupManager($lookupSession, $this->n2nCache->getAppCache()->lookupCacheStore(LookupManager::class),
				$this->n2nContext);
		$this->n2nContext->setLookupManager($lookupManager);

		return $n2nContext;
	}

	private function setUpExtension(string $extensionClassName): void {
		try {
			$class = new \ReflectionClass($extensionClassName);
		} catch (\ReflectionException $e) {
			throw new N2nStartupException('Could not set up extension: ' . $extensionClassName, null, $e);
		}

		if (!$class->implementsInterface(N2nExtension::class)) {
			throw new N2nStartupException('Extension class must implement \'' . N2nExtension::class
					. '\': ' . $extensionClassName);
		}

		try {
			$this->n2nExtensions[$extensionClassName] = $class->newInstance($this->appConfig, $this->n2nCache->getAppCache());
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

	private static ?ExceptionHandler $exceptionHandler;
	private static ?N2N $n2n = null;
	private static array $shutdownListeners = array();

	private static ?AppN2nContext $n2nContext = null;

	
	public static function setup(string $publicDirPath, string $varDirPath,
			N2nCache $n2nCache = null, ModuleFactory $moduleFactory = null, bool $enableExceptionHandler = true,
			LogMailer $logMailer = null): N2N {
		mb_internal_encoding(self::CHARSET);
		// 		ini_set('default_charset', self::CHARSET);
		
		if ($enableExceptionHandler) {
			self::setUpExceptionHandler($logMailer);
		}
		
		self::$n2n = new N2N(new FsPath(IoUtils::realpath($publicDirPath)),
				new FsPath(IoUtils::realpath($varDirPath)));
		self::$n2n->initModules($moduleFactory ?? new EtcModuleFactory());
		self::$n2n->initConfiguration($n2nCache ?? new \n2n\core\cache\impl\FileN2nCache());
		
		Sync::init(self::$n2n->varStore->requestDirFsPath(VarStore::CATEGORY_TMP, self::NS, self::SYNC_DIR));

		return self::$n2n;
	}

	private static function setUpExceptionHandler(?LogMailer $logMailer): void {
		self::$exceptionHandler = new ExceptionHandler(N2N::isDevelopmentModeOn());
		register_shutdown_function(array('n2n\core\N2N', 'shutdown'));

		if ($logMailer !== null) {
			self::$exceptionHandler->setLogMailer($logMailer);
		}
	}

	public static function initialize(string $publicDirPath, string $varDirPath, 
			N2nCache $n2nCache, ModuleFactory $moduleFactory = null, bool $enableExceptionHandler = true,
			LogMailer $logMailer = null): void {
		self::setup($publicDirPath, $varDirPath, $n2nCache, $moduleFactory, $enableExceptionHandler, $logMailer);
		
		self::$n2nContext = self::$n2n->createN2nContext();
		self::registerShutdownListener(self::$n2nContext);


		self::$initialized = true;
		
		// @todo move up so exception will be grouped earlier.
		self::initLogging(self::$n2n);

		self::$exceptionHandler?->checkForStartupErrors();
	}

	private static function initLogging(N2N $n2n): void {
		if (self::$exceptionHandler === null) {
			return;
		}

		$errorConfig = $n2n->appConfig->error();
		
		if ($errorConfig->isLogSaveDetailInfoEnabled()) {
			self::$exceptionHandler->setLogDetailDirPath(
					(string) $n2n->varStore->requestDirFsPath(VarStore::CATEGORY_LOG, self::NS, self::LOG_EXCEPTION_DETAIL_DIR, true),
					$n2n->appConfig->io()->getPrivateFilePermission());
		}
		
		if ($errorConfig->isLogHandleStatusExceptionsEnabled()) {
			self::$exceptionHandler->setLogStatusExceptionsEnabled(true, 
					$errorConfig->getLogExcludedHttpStatus());
		} else {
			self::$exceptionHandler->setLogStatusExceptionsEnabled(false, array());
		}
		
		if ($errorConfig->isLogSendMailEnabled()) {
			self::$exceptionHandler->setLogMailBufferDirPath(
					$n2n->varStore->requestDirFsPath(VarStore::CATEGORY_TMP, self::NS, self::LOG_MAIL_BUFFER_DIR));
		}
		
		Logger::configure((string) $n2n->varStore->requestFileFsPath(
				VarStore::CATEGORY_ETC, null, null, self::LOG4PHP_CONFIG_FILE, true, false));
	
		$logLevel = $n2n->appConfig->general()->getApplicationLogLevel();
		
		if (isset($logLevel)) {
			Logger::getRootLogger()->setLevel($logLevel);
		}
		
//		self::$exceptionHandler->setLogger(Logger::getLogger(get_class(self::$exceptionHandler)));
	}
	/**
	 * 
	 * @return bool
	 */
	public static function isInitialized() {
		return self::$initialized;
	}
	
	public static function finalize() {
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

		try {
			if (!N2N::isInitialized()) return;
				
			if (N2N::isHttpContextAvailable() && !N2N::getCurrentResponse()->isFlushed()) {
				N2N::getCurrentResponse()->flush();
			}
		} catch (\Throwable $t) {
		    if (self::$exceptionHandler === null) {
		        throw $t;
		    }
		    
			self::$exceptionHandler->handleThrowable($t);
		}
	}
	/**
	 * @param \n2n\core\ShutdownListener $shutdownListener
	 * @deprecated unsafe can cause memory leaks
	 */
	public static function registerShutdownListener(ShutdownListener $shutdownListener) {
		self::$shutdownListeners[spl_object_hash($shutdownListener)] = $shutdownListener;
	}
	/**
	 * 
	 * @param \n2n\core\ShutdownListener $shutdownListener
	 * @deprecated unsafe can cause memory leaks
	 */
	public static function unregisterShutdownListener(ShutdownListener $shutdownListener) {
		unset(self::$shutdownListeners[spl_object_hash($shutdownListener)]);
	}
	/**
	 * @return N2N
	 * @throws N2nHasNotYetBeenInitializedException
	 */
	protected static function _i() {
		if(self::$n2n === null) {
			throw new N2nHasNotYetBeenInitializedException('No N2N instance has been initialized for current thread.');
		}
		return self::$n2n;
	}
	/**
	 * @return bool
	 */
	public static function isDevelopmentModeOn() {
		return defined('N2N_STAGE') && N2N_STAGE == self::STAGE_DEVELOPMENT;
	}
	/**
	 * @return bool
	 */
	public static function isLiveStageOn() {
		return !defined('N2N_STAGE') || N2N_STAGE == self::STAGE_LIVE;
	}
	
	public static function getStage() {
		if (defined('N2N_STAGE')) {
			return N2N_STAGE;
		}
		
		return self::STAGE_LIVE;
	}
	/**
	 * 
	 * @return \n2n\core\err\ExceptionHandler
	 */
	public static function getExceptionHandler() {
		return self::$exceptionHandler;
	}
	/**
	 * 
	 * @return \n2n\core\config\AppConfig
	 */
	public static function getAppConfig() {
		return self::_i()->appConfig;
	}
	/**
	 * 
	 * @return string
	 */
	public static function getPublicDirPath() {
		return self::_i()->publicDirPath;
	}
	/**
	 * 
	 * @return \n2n\core\VarStore
	 */
	public static function getVarStore() {
		return self::_i()->varStore;	
	}
	/**
	 * 
	 * @return \n2n\l10n\N2nLocale[]
	 */
	public static function getN2nLocales() {
		return self::_i()->appConfig->web()->getAllN2nLocales();
	}
	/**
	 * 
	 * @param string $n2nLocaleId
	 * @return boolean
	 */
	public static function hasN2nLocale($n2nLocaleId) {
		return isset(self::_i()->n2nLocales[(string) $n2nLocaleId]);
	}
	/**
	 * 
	 * @param string $n2nLocaleId
	 * @throws N2nLocaleNotFoundException
	 * @return \n2n\l10n\N2nLocale
	 */
	public static function getN2nLocaleById($n2nLocaleId) {
		if (isset(self::_i()->n2nLocales[(string) $n2nLocaleId])) {
			return self::_i()->n2nLocales[(string) $n2nLocaleId];
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
		return self::_i()->moduleManager->getModules();
	}

	public static function registerModule(Module $module) {
		self::_i()->moduleManager->registerModule($module);
		if (null !== ($appConfigSource = $module->getAppConfigSource())) {
			self::_i()->combinedConfigSource->putAdditional((string) $module, $appConfigSource);
		}
	}

	public static function unregisterModule($module) {
		$namespace = (string) $module;

		self::_i()->moduleManager->unregisterModuleByNamespace($namespace);
		self::_i()->combinedConfigSource->removeAdditionalByKey($namespace);
	}

	public static function containsModule($module) {
		return self::_i()->getN2nContext()->getModuleManager()->containsModuleNs($module);
	}
	
 	public static function getModuleByClassName(string $className) {
 		foreach (self::_i()->getN2nContext()->getModuleManager()->getModules() as $namespace => $module) {
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
	
	public static function getHttpContext(): HttpContext {
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
	
	public static function createControllingPlan($subsystemName = null) {
		$request = self::$n2nContext->getHttpContext()->getRequest();
		if ($subsystemName === null) {
			$subsystemName = $request->getSubsystemName();
		}

		/**
		 * @var ControllerRegistry
		 */
		$controllerRegistry = self::$n2nContext->lookup(ControllerRegistry::class);

		return $controllerRegistry->createControllingPlan(
				self::$n2nContext, $request->getCmdPath(), $subsystemName);
	}
	
	public static function autoInvokeBatchJobs() {
		$n2nContext = self::$n2nContext;
		$batchJobRegistry = $n2nContext->lookup(BatchJobRegistry::class);
		CastUtils::assertTrue($batchJobRegistry instanceof BatchJobRegistry);
		$batchJobRegistry->trigger();
		
	}
	
	public static function autoInvokeControllers() {
		self::$n2nContext->getHttp()?->invokerControllers();
	}
	
//	public static function invokerControllers(string $subsystemName = null, Path $cmdPath = null) {
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
	 * @return \n2n\persistence\ext\PdoPool
	 */
	public static function getPdoPool() {
		return self::$n2nContext->lookup(PdoPool::class);
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
