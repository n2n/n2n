<?php

namespace n2n\core\err;

use PHPUnit\Framework\TestCase;
use n2n\util\ex\err\impl\FatalError;

class ExceptionHandlerTest extends TestCase {

	function testTriggerError() {
		$exceptionHandler = new ExceptionHandler(true);
		$exceptionHandler->setStrictAttitude(true);

		$this->expectException(FatalError::class);
		$this->expectExceptionCode(E_USER_ERROR);
		$this->expectExceptionMessage('Packets out of order. Expected 1 received 0. Packet size=145');

		try {
			trigger_error('Packets out of order. Expected 1 received 0. Packet size=145', E_USER_ERROR);
		} finally {
			$exceptionHandler->unregister();
		}
	}

	function testIgnoreTriggeredErrors() {
		$exceptionHandler = new ExceptionHandler(true);
		$exceptionHandler->setStrictAttitude(true);

		try {
			trigger_error('Packets out of order. Expected 1 received 0. Packet size=145', E_USER_WARNING);
			trigger_error('PDO::__construct(): SSL: Broken pipe', E_USER_WARNING);
			// no throwable => success
			$this->assertTrue(true);
		} finally {
			$exceptionHandler->unregister();
		}
	}

}