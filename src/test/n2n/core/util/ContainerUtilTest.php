<?php

namespace n2n\core\util;

use PHPUnit\Framework\TestCase;
use n2n\core\container\N2nContext;
use n2n\core\container\TransactionManager;
use n2n\core\container\mock\TransactionalResourceMock;
use n2n\core\container\TransactionPhase;
use n2n\core\container\err\CommitPreparationFailedException;
use n2n\core\container\err\TransactionalProcessFailedException;

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
		$this->assertEquals('requestCommit', $tr->callMethods[3]);
		$this->assertEquals('commit', $tr->callMethods[4]);

	}

	function testExecIsolatedSuccess(): void {
		$tr = new TransactionalResourceMock();
		$this->transactionManager->registerResource($tr);

		$callsNum = 0;
		$this->containerUtil->execIsolated(
				function () use (&$callsNum) {
					$callsNum++;
				}, 3);

		$this->assertEquals(1, $callsNum);
		$this->assertCount(4, $tr->callMethods);
	}

	function testExecIsolatedSuccessOnThird(): void {
		$tr = new TransactionalResourceMock();
		$this->transactionManager->registerResource($tr);

		$callsNum = 0;
		$this->containerUtil->execIsolated(
				function () use (&$callsNum, $tr) {
					$callsNum++;
					$this->assertTrue($this->transactionManager->hasOpenTransaction());
					$this->assertTrue($this->transactionManager->isReadyOnly());
					if ($callsNum < 3) {
						$tr->prepareOnce = fn () => throw new CommitPreparationFailedException(deadlock: true);
					}
				}, 3, fn () => $this->fail('should not be called'), true);

		$this->assertEquals(3, $callsNum);
		$this->assertCount(10, $tr->callMethods);
		$this->assertEquals('beginTransaction', $tr->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr->callMethods[1]);
		$this->assertEquals('rollBack', $tr->callMethods[2]);
		$this->assertEquals('beginTransaction', $tr->callMethods[3]);
		$this->assertEquals('prepareCommit', $tr->callMethods[4]);
		$this->assertEquals('rollBack', $tr->callMethods[5]);
		$this->assertEquals('beginTransaction', $tr->callMethods[6]);
		$this->assertEquals('prepareCommit', $tr->callMethods[7]);
		$this->assertEquals('requestCommit', $tr->callMethods[8]);
		$this->assertEquals('commit', $tr->callMethods[9]);
	}

	function testExecIsolatedTransactionFail(): void {
		$tr = new TransactionalResourceMock();
		$this->transactionManager->registerResource($tr);

		$callsNum = 0;
		try {
			$this->containerUtil->execIsolated(
					function() use (&$callsNum, $tr) {
						$callsNum++;
						$this->assertTrue($this->transactionManager->hasOpenTransaction());
						$this->assertFalse($this->transactionManager->isReadyOnly());
						$tr->prepareOnce = fn() => throw new CommitPreparationFailedException(deadlock: true);
					}, 3);
			$this->fail('exception expected');
		} catch (TransactionalProcessFailedException $e) {
		}

		$this->assertEquals(3, $callsNum);
		$this->assertCount(9, $tr->callMethods);
		$this->assertEquals('beginTransaction', $tr->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr->callMethods[1]);
		$this->assertEquals('rollBack', $tr->callMethods[2]);
		$this->assertEquals('beginTransaction', $tr->callMethods[3]);
		$this->assertEquals('prepareCommit', $tr->callMethods[4]);
		$this->assertEquals('rollBack', $tr->callMethods[5]);
		$this->assertEquals('beginTransaction', $tr->callMethods[6]);
		$this->assertEquals('prepareCommit', $tr->callMethods[7]);
		$this->assertEquals('rollBack', $tr->callMethods[8]);
	}

	function testSExecIsolatedFailWithDeadlockHandle(): void {
		$tr = new TransactionalResourceMock();
		$this->transactionManager->registerResource($tr);

		$callsNum = 0;
		$handleCallsNum = 0;
		$this->containerUtil->execIsolated(
				function() use (&$callsNum, $tr) {
					$callsNum++;
					$tr->prepareOnce = fn() => throw new CommitPreparationFailedException(deadlock: true);
				}, 2, function () use (&$handleCallsNum) {
					$handleCallsNum++;
				});

		$this->assertEquals(2, $callsNum);
		$this->assertCount(6, $tr->callMethods);
		$this->assertEquals('beginTransaction', $tr->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr->callMethods[1]);
		$this->assertEquals('rollBack', $tr->callMethods[2]);
		$this->assertEquals('beginTransaction', $tr->callMethods[3]);
		$this->assertEquals('prepareCommit', $tr->callMethods[4]);
		$this->assertEquals('rollBack', $tr->callMethods[5]);;
		$this->assertEquals(1, $handleCallsNum);
	}

}