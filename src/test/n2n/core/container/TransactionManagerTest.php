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
namespace n2n\core\container;

use PHPUnit\Framework\TestCase;
use n2n\core\container\mock\TransactionalResourceMock;
use n2n\util\StringUtils;
use n2n\util\ex\IllegalStateException;
use n2n\core\container\err\CommitFailedException;
use n2n\core\container\err\CommitRequestFailedException;
use n2n\core\container\err\RollbackFailedException;
use n2n\core\container\err\TransactionStateException;
use n2n\core\container\err\UnexpectedRollbackException;
use n2n\core\container\mock\CommitListenerMock;

class TransactionManagerTest extends TestCase {

	function testRollback() {
		$tr = new TransactionalResourceMock();
		$tr2 = new TransactionalResourceMock();

		$tm = new TransactionManager();
		$tm->registerResource($tr);
		$tm->registerResource($tr2);

		$tx = $tm->createTransaction();

		$tx->rollBack();

		$this->assertCount(2, $tr->callMethods);
		$this->assertEquals('beginTransaction', $tr->callMethods[0]);
		$this->assertEquals('rollBack', $tr->callMethods[1]);

		$this->assertCount(2, $tr2->callMethods);
		$this->assertEquals('beginTransaction', $tr2->callMethods[0]);
		$this->assertEquals('rollBack', $tr2->callMethods[1]);

		$this->assertEquals(TransactionPhase::CLOSED, $tm->getPhase());
	}


	function testSomething() {
		$tr = new TransactionalResourceMock();
		$tr2 = new TransactionalResourceMock();

		$tm = new TransactionManager();
		$tm->registerResource($tr);
		$tm->registerResource($tr2);

		$tr->prepareOnce = function () use ($tm) {
			$tm->extendCommitPreparation();
			$this->assertEquals(1, $tm->getCommitPreparationsNum());
		};

		$this->assertFalse($tm->hasOpenTransaction());
		$this->assertEquals(TransactionPhase::CLOSED, $tm->getPhase());

		$tx = $tm->createTransaction();

		$this->assertTrue($tm->hasOpenTransaction());
		$this->assertEquals(TransactionPhase::OPEN, $tm->getPhase());

		$this->assertCount(1, $tr->callMethods);
		$this->assertEquals('beginTransaction', $tr->callMethods[0]);


		$this->assertCount(1, $tr2->callMethods);
		$this->assertEquals('beginTransaction', $tr2->callMethods[0]);

		$this->assertEquals(0, $tm->getCommitPreparationsNum());

		$tx->commit();

		$this->assertCount(5, $tr->callMethods);
		$this->assertEquals('beginTransaction', $tr->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr->callMethods[1]);
		$this->assertEquals('prepareCommit', $tr->callMethods[2]);
		$this->assertEquals('requestCommit', $tr->callMethods[3]);
		$this->assertEquals('commit', $tr->callMethods[4]);

		$this->assertCount(4, $tr2->callMethods);
		$this->assertEquals('beginTransaction', $tr2->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr2->callMethods[1]);
		$this->assertEquals('requestCommit', $tr2->callMethods[2]);
		$this->assertEquals('commit', $tr2->callMethods[3]);

		$this->assertEquals(TransactionPhase::CLOSED, $tm->getPhase());
	}


	function testExtendExceptionOnOpen() {
		$tr = new TransactionalResourceMock();

		$tm = new TransactionManager();
		$tm->registerResource($tr);

		$tm->createTransaction();

		$this->expectException(TransactionStateException::class);
		$tm->extendCommitPreparation();
	}

