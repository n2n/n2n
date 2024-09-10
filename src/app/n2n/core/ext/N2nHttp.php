<?php

namespace n2n\core\ext;

use n2n\context\config\LookupSession;

interface N2nHttp {

	function getLookupSession(): LookupSession;

	function invokerControllers(bool $flush): bool;

	function renderThrowable(\Throwable $t): bool;
}
