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
namespace n2n\core\config;

class ErrorConfig {
	const ERROR_VIEW_DEFAULT_KEY_SUFFIX = 'default';
    const STRICT_ATTITUDE_DEFAULT = true;
    const STARTUP_DETECT_ERRORS_DEFAULT = true;
    const STARTUP_DETECT_BAD_REQUESTS_DEFAULT = true;
    const LOG_SAVE_DETAIL_INFO_DEFAULT = true;
    const LOG_SEND_MAIL_DEFAULT = false;
    const LOG_HANDLE_HTTP_STATUS_EXCEPTIONS_DEFAULT = false;


	public function __construct(private bool $strictAttitude = self::STRICT_ATTITUDE_DEFAULT,
			private bool $startupDetectErrors = self::STARTUP_DETECT_ERRORS_DEFAULT,
			private bool $startupDetectBadRequests = self::STARTUP_DETECT_BAD_REQUESTS_DEFAULT,
			private bool $logSaveDetailInfo = self::LOG_SAVE_DETAIL_INFO_DEFAULT,
			private bool $logSendMail = self::LOG_SEND_MAIL_DEFAULT,
			private ?string $logMailRecipient = null,
			private bool $logHandleStatusExceptions = self::LOG_HANDLE_HTTP_STATUS_EXCEPTIONS_DEFAULT,
			private array $logExcludedHttpStatuses = [],
			private array $errorViewNames = [],
			private readonly ?float $monitorSlowQueryTime = null) {
	}


	public function isStrictAttitudeEnabled(): bool {
		return $this->strictAttitude;
	}


	public function isDetectStartupErrorsEnabled(): bool {
		return $this->startupDetectErrors;
	}


	public function isStartupDetectBadRequestsEnabled(): bool {
		return $this->startupDetectBadRequests;
	}


	public function isLogSaveDetailInfoEnabled(): bool {
		return $this->logSaveDetailInfo;
	}


	public function isLogSendMailEnabled(): bool {
		return $this->logSendMail;
	}

	/**
	 * @return string
	 */
	public function getLogMailRecipient() {
		return $this->logMailRecipient;
	}

	public function isLogHandleStatusExceptionsEnabled(): bool {
		return $this->logHandleStatusExceptions;
	}

	/**
	 * 
	 * @return array
	 */
	public function getLogExcludedHttpStatus(): array {
		return $this->logExcludedHttpStatuses;
	}

	/**
	 * @param int $httpStatus
	 * @return bool
	 */
	public function isLoggingForStatusExceptionEnabled($httpStatus): bool {
		return $this->isLogHandleStatusExceptionsEnabled() && !in_array($httpStatus, $this->getLogExcludedHttpStatus());
	}

	/** 
	 * @param int $httpStatus
	 * @return string
	 */
	public function getErrorViewName($httpStatus) {
		if (isset($this->errorViewNames[$httpStatus])) {
			return $this->errorViewNames[$httpStatus];
		}
		
		if (isset($this->errorViewNames[self::ERROR_VIEW_DEFAULT_KEY_SUFFIX])) {
			return $this->errorViewNames[self::ERROR_VIEW_DEFAULT_KEY_SUFFIX];
		}
		
		return null;
	}
	
	public function getErrorViewNames(): array {
		return $this->errorViewNames;
	}
	
	function getDefaultErrorViewName() {
		return $this->errorViewNames[self::ERROR_VIEW_DEFAULT_KEY_SUFFIX] ?? null;
	}

	function getMonitorSlowQueryTime(): ?float {
		return $this->monitorSlowQueryTime;
	}
}