	function testExtendExceptionOnCommit() {
		$tr = new TransactionalResourceMock();

		$tm = new TransactionManager();
		$tm->registerResource($tr);

		$tx = $tm->createTransaction();


		$tr->commitOnce = function () use ($tm) {
			$tm->extendCommitPreparation();
		};

		try {
			$tx->commit();
			$this->fail('Exception expected.');
		} catch (TransactionStateException $e) {
			$previous = $e->getPrevious();
			$this->assertInstanceOf(CommitFailedException::class, $previous);
			$previous = $previous->getPrevious();
			$this->assertInstanceOf(TransactionStateException::class, $previous);
			$this->assertTrue(StringUtils::startsWith('Can not extend commit', $previous->getMessage()));
		}
	}

	function testRequestCommitFailure() {
		$tr = new TransactionalResourceMock();
		$tr2 = new TransactionalResourceMock();

		$tm = new TransactionManager();
		$tm->registerResource($tr);
		$tm->registerResource($tr2);

		$tr->requestCommitOnce = fn() => throw new IllegalStateException();

		$tx = $tm->createTransaction();

		try {
			$tx->commit();
			$this->fail('exeception expected');
		} catch (UnexpectedRollbackException $e) {
			$previous = $e->getPrevious();
			$this->assertInstanceOf(CommitRequestFailedException::class, $previous);
			$previous = $previous->getPrevious();
			$this->assertInstanceOf(IllegalStateException::class, $previous);
		}

		$this->assertCount(4, $tr->callMethods);
		$this->assertEquals('beginTransaction', $tr->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr->callMethods[1]);
		$this->assertEquals('requestCommit', $tr->callMethods[2]);
		$this->assertEquals('rollBack', $tr->callMethods[3]);

		$this->assertCount(3, $tr2->callMethods);
		$this->assertEquals('beginTransaction', $tr2->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr->callMethods[1]);
		$this->assertEquals('rollBack', $tr2->callMethods[2]);

		$tx = $tm->createTransaction();

		$this->assertCount(5, $tr->callMethods);
		$this->assertEquals('beginTransaction', $tr->callMethods[4]);

		$this->assertCount(4, $tr2->callMethods);
		$this->assertEquals('beginTransaction', $tr2->callMethods[3]);
	}

	function testCommitFailure() {
		$tr = new TransactionalResourceMock();
		$tr2 = new TransactionalResourceMock();

		$tm = new TransactionManager();
		$tm->registerResource($tr);
		$tm->registerResource($tr2);

		$tr2->commitOnce = fn() => throw new IllegalStateException();

		$tx = $tm->createTransaction();

		$this->expectException(TransactionStateException::class);
		try {
			$tx->commit();
			$this->fail('exception expected');
		} catch (TransactionStateException $e) {
			$this->assertInstanceOf(CommitFailedException::class, $e->getPrevious());
		}

		$this->assertCount(4, $tr->callMethods);
		$this->assertEquals('beginTransaction', $tr->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr->callMethods[1]);
		$this->assertEquals('requestCommit', $tr->callMethods[2]);
		$this->assertEquals('commit', $tr->callMethods[3]);

		$this->assertCount(4, $tr2->callMethods);
		$this->assertEquals('beginTransaction', $tr2->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr2->callMethods[1]);
		$this->assertEquals('requestCommit', $tr2->callMethods[2]);
		$this->assertEquals('commit', $tr2->callMethods[3]);

		$this->assertEquals(TransactionPhase::CORRUPTED_STATE, $tm->getPhase());

		$this->expectException(TransactionStateException::class);
		try {
			$tx = $tm->createTransaction();
		} finally {
			$this->assertCount(4, $tr->callMethods);
			$this->assertCount(4, $tr2->callMethods);
		}
	}

