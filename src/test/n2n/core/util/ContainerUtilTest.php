<?php

namespace n2n\core\util;

use PHPUnit\Framework\TestCase;
use n2n\core\container\N2nContext;
use n2n\core\container\TransactionManager;
use n2n\core\container\mock\TransactionalResourceMock;
use n2n\core\container\TransactionPhase;

class ContainerUtilTest extends TestCase {

	private TransactionManager $transactionManager;
	private ContainerUtil $containerUtil;

	function setUp(): void {
		$this->transactionManager = new TransactionManager();

		$n2nContextMock = $this->createMock(N2nContext::class);
		$n2nContextMock->method('getTransactionManager')
				->willReturn($this->transactionManager);
		$this->containerUtil = new ContainerUtil($n2nContextMock);
	}

	function testOutsideTransaction(): void {
		$tr = new TransactionalResourceMock();

		$this->transactionManager->registerResource($tr);

		$called = 0;
		$this->containerUtil->outsideTransaction(function () use (&$called) {
			$called++;
		});

		$this->assertEquals(1, $called);

		$tx = $this->transactionManager->createTransaction();
		$this->assertEquals(1, $called);
		$tx->commit();

		$this->assertEquals(1, $called);

		$tx = $this->transactionManager->createTransaction();
		$this->assertEquals(1, $called);
		$tx->rollBack();
	}

	function testOutsideTransaction2(): void {
		$tr = new TransactionalResourceMock();

		$this->transactionManager->registerResource($tr);
		$called = 0;

		$tx = $this->transactionManager->createTransaction();
		$this->containerUtil->outsideTransaction(function () use (&$called) {
			$called++;
		});
		$this->assertEquals(0, $called);
		$tx->commit();

		$this->assertEquals(1, $called);

		$tx = $this->transactionManager->createTransaction();
		$this->assertEquals(1, $called);
		$this->containerUtil->outsideTransaction(function () use (&$called) {
			$called++;
		});
		$this->assertEquals(1, $called);
		$tx->rollBack();

		$this->assertEquals(2, $called);
	}

	function testPostPrepareAfterExtend() {

		$tr = new TransactionalResourceMock();
		$this->transactionManager->registerResource($tr);

		$firstPostPrepareCalled = false;
		$secondPostPrepareCalled = false;
		$postPrepareCalled = false;

		$tx = $this->transactionManager->createTransaction();

		$this->containerUtil->postPrepare(function () use (&$firstPostPrepareCalled, &$secondPostPrepareCalled, &$postPrepareCalled) {
			$this->assertTrue($firstPostPrepareCalled);
			$this->assertTrue($secondPostPrepareCalled);
			$this->assertFalse($postPrepareCalled);
			$postPrepareCalled = true;
		});

		$this->containerUtil->postPrepareAndExtend(function () use (&$firstPostPrepareCalled, &$secondPostPrepareCalled) {
			$this->assertFalse($firstPostPrepareCalled);
			$firstPostPrepareCalled = true;

			$this->containerUtil->postPrepareAndExtend(function () use (&$secondPostPrepareCalled) {
				$this->assertFalse($secondPostPrepareCalled);
				$secondPostPrepareCalled = true;
			});
		});

		$tx->commit();

		$this->assertTrue($firstPostPrepareCalled);
		$this->assertTrue($secondPostPrepareCalled);
		$this->assertTrue($postPrepareCalled);

		$this->assertEquals('beginTransaction', $tr->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr->callMethods[1]);
		$this->assertEquals('prepareCommit', $tr->callMethods[2]);
		$this->assertEquals('prepareCommit', $tr->callMethods[3]);
		$this->assertEquals('requestCommit', $tr->callMethods[4]);
		$this->assertEquals('commit', $tr->callMethods[5]);

	}

}