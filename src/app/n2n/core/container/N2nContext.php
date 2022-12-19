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

use n2n\l10n\N2nLocale;
use n2n\util\magic\MagicContext;
use n2n\core\VarStore;
use n2n\core\module\ModuleManager;
use n2n\web\http\HttpContext;
use n2n\web\http\HttpContextNotAvailableException;
use n2n\context\LookupManager;
use n2n\util\type\ArgUtils;
use n2n\core\util\N2nUtil;

interface N2nContext extends MagicContext {

	function util(): N2nUtil;

	public function getTransactionManager(): TransactionManager;

	public function getModuleManager(): ModuleManager;

	public function getModuleConfig(string $namespace);

	public function getVarStore(): VarStore;

	public function isHttpContextAvailable(): bool;

	public function getHttpContext(): HttpContext;

	public function getAppCache(): AppCache;

	public function getN2nLocale(): N2nLocale;

	public function setN2nLocale(N2nLocale $n2nLocale);

	public function getLookupManager(): LookupManager;

	function putLookupInjection(string $id, object $obj): void;

	function removeLookupInjection(string $id): void;

	function clearLookupInjections(): void;

	function finalize(): void;
}
