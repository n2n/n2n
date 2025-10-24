<?php

namespace n2n\core\cache;

use n2n\cache\CacheStore;
use n2n\cache\CacheItem;
use n2n\cache\CharacteristicsList;

class LazyAppCacheStore implements CacheStore {

	private CacheStore $decoratedCache;

	function __construct(private AppCache $appCache, private string $namespace, private bool $shared) {

	}

	private function cacheStore(): CacheStore {
		return $this->decoratedCache
				?? $this->decoratedCache = $this->appCache->lookupCacheStore($this->namespace, $this->shared);
	}

	public function store(string $name, CharacteristicsList $characteristicsList, mixed $data, ?\DateInterval $ttl = null,
			?\DateTimeInterface $now = null): void {
		$this->cacheStore()->store($name, $characteristicsList, $data, $ttl, $now);
	}

	public function get(string $name, CharacteristicsList $characteristicsList, ?\DateTimeInterface $now = null): ?CacheItem {
		return $this->cacheStore()->get($name, $characteristicsList, $now);
	}

	public function remove(string $name, CharacteristicsList $characteristicsList): void {
		$this->cacheStore()->remove($name, $characteristicsList);
	}

	public function findAll(string $name, ?CharacteristicsList $characteristicNeedlesList = null, ?\DateTimeInterface $now = null): array {
		return $this->cacheStore()->findAll($name, $characteristicNeedlesList, $now);
	}

	public function removeAll(?string $name, ?CharacteristicsList $characteristicNeedlesList = null): void {
		$this->cacheStore()->removeAll($name, $characteristicNeedlesList);
	}

	public function garbageCollect(?\DateInterval $maxLifetime = null, ?\DateTimeInterface $now = null): void {
		$this->cacheStore()->garbageCollect($maxLifetime, $now);
	}

	public function clear(): void {
		$this->cacheStore()->clear();
	}
}