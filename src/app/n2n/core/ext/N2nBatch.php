<?php

namespace n2n\core\ext;

interface N2nBatch {

	function trigger(?BatchTriggerConfig $config = null): void;
}

