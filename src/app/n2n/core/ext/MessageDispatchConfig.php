<?php

namespace n2n\core\ext;

use n2n\core\container\N2nContext;

class MessageDispatchConfig {
	function __construct(public ?N2nContext $n2nContext = null) {
	}
}