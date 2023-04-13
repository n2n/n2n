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

use n2n\log4php\LoggerLevel;
use n2n\util\type\ArgUtils;

class GeneralConfig {
	const PAGE_NAME_DEFAULT = 'New awesome project';
	const APPLICATION_NAME_DEFAULT = 'newAwesomeProject';
	const APPLICATION_REPLICATABLE_DEFAULT = false;

	/**
	 * @param string $pageName
	 * @param string|null $pageUrl
	 * @param string $applicationName
	 * @param string|null $applicationLogLevel
	 * @param bool $applicationReplicatable
	 * @param string[] $batchJobClassNames
	 * @param string[] $extensionClassNames
	 */
	public function  __construct(private string $pageName = self::PAGE_NAME_DEFAULT,
			private ?string $pageUrl = null,
			private string $applicationName = self::APPLICATION_NAME_DEFAULT,
			private ?string $applicationLogLevel = null,
			private bool $applicationReplicatable = self::APPLICATION_REPLICATABLE_DEFAULT,
			private array $batchJobClassNames = [],
			private array $extensionClassNames = []) {
		ArgUtils::assertTrue(1 === preg_match('#^\w+$#', $applicationName), 'Invalid application name.');
		ArgUtils::valArray($this->batchJobClassNames, 'string');
		ArgUtils::valArray($this->extensionClassNames, 'string');
	}

	/**
	 * @return string
	 */
	public function getPageName(): string {
		return $this->pageName;
	}

    /**
     * @return string|null
     */
	public function getPageUrl(): ?string {
		return $this->pageUrl;
	}
	
	/**
	 * @return string
	 */
	public function getApplicationName(): string {
		return $this->applicationName;
	}

	/**
	 * @return LoggerLevel
	 */
	public function getApplicationLogLevel(): LoggerLevel {
		$enumValues = array(LoggerLevel::getLevelTrace()->__toString(),
				LoggerLevel::getLevelDebug()->__toString(),	LoggerLevel::getLevelInfo()->__toString(),
				LoggerLevel::getLevelWarn()->__toString(), LoggerLevel::getLevelError()->__toString(),	
				LoggerLevel::getLevelFatal()->__toString(), LoggerLevel::getLevelOff()->__toString());
		// @todo how to determine default loglevel
		return LoggerLevel::toLevel($this->applicationLogLevel ?? LoggerLevel::ALL, LoggerLevel::getLevelAll());
	}

	function isApplicationReplicatable(): bool {
		return $this->applicationReplicatable;
	}

	/**
	 * @return array
	 * @deprecated use {@link self::getBatchJobClassNames()}
	 */
	public function getBatchJobLookupIds(): array {
		return $this->batchJobClassNames;
	}

	function getBatchJobClassNames(): array {
		return $this->batchJobClassNames;
	}

	function getExtensionClassNames(): array {
		return $this->extensionClassNames;
	}
}
