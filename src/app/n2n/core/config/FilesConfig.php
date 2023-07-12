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
namespace n2n\core\config;

use n2n\util\io\fs\FsPath;
use n2n\util\uri\Url;

class FilesConfig {
    const ASSETS_DIR_DEFAULT = 'assets';
    const ASSETS_URL_DEFAULT = 'assets';
    const MANAGER_PUBLIC_DIR_DEFAULT = 'files';
    const MANAGER_PUBLIC_URL_DEFAULT = 'files';

    private FsPath $assetsDir;
	private Url $assetsUrl;
	private FsPath $managerPublicDir;
	private Url $managerPublicUrl;
	private ?FsPath $managerPrivateDir;
	
	public function __construct(FsPath|string $assetsDir = self::ASSETS_DIR_DEFAULT,
            Url|string $assetsUrl = self::ASSETS_URL_DEFAULT,
            FsPath|string $managerPublicDir = self::MANAGER_PUBLIC_DIR_DEFAULT,
            Url|string $managerPublicUrl = self::MANAGER_PUBLIC_URL_DEFAULT,
			FsPath|string $managerPrivateDir = null) {

		$this->assetsDir = FsPath::create($assetsDir);
		$this->assetsUrl = Url::create($assetsUrl);
		$this->managerPublicDir = FsPath::create($managerPublicDir);
		$this->managerPublicUrl = Url::create($managerPublicUrl);
		$this->managerPrivateDir = ($managerPrivateDir === null ? null : FsPath::create($managerPrivateDir));
	}

	/**
	 * @return string
	 */
	public function getAssetsDir(): FsPath {
		return $this->assetsDir;
	}

    /**
     * @return Url
     */
    public function getAssetsUrl(): Url {
		return $this->assetsUrl;
	}
	
	/**
	 * @return FsPath
	 */
	public function getManagerPublicDir(): FsPath {
		return $this->managerPublicDir;
	}

	/**
	 * @return Url
	 */
	public function getManagerPublicUrl(): Url {
		return $this->managerPublicUrl;
	}

	/**
	 * @return FsPath|null
	 */
	public function getManagerPrivateDir(): ?FsPath {
		return $this->managerPrivateDir;
	}
}
