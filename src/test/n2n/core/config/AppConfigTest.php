<?php

namespace n2n\core\config;

use PHPUnit\Framework\TestCase;

class AppConfigTest extends TestCase {


	function testDefaultConstruct() {
		$appConfig = new AppConfig();
		$this->assertEquals(GeneralConfig::PAGE_NAME_DEFAULT, $appConfig->general()->getPageName());
	}
}