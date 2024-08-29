<?php

namespace n2n\core\cache\impl;

use n2n\core\cache\AppCache;
use n2n\core\VarStore;
use n2n\cache\impl\fs\FileCacheStore;
use n2n\cache\CacheStore;
use n2n\util\io\fs\FsPath;

class FileAppCache implements AppCache {
	private $dirPerm;
	private $filePerm;

	public function __construct(private FsPath $dirFsPath, private FsPath $sharedDirFsPath, ?string $dirPerm, ?string $filePerm) {
		$this->dirPerm = $dirPerm;
		$this->filePerm = $filePerm;
	}

	private function determineDirFsPath(bool $shared): FsPath {
		if (!$shared) {
			return $this->dirFsPath;
		}

		return $this->sharedDirFsPath;
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\core\cache\AppCache::lookupCacheStore($namespace)
	 */
	public function lookupCacheStore(string $namespace, bool $shared = true): CacheStore {
		$dirFsPath = $this->determineDirFsPath($shared)->ext(VarStore::namespaceToDirName($namespace));
		if (!$dirFsPath->isDir()) {
			$dirFsPath->mkdirs($this->dirPerm);
			if ($this->dirPerm !== null) {
				// chmod after mkdirs because of possible umask restrictions.
				$dirFsPath->chmod($this->dirPerm);
			}
		}

		return new FileCacheStore($dirFsPath, $this->dirPerm, $this->filePerm);
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\core\cache\AppCache::clear()
	 */
	public function clear(): void {
		$this->dirFsPath->delete();
		$this->sharedDirFsPath->delete();
	}
}