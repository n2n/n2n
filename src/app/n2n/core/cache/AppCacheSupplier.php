<?php

namespace n2n\core\cache;

use n2n\core\container\impl\AppN2nContext;

interface AppCacheSupplier {

	function applyToN2nContext(AppN2nContext $n2nContext): void;
}
