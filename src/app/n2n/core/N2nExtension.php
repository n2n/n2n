<?php

namespace n2n\core;

use n2n\core\container\impl\AppN2nContext;

interface N2nExtension {

	function __construct();

	function setUp(AppN2nContext $appN2NContext): void;

	function copyTo(AppN2nContext $appN2NContext): void;
}