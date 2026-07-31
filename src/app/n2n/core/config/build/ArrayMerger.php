<?php

namespace n2n\core\config\build;

use n2n\util\attr\AttributesException;

class ArrayMerger {
	private $groupReader;
	private $attributeName;

	private $arr = array();
	private $mainArr = array();
	private $attributesDefs = array();

	public function __construct(GroupReader $groupReader) {
		$this->groupReader = $groupReader;
	}

	public function loadScalarArray(string $attributeName) {
		$this->attributeName = $attributeName;
		$this->arr = array();
		$this->mainArr = array();
		$this->attributesDefs = array();

		foreach ($this->groupReader->getMainAttributesDefs() as $def) {
			try {
				$this->merge($def->getAttributes()->getScalarArray($attributeName, false), $def, true);
			} catch (AttributesException $e) {
				throw $this->groupReader->createInvalidAttributeException($attributeName, $def, $e);
			}
		}

		foreach ($this->groupReader->getAdditionalAttributesDefs() as $def) {
			try {
				$this->merge($def->getAttributes()->getScalarArray($attributeName, false), $def, false);
			} catch (AttributesException $e) {
				throw $this->groupReader->createInvalidAttributeException($attributeName, $def, $e);
			}
		}
	}

	private function merge(array $arr, AttributesDef $attributesDef, bool $main) {
		foreach ($arr as $key => $value) {
			if (is_numeric($key)) {
				$this->arr[] = $value;
				continue;
			}

			if (!array_key_exists($key, $this->arr) || (!$this->mainArr[$key] && $main)) {
				$this->arr[$key] = $value;
				$this->mainArr[$key] = $main;
				$this->attributesDefs[$key] = $attributesDef;
				continue;
			}

			if (($this->mainArr[$key] && !$main) || $this->arr[$key] === $value) {
				continue;
			}

			throw $this->groupReader->createConflictException($this->attributeName . '[' . $key . ']',
					$this->attributesDefs[$key], $attributesDef);
		}
	}

	public function getArray() {
		return $this->arr;
	}

	public function getAttributesDefByKey($key) {
		if (isset($this->attributesDefs[$key])) {
			return $this->attributesDefs[$key];
		}

		throw new \OutOfBoundsException();
	}
}