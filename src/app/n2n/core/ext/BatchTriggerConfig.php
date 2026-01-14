<?php

namespace n2n\core\ext;

use n2n\core\container\N2nContext;

class BatchTriggerConfig {
	function __construct(public \DateTimeImmutable $dateTime = new \DateTimeImmutable(),
			public ?\DateTimeImmutable $overwriteLastTriggerDateTime = null,
			public ?array $filterBatchJobNames = null,
			public ?N2nContext $n2nContext = null) {
	}

	static function filter(string ...$filterBatchJobNames): BatchTriggerConfig {
		return new BatchTriggerConfig(filterBatchJobNames: $filterBatchJobNames);
	}
}