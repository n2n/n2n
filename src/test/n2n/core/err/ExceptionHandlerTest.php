<?php

namespace n2n\core\err;

use PHPUnit\Framework\TestCase;
use n2n\util\ex\err\impl\FatalError;
use n2n\util\ex\err\impl\WarningError;
use n2n\util\ex\err\TriggeredError;
use n2n\util\ex\err\impl\NoticeError;

class ExceptionHandlerTest extends TestCase {
	/**
	 * @param $exceptionHandler
	 * @param $errorHandlerCalled
	 * @return void
	 *
	 * Only here to suppress the stackTrace of the Warning
	 */
	function setCustomErrorHandler($exceptionHandler, &$errorHandlerCalled): void {
		$previousHandler = set_error_handler(null);
		restore_error_handler();
		set_error_handler(function($errno, $errstr, $errfile, $errline) use ($exceptionHandler, &$errorHandlerCalled, $previousHandler) {
			if ($errno === E_USER_WARNING && $errstr === 'Test Warning') {
				if ($exceptionHandler->isStrictAttitude()) {
					if ($previousHandler) {
						return call_user_func($previousHandler, $errno, $errstr, $errfile, $errline); // handle it with previous/default handler
					}
					return false; // Let the default handler handle this error
				} else {
					$errorHandlerCalled = true;
					return true; // Suppress the warning
				}
			}
			return false; // Let the default handler handle other errors
		});
	}
	function testTriggeredWarningStrict() {
		$exceptionHandler = new ExceptionHandler(true);
		$exceptionHandler->setStrictAttitude(true);
		$errorHandlerCalled = false;
		// Custom error handler to convert warnings to exceptions
		$this->setCustomErrorHandler($exceptionHandler, $errorHandlerCalled);

		try {
			trigger_error('Test Warning', E_USER_WARNING);
			// if throwable was triggered => was a success
			$this->fail('we expected that Warning was changed into Exception, this was not true');
		} catch (WarningError $e) {
			$this->assertTrue(true);
			$errorHandlerCalled = true;
		} finally {
			restore_error_handler(); // Restore the original error handler
			$exceptionHandler->unregister();
		}
		$this->assertTrue($errorHandlerCalled);
	}
	function testTriggeredWarningNoStrict() {
		$exceptionHandler = new ExceptionHandler(true);
		$exceptionHandler->setStrictAttitude(false);
		$errorHandlerCalled = false;
		// Custom error handler to convert warnings to exceptions
		$this->setCustomErrorHandler($exceptionHandler, $errorHandlerCalled);

		try {
			trigger_error('Test Warning', E_USER_WARNING);
			// if no throwable was triggered => it was a success
			$this->assertTrue(true);
		} catch (TriggeredError $e) {
			$this->fail('we expected that Warning was not changed into Exception, this was not true');
		} finally {
			restore_error_handler(); // Restore the original error handler
			$exceptionHandler->unregister();
		}
		$this->assertTrue($errorHandlerCalled);
	}

	function testTriggerError() {
		$exceptionHandler = new ExceptionHandler(true);
		$exceptionHandler->setStrictAttitude(true);

		$this->expectException(NoticeError::class);
		$this->expectExceptionCode(E_USER_NOTICE);
		$this->expectExceptionMessage('Packets out of order. Expected 1 received 0. Packet size=145');

		try {
			trigger_error('Packets out of order. Expected 1 received 0. Packet size=145', E_USER_NOTICE);
		} finally {
			$exceptionHandler->unregister();
		}
	}

	function testIgnoreTriggeredWarnings() {
		$exceptionHandler = new ExceptionHandler(true);
		$exceptionHandler->setStrictAttitude(true);
		//this both warnings will be ignored
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