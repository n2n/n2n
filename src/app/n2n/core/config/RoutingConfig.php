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

use n2n\util\type\ArgUtils;
use n2n\l10n\N2nLocale;
use n2n\core\config\routing\ControllerDef;
use n2n\core\config\routing\RoutingRule;

class RoutingConfig {

	function __construct(private readonly array $mainControllerDefs = [], private readonly array $filterControllerDefs = [],
			private readonly array $precacheControllerDefs = [], private readonly array $n2nLocales = [],
			private readonly array $responseHeaders = [], private readonly array $routingRules = []) {
		ArgUtils::valArray($this->mainControllerDefs, ControllerDef::class);
		ArgUtils::valArray($this->filterControllerDefs, ControllerDef::class);
		ArgUtils::valArray($this->precacheControllerDefs, ControllerDef::class);
		ArgUtils::valArray($this->n2nLocales, N2nLocale::class);
		ArgUtils::valArray($this->responseHeaders, 'string');
		ArgUtils::valArray($this->routingRules, RoutingRule::class);
	}

	/**
	 * @return ControllerDef[]
	 */
	public function getMainControllerDefs(): array {
		return $this->mainControllerDefs;
	}

	/**
	 * @return ControllerDef[]
	 */
	public function getFilterControllerDefs(): array {
		return $this->filterControllerDefs;
	}

	/**
	 * @return ControllerDef[]
	 */
	public function getPrecacheControllerDefs(): array {
		return $this->precacheControllerDefs;
	}

	/**
	 * @return N2nLocale[]
	 */
	public function getN2nLocales(): array {
		return $this->n2nLocales;
	}

	/**
	 * @return string[]
	 */
	function getResponseHeaders(): array {
		return $this->responseHeaders;
	}

	/**
	 * @return RoutingRule[]
	 */
	public function getRoutingRules(): array {
		return $this->routingRules;
	}
}