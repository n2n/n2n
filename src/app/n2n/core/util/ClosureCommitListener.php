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
namespace n2n\core\util;

use n2n\core\container\CommitFailedException;
use n2n\core\container\Transaction;
use n2n\core\container\CommitListener;

class ClosureCommitListener implements CommitListener {

	function __construct(private ?\Closure $prePrepareCallback = null, private ?\Closure $preCommitCallback = null,
			private ?\Closure $postCommitCallback = null, private ?\Closure $commitFailedCallback = null,
			private ?\Closure $preRollbackCallback = null, private ?\Closure $postRollbackCallback = null,
			private ?\Closure $finallyCallback = null) {

	}

	public function getPrePrepareCallback(): ?\Closure {
		return $this->prePrepareCallback;
	}

	/**
	 * @param \Closure|null $prePrepareCallback
	 */
	public function setPrePrepareCallback(?\Closure $prePrepareCallback): void {
		$this->prePrepareCallback = $prePrepareCallback;
	}

	/**
	 * @return \Closure|null
	 */
	public function getPreCommitCallback(): ?\Closure {
		return $this->preCommitCallback;
	}

	/**
	 * @param \Closure|null $preCommitCallback
	 */
	public function setPreCommitCallback(?\Closure $preCommitCallback): void {
		$this->preCommitCallback = $preCommitCallback;
	}

	/**
	 * @return \Closure|null
	 */
	public function getPostCommitCallback(): ?\Closure {
		return $this->postCommitCallback;
	}

	/**
	 * @param \Closure|null $postCommitCallback
	 */
	public function setPostCommitCallback(?\Closure $postCommitCallback): void {
		$this->postCommitCallback = $postCommitCallback;
	}


	/**
	 * @return \Closure|null
	 */
	public function getPreRollbackCallback(): ?\Closure {
		return $this->preRollbackCallback;
	}

	/**
	 * @param \Closure|null $preRollbackCallback
	 */
	public function setPreRollbackCallback(?\Closure $preRollbackCallback): void {
		$this->preRollbackCallback = $preRollbackCallback;
	}

	/**
	 * @return \Closure|null
	 */
	public function getPostRollbackCallback(): ?\Closure {
		return $this->postRollbackCallback;
	}

	/**
	 * @param \Closure|null $postRollbackCallback
	 */
	public function setPostRollbackCallback(?\Closure $postRollbackCallback): void {
		$this->postRollbackCallback = $postRollbackCallback;
	}

	/**
	 * @return \Closure|null
	 */
	public function getCommitFailedCallback(): ?\Closure {
		return $this->commitFailedCallback;
	}

	/**
	 * @param \Closure|null $commitFailedCallback
	 */
	public function setCommitFailedCallback(?\Closure $commitFailedCallback): void {
		$this->commitFailedCallback = $commitFailedCallback;
	}

	public function getFinallyCallback(): ?\Closure {
		return $this->finallyCallback;
	}

	/**
	 * @param \Closure|null $finallyCallback
	 */
	public function setFinallyCallback(?\Closure $finallyCallback): void {
		$this->finallyCallback = $finallyCallback;
	}

	private function callFinally(Transaction $transaction) {
		if ($this->finallyCallback !== null) {
			($this->finallyCallback)($transaction);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function prePrepare(Transaction $transaction): void {
		if ($this->prePrepareCallback !== null) {
			($this->prePrepareCallback)($transaction);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function preCommit(Transaction $transaction): void {
		if ($this->preCommitCallback !== null) {
			($this->preCommitCallback)($transaction);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function postCommit(Transaction $transaction): void {
		if ($this->postCommitCallback !== null) {
			($this->postCommitCallback)($transaction);
		}

		$this->callFinally($transaction);
	}

	/**
	 * @inheritDoc
	 */
	public function commitFailed(Transaction $transaction, CommitFailedException $e): void {
		if ($this->commitFailedCallback !== null) {
			($this->commitFailedCallback)($transaction);
		}

		$this->callFinally($transaction);
	}

	/**
	 * @inheritDoc
	 */
	public function preRollback(Transaction $transaction): void {
		if ($this->preRollbackCallback !== null) {
			($this->preRollbackCallback)($transaction);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function postRollback(Transaction $transaction): void {
		if ($this->postRollbackCallback !== null) {
			($this->postRollbackCallback)($transaction);
		}

		$this->callFinally($transaction);
	}
}