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
namespace n2n\core\config\routing;

use n2n\util\type\ArgUtils;
use n2n\l10n\N2nLocale;

class RoutingRule {

	function __construct(private string $matcherName, private ?string $subsystemName = null,
			private ?string $hostName = null, private ?string $contextPath = null,
			private array $n2nLocales = [], private array $responseHeaders = []) {
		ArgUtils::valArray($this->n2nLocales, N2nLocale::class);
		ArgUtils::valArray($this->responseHeaders, 'string');
	}

	/**
	 * @return string
	 */
	public function getMatcherName(): string {
		return $this->matcherName;
	}

	/**
	 * @return string|null
	 */
	public function getSubsystemName(): ?string {
		return $this->subsystemName;
	}

	/**
	 * @return string|null
	 */
	public function getHostName(): ?string {
		return $this->hostName;
	}

	/**
	 * @return string|null
	 */
	public function getContextPath(): ?string {
		return $this->contextPath;
	}

	/**
	 * @return array
	 */
	public function getN2nLocales(): array {
		return $this->n2nLocales;
	}

	/**
	 * @return array
	 */
	public function getResponseHeaders(): array {
		return $this->responseHeaders;
	}
}