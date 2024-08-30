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

use n2n\core\config\AppConfig;
use n2n\core\cache\AppCache;
use n2n\cache\CacheStore;
use n2n\core\cache\N2NCache;
use n2n\core\VarStore;
use n2n\cache\impl\ephemeral\EphemeralCacheStore;
use n2n\core\container\impl\AppN2nContext;

class EphemeralN2nCache implements N2NCache {

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

	function applyToN2nContext(AppN2nContext $n2nContext): void {
		$n2nContext->setAppCache($this->getAppCache());
	}
}