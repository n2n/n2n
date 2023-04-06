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
namespace n2n\core\config\build;

use PHPUnit\Framework\TestCase;
use n2n\util\io\fs\FsPath;
use n2n\config\source\impl\IniFileConfigSource;
use n2n\util\type\ArgUtils;
use n2n\core\N2N;
use n2n\core\config\AppConfig;

class AppConfigFactoryTest extends TestCase {

	private function createFromFsPath(string $iniFileName, array $additionalIniFileNames = [],
			string $stage = N2N::STAGE_LIVE): AppConfig {
		ArgUtils::valArray($additionalIniFileNames, 'string');

		$source = new CombinedConfigSource(new IniFileConfigSource($this->determineFsPath($iniFileName)));
		foreach ($additionalIniFileNames as $key => $additionalIniFileName) {
			$source->putAdditional($key, new IniFileConfigSource($this->determineFsPath($additionalIniFileName)));
		}

		$appConfigFactory = new AppConfigFactory(new FsPath('public'));
		return $appConfigFactory->create($source, $stage);
	}

	private function determineFsPath(string $iniFileName): FsPath {
		return (new FsPath(__DIR__))->getParent()->ext(['mock', 'ini', $iniFileName]);
	}

	function testGeneral() {
		$appConfig = $this->createFromFsPath('general.app.ini');
		$this->assertTrue($appConfig->general()->isApplicationReplicatable());
	}

	function testRouting() {
		$appConfig = $this->createFromFsPath('routing.app.ini');

		$routingConfig = $appConfig->routing();
		$this->assertCount(2, $routingConfig->getMainControllerDefs());
		$this->assertCount(1, $routingConfig->getN2nLocales());

		$this->assertCount(1, $routingConfig->getRoutingRules());

		$this->assertCount(2, $appConfig->web()->getAllN2nLocales());


	}

}