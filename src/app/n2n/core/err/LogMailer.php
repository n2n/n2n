<?php
namespace n2n\core\err;

interface LogMailer {

	/**
	 * @param string $to
	 * @param string $from
	 * @param string $subject
	 * @param string $detailMessage
	 * @return void
	 */
	function sendLogMail(string $from, string $to, string $subject, string $detailMessage): void;
}