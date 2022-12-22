<?php

namespace n2n\core\ext;

use n2n\core\container\impl\AppN2nContext;

interface N2nExtension {

	function __construct();

	function setUp(AppN2nContext $appN2NContext): void;
}
