<?php

namespace n2n\core\container\impl;

use n2n\util\magic\MagicContext;

interface AddOnContext extends MagicContext {

	function copyTo(AppN2nContext $appN2NContext): void;

	function finalize(): void;


}