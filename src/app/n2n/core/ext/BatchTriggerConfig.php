<?php

namespace n2n\core\ext;

class BatchTriggerConfig {
	function __construct(public \DateTimeImmutable $dateTime = new \DateTimeImmutable(),
			public ?\DateTimeImmutable $overwriteLastTriggerDateTime = null,
			public ?array $filterBatchJobNames = null) {
	}
}