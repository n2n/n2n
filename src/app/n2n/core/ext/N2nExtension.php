<?php

namespace n2n\core\ext;

use n2n\core\container\impl\AppN2nContext;
use n2n\core\config\AppConfig;
use n2n\core\cache\AppCache;

interface N2nExtension {

	function __construct(AppConfig $appConfig, AppCache $appCache);

	function setUp(AppN2nContext $appN2nContext): void;
}
