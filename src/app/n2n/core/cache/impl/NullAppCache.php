<?php
//
//namespace n2n\core\cache\impl;
//
//use n2n\core\cache\AppCache;
//use n2n\cache\CacheStore;
//use n2n\cache\impl\ephemeral\EphemeralCacheStore;
//
//class NullAppCache implements AppCache {
//
//
//	public function lookupCacheStore(string $namespace, bool $shared = true): CacheStore {
//		return new EphemeralCacheStore();
//	}
//
//	public function clear(): void {
//	}
//}