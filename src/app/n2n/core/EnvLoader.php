<?php

namespace n2n\core;

use n2n\util\io\fs\FsPath;
use n2n\util\io\IoUtils;

class EnvLoader {

	/**
	 * @param FsPath $envFileFsPath
	 * @param bool $putEnv
	 * @return void
	 * @throws \n2n\util\io\IoException
	 */
	static function load(FsPath $envFileFsPath, bool $putEnv = false): void {
		foreach (IoUtils::parseIniFile($envFileFsPath) as $key => $value) {
			$_SERVER[$key] = $value;
			$_ENV[$key] = $value;
			if ($putEnv) {
				putenv($key . '=' . $value);
			}
		}
	}
}