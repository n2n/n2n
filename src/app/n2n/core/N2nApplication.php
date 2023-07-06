<?php

namespace n2n\core;

use n2n\core\module\ModuleManager;
use n2n\util\io\fs\FsPath;

class N2nApplication {

	function __construct(private VarStore $varStore, private ModuleManager $moduleManager,
			private ?FsPath $publicFsPath) {

	}

	function getVarStore(): VarStore {
		return $this->varStore;
	}

	function getModuleManager(): ModuleManager {
		return $this->moduleManager;
	}

	function getPublicFsPath(): ?FsPath {
		return $this->publicFsPath;
	}
}