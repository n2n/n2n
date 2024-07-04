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

use n2n\util\ex\IllegalStateException;
use n2n\core\container\err\CommitFailedException;
use n2n\core\container\err\CommitRequestFailedException;
use n2n\core\container\err\RollbackFailedException;
use n2n\core\container\err\CommitPreparationFailedException;

/**
 * Each TransactionalResource represents a participant in the two phase commit protocol
 * (@link https://en.wikipedia.org/wiki/Two-phase_commit_protocol).
 *
 * N2N adds additional prepare phase in which participants can settle the commit between them. This phase happens
 * in advance of the commit request (or voting) phase.
 */
interface TransactionalResource extends ReleasableResource {
	/**
	 * @param Transaction $transaction
	 */
	public function beginTransaction(Transaction $transaction): void;

	/**
	 * Will be called previous to {@link self::requestCommit()}. Contrary to prepareCommit() this method could
	 * be called multiple times. This happens when the prepare phase was extended
	 * ({@link TransactionManager::extendCommitPreparation() was called or {@link self::prepareCommit()} of
	 * any TransactionalResource returned false).
	 *
	 *
	 * @param Transaction $transaction
	 * @return void
	 * @throws CommitPreparationFailedException|\Throwable causes the abort of the commit but the transaction will remain open.
	 */
	public function prepareCommit(Transaction $transaction): void;
	
	/**
	 * Phase 1 (voting phase) of the two-phase commit protocol.
	 *
	 * @param Transaction $transaction
	 * @throws CommitRequestFailedException|\Throwable equivalent of voting no, causes the transaction to roll back and close.
	 */
	public function requestCommit(Transaction $transaction): void;
	
	/**
	 * Phase 2 of the two-phase commit protocol when all TransactionalResources voted yes on Phase 1.
	 *
	 * @param Transaction $transaction
	 * @throws CommitFailedException|\Throwable causes the transaction to enter a corrupted state.
	 */	
	public function commit(Transaction $transaction): void;
	
	/**
	 * Phase 2 of the two-phase commit protocol when some TransactionalResources voted no on Phase 1.
	 *
	 * @param Transaction $transaction
	 * @throws RollbackFailedException|\Throwable causes the transaction to enter a corrupted state.
	 */
	public function rollBack(Transaction $transaction): void;

}
