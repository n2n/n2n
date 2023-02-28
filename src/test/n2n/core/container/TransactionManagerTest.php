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

class TransactionManagerTest extends TestCase {


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
		$this->assertEquals($tm->getPhase(), TransactionPhase::CLOSED);

		$tx = $tm->createTransaction();

		$this->assertTrue($tm->hasOpenTransaction());
		$this->assertEquals($tm->getPhase(), TransactionPhase::OPEN);

		$this->assertCount(1, $tr->callMethods);
		$this->assertEquals('beginTransaction', $tr->callMethods[0]);


		$this->assertCount(1, $tr2->callMethods);
		$this->assertEquals('beginTransaction', $tr2->callMethods[0]);

		$this->assertEquals(0, $tm->getCommitPreparationsNum());

		$tx->commit();

		$this->assertCount(4, $tr->callMethods);
		$this->assertEquals('beginTransaction', $tr->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr->callMethods[1]);
		$this->assertEquals('prepareCommit', $tr->callMethods[2]);
		$this->assertEquals('commit', $tr->callMethods[3]);

		$this->assertCount(3, $tr2->callMethods);
		$this->assertEquals('beginTransaction', $tr2->callMethods[0]);
		$this->assertEquals('prepareCommit', $tr2->callMethods[1]);
		$this->assertEquals('commit', $tr2->callMethods[2]);
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

		$this->expectException(TransactionStateException::class);
		$tx->commit();

	}

}