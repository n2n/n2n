<?php

namespace n2n\core\container;

enum TransactionPhase: string {
	case CLOSED = 'closed';
	case OPEN = 'open';
	case PREPARE_COMMIT = 'prepare commit';
	case COMMIT = 'commit';

	case ROLLBACK = 'rollback';
	case CORRUPTED_STATE = 'corrupted state';

	function isCompleting(): bool {
		return in_array($this, [self::PREPARE_COMMIT, self::COMMIT, self::ROLLBACK]);
	}

}
