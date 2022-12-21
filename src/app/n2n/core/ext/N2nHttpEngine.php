<?php

namespace n2n\core\ext;

use n2n\context\config\LookupSession;

interface N2nHttpEngine {

	function getLookupSession(): LookupSession;

	function invokerControllers(): void;
}
