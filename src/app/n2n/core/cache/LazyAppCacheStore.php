<?php

namespace n2n\core\cache;

use n2n\cache\CacheStore;
use n2n\cache\CacheItem;

class LazyAppCacheStore implements CacheStore {

	private CacheStore $decoratedCache;

	function __construct(private AppCache $appCache, private string $namespace, private bool $shared) {

	}

	private function cacheStore(): CacheStore {
		return $this->decoratedCache
				?? $this->decoratedCache = $this->appCache->lookupCacheStore($this->namespace, $this->shared);
	}

	public function store(string $name, array $characteristics, mixed $data, ?\DateInterval $ttl = null,
			?\DateTimeInterface $now = null): void {
		$this->cacheStore()->store($name, $characteristics, $data, $ttl, $now);
	}

	public function get(string $name, array $characteristics, ?\DateTimeInterface $now = null): ?CacheItem {
		return $this->cacheStore()->get($name, $characteristics, $now);
	}

	public function remove(string $name, array $characteristics): void {
		$this->cacheStore()->remove($name, $characteristics);
	}

	public function findAll(string $name, ?array $characteristicNeedles = null, ?\DateTimeInterface $now = null): array {
		return $this->cacheStore()->findAll($name, $characteristicNeedles, $now);
	}

	public function removeAll(?string $name, ?array $characteristicNeedles = null): void {
		$this->cacheStore()->removeAll($name, $characteristicNeedles);
	}

	public function garbageCollect(?\DateInterval $maxLifetime = null, ?\DateTimeInterface $now = null): void {
		$this->cacheStore()->garbageCollect($maxLifetime, $now);
	}

	public function clear(): void {
		$this->cacheStore()->clear();
	}
}