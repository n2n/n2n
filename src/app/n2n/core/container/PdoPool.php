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

use n2n\core\config\DbConfig;
use n2n\core\config\OrmConfig;
use n2n\util\magic\MagicContext;
use n2n\persistence\orm\model\EntityModelManager;
use n2n\persistence\orm\model\EntityModelFactory;
use n2n\persistence\orm\proxy\EntityProxyManager;
use n2n\core\container\impl\AppN2nContext;
use n2n\persistence\UnknownPersistenceUnitException;
use n2n\persistence\Pdo;
use n2n\core\config\PersistenceUnitConfig;
use n2n\persistence\orm\LazyEntityManagerFactory;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\PdoPoolListener;
use n2n\context\attribute\ThreadScoped;

/**
 * @deprecated use {@link \n2n\persistence\ext\PdoPool}
 */
#[ThreadScoped]
class PdoPool {

	const DEFAULT_DS_NAME = \n2n\persistence\ext\PdoPool::DEFAULT_DS_NAME;

	private \n2n\persistence\ext\PdoPool $decoratedPdoPool;
	private \n2n\persistence\ext\EmPool $decoratedEmPool;

	private function _init(\n2n\persistence\ext\PdoPool $decoratedPdoPool,
			\n2n\persistence\ext\EmPool $decoratedEmPool) {
		$this->decoratedPdoPool = $decoratedPdoPool;
		$this->decoratedEmPool = $decoratedEmPool;
	}

	function clear() {
		$this->decoratedEmPool->clear();
	}

	public function getTransactionManager() {
		return $this->decoratedPdoPool->getTransactionManager();
	}

	public function setMagicContext(MagicContext $magicContext = null) {
		$this->decoratedEmPool->setMagicContext($magicContext);
	}
	/**
	 * @return MagicContext
	 */
	public function getMagicContext() {
		return $this->decoratedEmPool->getMagicContext();
	}
	/**
	 * @return string
	 */
	public function getPersistenceUnitNames() {
		return $this->decoratedPdoPool->getPersistenceUnitNames();
	}
	/**
	 * @param string $persistenceUnitName
	 * @return \n2n\persistence\Pdo
	 */
	public function getPdo(string $persistenceUnitName = null) {
		return $this->decoratedPdoPool->getPdo($persistenceUnitName);
	}

	/**
	 * @return Pdo[]
	 */
	function getInitializedPdos() {
		return $this->decoratedPdoPool->getInitializedPdos();
	}

	/**
	 * @param string $persistenceUnitName
	 * @param Pdo $pdo
	 * @throws \InvalidArgumentException
	 */
	function setPdo(string $persistenceUnitName, Pdo $pdo) {
		$this->decoratedPdoPool->setPdo($persistenceUnitName, $pdo);
	}


	/**
	 * @param PersistenceUnitConfig $persistenceUnitConfig
	 * @return Pdo
	 */
	public function createPdo(PersistenceUnitConfig $persistenceUnitConfig) {
		return $this->decoratedPdoPool->createPdo($persistenceUnitConfig);
	}

	/**
	 *
	 * @param string $persistenceUnitName
	 * @return \n2n\persistence\orm\EntityManagerFactory
	 */
	public function getEntityManagerFactory($persistenceUnitName = null) {
		return $this->decoratedEmPool->getEntityManagerFactory($persistenceUnitName);
	}

	/**
	 * @return EntityModelManager
	 */
	public function getEntityModelManager() {
		return $this->decoratedEmPool->getEntityModelManager();
	}
	/**
	 * @return EntityProxyManager
	 */
	public function getEntityProxyManager() {
		return $this->decoratedEmPool->getEntityProxyManager();
	}

	public function registerListener(PdoPoolListener $dbhPoolListener) {
		$this->decoratedPdoPool->registerListener($dbhPoolListener);
	}

	public function unregisterListener(PdoPoolListener $dbhPoolListener) {
		$this->decoratedPdoPool->unregisterListener($dbhPoolListener);
	}
}
