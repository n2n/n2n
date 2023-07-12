<?php
namespace n2n\core\container\impl;

use n2n\util\magic\MagicLookupFailedException;
use n2n\util\magic\MagicObjectUnavailableException;
use ReflectionClass;

interface AddOnContext {

//	function copyTo(AppN2nContext $appN2nContext): void;

	function finalize(): void;

	/**
	 * @param string $id
	 * @return bool false if the object was not found. Also false if the object belongs to this AddOnContext but it
	 * 		somehow not available
	 */
	function hasMagicObject(string $id): bool;

	/**
	 * @param string $id
	 * @param bool $required
	 * @param string|null $contextNamespace
	 * @return mixed null if the object could not be found
	 * @throws MagicLookupFailedException general lookup error
	 * @throws MagicObjectUnavailableException only if $required is true, object was not found and the object of the
	 * 		passed id belongs to this AddOnContext but it somehow not available.
	 */
	function lookupMagicObject(string $id, bool $required = true, string $contextNamespace = null): mixed;

}