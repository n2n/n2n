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
use n2n\spec\tx\TransactionIsolationLevel;

class PersistenceUnitConfig {
//	const TIL_READ_UNCOMMITTED = "READ UNCOMMITTED";
//	const TIL_READ_COMMITTED = "READ COMMITTED";
//	const TIL_REPEATABLE_READ = "REPEATABLE READ";
//	const TIL_SERIALIZABLE = "SERIALIZABLE";
	
	public function __construct(private string $name, private string $dsnUri, private string $user, private ?string $password,
			private TransactionIsolationLevel $readWriteTransactionIsolationLevel, private string $dialectClassName, private bool $sslVerify = true,
			private ?string $sslCaCertificatePath = null, private bool $persistent = false,
			private TransactionIsolationLevel $readOnlyTransactionIsolationLevel = TransactionIsolationLevel::TIL_REPEATABLE_READ) {
		ArgUtils::valEnum($this->readWriteTransactionIsolationLevel, self::getTransactionIsolationLevels());
		ArgUtils::valEnum($this->readOnlyTransactionIsolationLevel, self::getTransactionIsolationLevels());
	}
	
	public function getName(): string {
		return $this->name;
	}
	
	public function getDsnUri(): string {
		return $this->dsnUri;
	}
	
	public function getUser(): string {
		return $this->user;		
	}
	
	public function getPassword(): ?string {
		return $this->password;
	}

	/**
	 * @deprecated default use readWriteTransactionIsolationLevel
	 */
	public function getTransactionIsolationLevel(): TransactionIsolationLevel {
		return $this->readWriteTransactionIsolationLevel;
	}

	public function getReadWriteTransactionIsolationLevel(): TransactionIsolationLevel {
		return $this->readWriteTransactionIsolationLevel;
	}

	public function getReadOnlyTransactionIsolationLevel(): TransactionIsolationLevel {
		return $this->readOnlyTransactionIsolationLevel;
	}
	
	public function getDialectClassName(): string {
		return $this->dialectClassName;
	}

	function isSslVerify(): bool {
		return $this->sslVerify;
	}

	function getSslCaCertificatePath(): ?string {
		return $this->sslCaCertificatePath;
	}

	/**
	 * @deprecated use {@link TransactionIsolationLevel::cases()}
	 */
	public static function getTransactionIsolationLevels(): array {
		return TransactionIsolationLevel::cases();
	}

	public function isPersistent(): bool {
		return $this->persistent;
	}
}
