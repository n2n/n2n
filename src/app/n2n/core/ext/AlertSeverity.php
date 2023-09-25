<?php
namespace n2n\core\ext;

enum AlertSeverity: string {
	case LOW = 'low';
	case MEDIUM = 'medium';
	case HIGH = 'high';
}