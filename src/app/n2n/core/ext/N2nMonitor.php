<?php

namespace n2n\core\ext;

interface N2nMonitor {
	function alert(string $namespace, string $hash, string $text, AlertSeverity $severity): void;
}