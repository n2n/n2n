<?php

namespace n2n\core;

use n2n\util\io\fs\FsPath;
use PHPUnit\Framework\TestCase;
use n2n\core\container\N2nContext;

class N2NTest extends TestCase {

	function testSetup(): void {
		$n2nApplication = N2N::setup(new FsPath(__DIR__ . '/test/public'), new FsPath(__DIR__ . '/test/var'));
		$n2nContext = $n2nApplication->createN2nContext();

		$this->assertInstanceOf(N2nContext::class, $n2nContext);
	}

}