<?php

namespace n2n\core\container;

class ClosureCommitListener implements CommitListener {

	function __construct(private ?\Closure $preCommitCallback = null,
			private ?\Closure $postCommitCallback = null, private ?\Closure $commitFailedCallback = null) {

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
	public function getCommitFailedCallback(): ?\Closure {
		return $this->commitFailedCallback;
	}

	/**
	 * @param \Closure|null $commitFailedCallback
	 */
	public function setCommitFailedCallback(?\Closure $commitFailedCallback): void {
		$this->commitFailedCallback = $commitFailedCallback;
	}


	/**
	 * @inheritDoc
	 */
	public function preCommit(Transaction $transaction) {
		if ($this->preCommitCallback !== null) {
			($this->preCommitCallback)($transaction);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function postCommit(Transaction $transaction) {
		if ($this->postCommitCallback !== null) {
			($this->postCommitCallback)($transaction);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function commitFailed(Transaction $transaction, CommitFailedException $e) {
		if ($this->commitFailedCallback !== null) {
			($this->commitFailedCallback)($transaction);
		}
	}
}