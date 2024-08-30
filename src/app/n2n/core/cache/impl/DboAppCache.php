<?php
//
//namespace n2n\core\cache\impl;
//
//use n2n\core\cache\AppCache;
//use n2n\cache\CacheStore;
//use n2n\util\type\TypeUtils;
//use n2n\cache\impl\persistence\DboCacheStore;
//use n2n\spec\dbo\Dbo;
//use WeakReference;
//use ArrayObject;
//use n2n\util\StringUtils;
//use n2n\util\ex\ExUtils;
//
//class DboAppCache implements AppCache {
//
//
//	/**
//	 * @var ArrayObject<WeakReference>
//	 */
//	private ArrayObject $weakCacheStores;
//	/**
//	 * @var ArrayObject<WeakReference>
//	 */
//	private ArrayObject $weakSharedCacheStores;
//
//	function __construct(private readonly Dbo $dbo, private readonly Dbo $sharedDbo) {
//		$this->weakCacheStores = new ArrayObject();
//		$this->weakSharedCacheStores = new ArrayObject();
//	}
//
//	public function lookupCacheStore(string $namespace, bool $shared = true): CacheStore {
//		$tableName = mb_strtolower(TypeUtils::encodeNamespace($namespace, self::SEPARATOR));
//
//		if ($shared) {
//			return $this->determineCacheStore($this->weakSharedCacheStores, $tableName, $this->sharedDbo);
//		} else {
//			return $this->determineCacheStore($this->weakCacheStores, $tableName, $this->dbo);
//		}
//	}
//
//	/**
//	 * @param ArrayObject $weekReferences
//	 * @param string $tableName
//	 * @param Dbo $dbo
//	 * @return CacheStore
//	 */
//	private function determineCacheStore(ArrayObject $weekReferences, string $tableName, Dbo $dbo): CacheStore {
//		if (null !== ($cacheStore = $weekReferences[$tableName]?->getObj())) {
//			return $cacheStore;
//		}
//
//		$cacheStore = new DboCacheStore($dbo);
//		$cacheStore->setDataTableName(self::TABLE_PREFIX . $tableName . self::TABLE_DATA_SUFFIX);
//		$cacheStore->setCharacteristicTableName(self::TABLE_PREFIX . $tableName
//				. self::TABLE_CHARACTERISTICS_SUFFIX);
//		$weekReferences[$tableName] = new WeakReference($cacheStore);
//		return $cacheStore;
//	}
//
//	public function clear(): void {
//		$this->clearDbo($this->dbo);
//		if ($this->dbo !== $this->sharedDbo) {
//			$this->clearDbo($this->sharedDbo);
//		}
//	}
//
//	private function clearDbo(Dbo $dbo): void {
//		foreach ($this->dbo->createMetaManager()->createDatabase()->getMetaEntities() as $metaEntity) {
//			$tableName = $metaEntity->getName();
//			if (StringUtils::startsWith(self::TABLE_PREFIX, $tableName)) {
//				ExUtils::try(fn () => $this->dbo->exec($this->dbo->createDeleteStatementBuilder()->setTable($tableName)->toSqlString()));
//			}
//		}
//	}
//}