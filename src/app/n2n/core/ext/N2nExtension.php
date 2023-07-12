<?php

namespace n2n\core\ext;

use n2n\core\container\impl\AppN2nContext;
use n2n\core\config\AppConfig;
use n2n\core\cache\AppCache;
use n2n\core\N2nApplication;

interface N2nExtension {

	function applyToN2nContext(AppN2nContext $appN2nContext): void;
}
