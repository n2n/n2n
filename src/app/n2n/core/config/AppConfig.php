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

use n2n\l10n\L10nConfig;
use n2n\l10n\PseudoL10nConfig;

class AppConfig {
		public function __construct(private GeneralConfig $generalConfig = new GeneralConfig(),
			private WebConfig $webConfig = new WebConfig(),
			private readonly RoutingConfig $routingConfig = new RoutingConfig(),
			private MailConfig $mailConfig = new MailConfig(),
			private IoConfig $ioConfig = new IoConfig(),
			private FilesConfig $filesConfig = new FilesConfig(),
			private ErrorConfig $errorConfig = new ErrorConfig(),
			private DbConfig $dbConfig = new DbConfig(),
			private OrmConfig $ormConfig = new OrmConfig(),
			private N2nLocaleConfig $localeConfig = new N2nLocaleConfig(),
			private L10nConfig $l10nConfig = new L10nConfig(),
			private PseudoL10nConfig $pseudoL10nConfig = new PseudoL10nConfig()) {
		$this->webConfig->legacyRoutingConfig = $this->routingConfig;
	}
	/**
	 * @return \n2n\core\config\GeneralConfig
	 */
	public function general() {
		return $this->generalConfig;
	}
	/**
	 * @return \n2n\core\config\WebConfig
	 */
	public function web() {
		return $this->webConfig;	
	}

	function routing(): RoutingConfig {
		return $this->routingConfig;
	}

	/**
	 * @return \n2n\core\config\MailConfig
	 */
	public function mail() {
		return $this->mailConfig;
	}
	/**
	 * @return \n2n\core\config\IoConfig
	 */
	public function io() {
		return $this->ioConfig; 
	}
	/**
	 * @return FilesConfig
	 */
	public function files() {
		return $this->filesConfig;
	}
	/**
	 * @return \n2n\core\config\ErrorConfig
	 */
	public function error() {
		return $this->errorConfig;
	}
	/**
	 * @return \n2n\core\config\DbConfig
	 */
	public function db() {
		return $this->dbConfig;
	}
	/**
	 * @return \n2n\core\config\OrmConfig
	 */
	public function orm() {
		return $this->ormConfig;
	}
	/**
	 * @return \n2n\core\config\N2nLocaleConfig
	 */
	public function locale() {
		return $this->localeConfig;
	}
	/**
	 * @return \n2n\l10n\L10nConfig
	 */
	public function l10n() {
		return $this->l10nConfig;
	}
	/**
	 * @return \n2n\l10n\PseudoL10nConfig
	 */
	public function pseudoL10n() {
		return $this->pseudoL10nConfig;
	}
}
