<?php

namespace n2n\core\ext;

interface N2nBatch {

	/**
	 * @param BatchTriggerConfig|null $config
	 * @return array arbitrary result object per successfully invoked batch job.
	 */
	function trigger(?BatchTriggerConfig $config = null): array;
}

