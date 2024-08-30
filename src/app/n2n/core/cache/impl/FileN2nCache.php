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
namespace n2n\core\cache\impl;

use InvalidArgumentException;
use n2n\core\config\AppConfig;
use n2n\util\io\IoUtils;
use n2n\core\cache\AppCache;
use n2n\util\io\fs\FsPath;
use n2n\cache\CacheStore;
use n2n\cache\impl\fs\FileCacheStore;
use n2n\core\cache\N2NCache;
use n2n\core\VarStore;
use n2n\core\N2N;
use n2n\core\container\N2nContext;
use n2n\core\container\impl\AppN2nContext;
use n2n\util\io\fs\FileOperationException;
use n2n\cache\impl\CacheStorePools;

class FileN2nCache implements N2NCache {
	const STARTUP_CACHE_DIR = 'startupcache';
	const APP_CACHE_DIR = 'appcache';
	
	private VarStore $varStore;
	private $startupCacheStore;
	private $dirPerm;
	private $filePerm;
	
	/**
	 * {@inheritDoc}
	 * @see \n2n\core\cache\N2NCache::varStoreInitialized()
	 */
	public function varStoreInitialized(VarStore $varStore): void {
		$this->varStore = $varStore;
		$this->startupCacheStore = new FileCacheStore($this->varStore->requestDirFsPath(
				VarStore::CATEGORY_TMP, N2N::NS, self::STARTUP_CACHE_DIR, false, false));
	}
	
	/**
	 * {@inheritDoc}
	 * @see \n2n\core\cache\N2NCache::getStartupCacheStore()
	 */
	public function getStartupCacheStore(): ?CacheStore {
		return $this->startupCacheStore;
	}
	
	public function appConfigInitialized(AppConfig $appConfig): void {
		$this->dirPerm = $appConfig->io()->getPrivateDirPermission();
		$this->filePerm = $appConfig->io()->getPrivateFilePermission(); 
		$this->startupCacheStore->setDirPerm($this->dirPerm);
		$this->startupCacheStore->setFilePerm($this->filePerm);
	}
	
//	public function n2nContextInitialized(N2nContext $n2nContext) {
//	}
	
//	public function requestCacheStore($module, $componentName) {
//		if (!strlen($componentName) || !IoUtils::hasStrictSpecialChars($componentName)) {
//			throw new InvalidArgumentException('Component name is empty or contains strict special chars: ' . $componentName);
//		}
//
//		return new FileCacheStore($this->varStore->requestDirFsPath(VarStore::CATEGORY_TMP, $module,
//						$componentName), $this->dirPerm, $this->filePerm);
//	}

	public function applyToN2nContext(AppN2nContext $n2nContext): void {
		$n2nContext->setAppCache(new CombinedAppCache(
				CacheStorePools::file(
						$this->varStore->requestDirFsPath(VarStore::CATEGORY_TMP, N2N::NS, self::APP_CACHE_DIR),
						$this->dirPerm, $this->filePerm),
				CacheStorePools::file(
						$this->varStore->requestDirFsPath(VarStore::CATEGORY_TMP, N2N::NS, self::APP_CACHE_DIR, shared: true),
						$this->dirPerm, $this->filePerm)));
	}
}