	function testUnexpectedRollbackFailure() {
		$tr = new TransactionalResourceMock();
		$tr2 = new TransactionalResourceMock();

		$tm = new TransactionManager();
		$tm->registerResource($tr);
		$tm->registerResource($tr2);

		$tr->requestCommitOnce = fn() => throw new IllegalStateException('commit fail mock ex');
		$tr2->rollbackOnce = fn() => throw new IllegalStateException('rollback fail mock ex');

		$tx = $tm->createTransaction();

		try {
			$tx->commit();
			$this->fail('exception expected.');
		} catch (TransactionStateException $e) {
			$this->assertTrue(StringUtils::contains('commit fail mock ex', $e->getMessage()));
			$this->assertInstanceOf(RollbackFailedException::class, $e->getPrevious());
		}

		$this->assertCount(4, $tr->callMethods);
		$this->assertEquals('beginTransaction', $tr->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr->callMethods[1]);
		$this->assertEquals('requestCommit', $tr->callMethods[2]);
		$this->assertEquals('rollBack', $tr->callMethods[3]);

		$this->assertCount(3, $tr2->callMethods);
		$this->assertEquals('beginTransaction', $tr2->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr2->callMethods[1]);
		$this->assertEquals('rollBack', $tr2->callMethods[2]);

		$this->assertEquals(TransactionPhase::CORRUPTED_STATE, $tm->getPhase());

		$this->expectException(TransactionStateException::class);
		try {
			$tm->createTransaction();
		} finally {
			$this->assertCount(4, $tr->callMethods);
			$this->assertCount(3, $tr2->callMethods);
		}
	}

	function testRollbackAfterPrepareFailure(): void {
		$tr = new TransactionalResourceMock();

		$tm = new TransactionManager();
		$tm->registerResource($tr);

		$tr->prepareOnce = fn() => throw new IllegalStateException('prepare fail mock ex');

		$tx = $tm->createTransaction();

		try {
			$tx->commit();
			$this->fail('exception expected.');
		} catch (IllegalStateException $e) {
			$this->assertTrue(StringUtils::contains('prepare fail mock ex', $e->getMessage()));
		}

		$this->assertTrue($tm->hasOpenTransaction());
		$tx->rollBack();

		$this->assertFalse($tm->hasOpenTransaction());
		$this->assertEquals(TransactionPhase::CLOSED, $tm->getPhase());
	}

	function testBeginFailure(): void {
		$tr = new TransactionalResourceMock();
		$tr2 = new TransactionalResourceMock();

		$tm = new TransactionManager();
		$tm->registerResource($tr);
		$tm->registerResource($tr2);

		$tr2->beginOnce = fn() => throw new IllegalStateException('begin fail mock ex');

		try {
			$tx = $tm->createTransaction();
			$this->fail('exception expected.');
		} catch (UnexpectedRollbackException $e) {
			$this->assertTrue(StringUtils::contains('begin fail mock ex', $e->getPrevious()->getMessage()));
		}

		$this->assertCount(2, $tr->callMethods);
		$this->assertEquals('beginTransaction', $tr->callMethods[0]);
		$this->assertEquals('rollBack', $tr->callMethods[1]);

		$this->assertCount(1, $tr2->callMethods);
		$this->assertEquals('beginTransaction', $tr2->callMethods[0]);

		$this->assertFalse($tm->hasOpenTransaction());
		$this->assertEquals(TransactionPhase::CLOSED, $tm->getPhase());


	}

	public function testPreCommitInterruption(): void {

		$tr = new TransactionalResourceMock();
		$cl = new CommitListenerMock();

		$cl->preCommitOnce = fn () => throw new IllegalStateException();

		$tm = new TransactionManager();
		$tm->registerResource($tr);
		$tm->registerCommitListener($cl);

		$tx = $tm->createTransaction();


		$this->expectException(TransactionStateException::class);
		try {
			$tx->commit();
		} finally {
			$this->assertEquals(TransactionPhase::OPEN, $tm->getPhase());
			$this->assertCount(3, $cl->callMethods);
			$this->assertEquals('prePrepare', $cl->callMethods[0]);
			$this->assertEquals('postPrepare', $cl->callMethods[1]);
			$this->assertEquals('preCommit', $cl->callMethods[2]);

			$this->assertCount(2, $tr->callMethods);
			$this->assertEquals('beginTransaction', $tr->callMethods[0]);
			$this->assertEquals('prepareCommit', $tr->callMethods[1]);
		}
	}

}