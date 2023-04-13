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

use n2n\l10n\N2nLocale;

class N2nLocaleConfig {
    const FALLBACK_LOCALE_ID_DEFAULT = 'en';
    const ADMIN_LOCALE_ID_DEFAULT = 'rm_CH';
    const DEFAULT_LOCALE_ID_DEFAULT = 'rm_CH';


	public function __construct(
			private N2nLocale $fallbackN2nLocale = new N2nLocale(self::FALLBACK_LOCALE_ID_DEFAULT),
			private N2nLocale $adminN2nLocale = new N2nLocale(self::ADMIN_LOCALE_ID_DEFAULT),
			private N2nLocale $defaultN2nLocale = new N2nLocale(self::DEFAULT_LOCALE_ID_DEFAULT)) {

	}
	
	/**
	 * @return \n2n\l10n\N2nLocale
	 */
	public function getFallbackN2nLocale(): N2nLocale {
		return $this->fallbackN2nLocale;
	}
	
	/**
	 * @return \n2n\l10n\N2nLocale
	 */
	public function getAdminN2nLocale(): N2nLocale  {
		return $this->adminN2nLocale;
	}
	
	/**
	 * @return \n2n\l10n\N2nLocale
	 */
	public function getDefaultN2nLocale(): N2nLocale  {
		return $this->defaultN2nLocale;
	}
	
	/**
	 * @deprecated
	 */
	public function getN2nLocales() {
		return array($this->defaultN2nLocale);
	}
}