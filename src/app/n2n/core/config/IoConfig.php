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

use n2n\util\type\ArgUtils;

class IoConfig {
    const PUBLIC_DIR_PERMISSION_DEFAULT = '0700';
    const PUBLIC_FILE_PERMISSION_DEFAULT = '0600';
    const PRIVATE_DIR_PERMISSION_DEFAULT = '0700';
    const PRIVATE_FILE_PERMISSION_DEFAULT = '0600';

	public function __construct(private string $publicDirPermission = self::PUBLIC_DIR_PERMISSION_DEFAULT,
			private string $publicFilePermission = self::PUBLIC_FILE_PERMISSION_DEFAULT,
			private string $privateDirPermission = self::PRIVATE_DIR_PERMISSION_DEFAULT,
			private string $privateFilePermission = self::PRIVATE_FILE_PERMISSION_DEFAULT) {
        ArgUtils::assertTrue(preg_match('/^[0][0-7]{3}$/', $this->publicDirPermission) !== false,
                'Use the 4 digit dir permission style, default = 0700');
        ArgUtils::assertTrue(preg_match('/^[0][0-7]{3}$/', $this->publicFilePermission) !== false,
                'Use the 4 digit file permission style, default = 0600');
        ArgUtils::assertTrue(preg_match('/^[0][0-7]{3}$/', $this->privateDirPermission) !== false,
                'Use the 4 digit dir permission style, default = 0700');
        ArgUtils::assertTrue(preg_match('/^[0][0-7]{3}$/', $this->privateFilePermission) !== false,
                'Use the 4 digit file permission style, default = 0600');
	}

	public function getPublicDirPermission(): string {
		return $this->publicDirPermission;
	}

	public function getPublicFilePermission(): string {
		return $this->publicFilePermission;
	}

	public function getPrivateDirPermission(): string {
		return $this->privateDirPermission;
	}

	public function getPrivateFilePermission(): string {
		return $this->privateFilePermission;
	}
}
