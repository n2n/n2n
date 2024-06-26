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
namespace n2n\core\cache;

use n2n\cache\CacheStore;

interface AppCache extends \n2n\core\container\AppCache {
	/**
	 * @param string $namespace or type name of a related package or type
	 * @param bool $shared true if this cache must be shared between replicas of this application in containerized
	 * 		deployments. If not use false for better performance.
	 * @return CacheStore
	 */
	public function lookupCacheStore(string $namespace, bool $shared = true): CacheStore;

	/**
	 * Clear the cache of every cache store belonging to this {@see AppCache} instance.
	 */
	public function clear(): void;
}
