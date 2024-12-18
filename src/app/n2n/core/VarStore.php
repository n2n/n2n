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
namespace n2n\core;

use n2n\util\io\InvalidPathException;
use n2n\util\io\fs\FsPath;
use n2n\util\io\IoUtils;
use n2n\util\ex\IllegalStateException;
use n2n\util\io\IoException;
use n2n\util\type\ArgUtils;
use n2n\util\type\TypeUtils;
use InvalidArgumentException;
use n2n\util\io\fs\FileOperationException;
use n2n\util\ex\ExUtils;

class VarStore {
	const CATEGORY_ETC = 'etc';
	const CATEGORY_LOG = 'log';
	const CATEGORY_SRV = 'srv';
	const CATEGORY_BAK = 'bak';
	const CATEGORY_TMP = 'tmp';

	const SHARED_FOLDER = 'shared';
	
	private $varPath;
	private $dirPerm;
	private $filePerm;
	
	private $moduleOverwrittenPaths = array();

	/**
	 *
	 * @param string $varPath
	 * @param string|null $dirPerm
	 * @param string|null $filePerm
	 */
	public function __construct(string $varPath, ?string $dirPerm, ?string $filePerm) {
		$this->varPath = $varPath;
		$this->dirPerm = $dirPerm;
		$this->filePerm = $filePerm;
	}
	
	public function setDirPerm(?string $dirPerm) {
		$this->dirPerm = $dirPerm;
	}
	
	public function getDirPerm(): ?string {
		return $this->dirPerm;
	}
	
	public function setFilePerm(?string $filePerm) {
		$this->filePerm = $filePerm;
	}
	
	public function getFilePerm(): ?string {
		return $this->filePerm;
	}
	
	public function overwritePath(string $category, string $moduleNamespace, string $path): void {
		ArgUtils::valEnum($category, self::getCategories(), null, false, 'category');
		
		if (!isset($this->moduleOverwrittenPaths[$category])) {
			$this->moduleOverwrittenPaths[$category] = [];
		}
		
		$this->moduleOverwrittenPaths[$category][$moduleNamespace] = $path;
	}
	
	private function validatePathPart($pathPart): void {
		if (!IoUtils::hasSpecialChars($pathPart)) return;
		
		throw new InvalidArgumentException('Path part contains invalid chars: ' . $pathPart);
	}

	/**
	 *
	 * @param string $category
	 * @param string|null $moduleNamespace
	 * @param string|null $directoryName
	 * @param bool $create
	 * @param bool $required
	 * @param bool $shared
	 * @return FsPath
	 */
	public function requestDirFsPath(string $category, ?string $moduleNamespace = null, ?string $directoryName = null,
			bool $create = true, bool $required = true, bool $shared = false): FsPath {
		if (!in_array($category, self::getCategories())) {
			throw new InvalidArgumentException('Invalid var category \'' . $category . '\'. Available categories: '
					. implode(', ', self::getCategories()));
		}
		
		$dirPath = null;
		if (!$shared && $moduleNamespace !== null && isset($this->moduleOverwrittenPaths[$category][$moduleNamespace])) {
			$dirPath = $this->moduleOverwrittenPaths[$category][$moduleNamespace];
		} else {
			$dirPath = $this->varPath
					. ($shared ? DIRECTORY_SEPARATOR . self::SHARED_FOLDER : '')
					. DIRECTORY_SEPARATOR . $category;
			
			if (isset($moduleNamespace)) {
				$modulePathPart = self::namespaceToDirName((string) $moduleNamespace);
				$this->validatePathPart($modulePathPart);
				$dirPath .= DIRECTORY_SEPARATOR . $modulePathPart;
			}
		}
		
		if (isset($directoryName)) {
			$this->validatePathPart($directoryName);
			$dirPath .= DIRECTORY_SEPARATOR . $directoryName;
		}

		$path = new FsPath($dirPath);
		if ($path->isDir()) return $path;

		if ($create) {
			ExUtils::try(fn () => $path->mkdirs($this->dirPerm));
			return $path;
		}
		
		if (!$required) return $path;
		
		throw new InvalidPathException('Var directory not found: ' . $path);
	}
	
	public function requestFileFsPath($category, $module, $folderName, $fileName, $createFolder = false,
			$createFile = false, $required = true, bool $shared = false): FsPath {
		$dirPath = $this->requestDirFsPath($category, $module, $folderName, $createFolder || $createFile, $required, $shared);
		
		$this->validatePathPart($fileName);
		$filePath = new FsPath($dirPath . DIRECTORY_SEPARATOR . $fileName);
		if ($filePath->isFile()) return $filePath;
		
		if ($createFile) {
			$filePath->createFile($this->filePerm);
			return $filePath;
		}
		
		if (!$required) return $filePath;
		
		throw new InvalidPathException('Var file not found: ' . $filePath);
	}
	/**
	 * 
	 * @return array
	 */
	public static function getCategories() {
		return array(self::CATEGORY_ETC, self::CATEGORY_LOG, self::CATEGORY_SRV, self::CATEGORY_BAK, 
				self::CATEGORY_TMP);
	}
	
	public static function namespaceToDirName(string $namespace) {
		return TypeUtils::encodeNamespace($namespace);
	}
	
	public static function dirNameToNamespace(string $dirName) {
		$namespace = TypeUtils::decodeNamespace($dirName);
		
		if (TypeUtils::hasSpecialChars($namespace, false)) {
			throw new InvalidArgumentException('Invalid namespace: ' . $namespace);
		}
		
		return $namespace;
	}
}
