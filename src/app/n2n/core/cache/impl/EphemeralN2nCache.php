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
use n2n\util\cache\CacheStore;
use n2n\util\cache\impl\FileCacheStore;
use n2n\core\cache\N2nCache;
use n2n\core\VarStore;
use n2n\util\cache\impl\EphemeralCacheStore;

class EphemeralN2nCache implements N2nCache {

	private ?CacheStore $startupCacheStore = null;
	private ?AppCache $appCache = null;

	public function varStoreInitialized(VarStore $varStore): void {
	}

	public function getStartupCacheStore(): ?CacheStore {
		return $this->startupCacheStore ?? $this->startupCacheStore = new EphemeralCacheStore();
	}

	public function appConfigInitialized(AppConfig $appConfig): void {
	}

	public function getAppCache(): AppCache {
		return $this->appCache ?? $this->appCache = new EphemeralAppCache();
	}
}