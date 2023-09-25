<?php

namespace n2n\core\ext;

use n2n\core\N2nApplication;

interface ConfigN2nExtension extends N2nExtension {

	function __construct(N2nApplication $n2nApplication);
}