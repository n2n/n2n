<?php

namespace n2n\core\ext;

interface N2nBatch {

	/**
	 * @param BatchTriggerConfig|null $config
	 * @return array arbitrary result object per successfully invoked batch job.
	 */
	function trigger(?BatchTriggerConfig $config = null): array;

	function dispatch(object $message, ?MessageDispatchConfig $config = null): array;

	/**
	 * @template T
	 * @param object $message
	 * @param class-string<T>|null $expectedReturnClassName
	 * @param MessageDispatchConfig|null $config
	 * @return T
	 */
	function dispatchUnicast(object $message, ?string $expectedReturnClassName,
			?MessageDispatchConfig $config = null): mixed;
}

