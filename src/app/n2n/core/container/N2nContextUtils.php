<?php

namespace n2n\core\container;

use n2n\core\container\impl\AppN2nContext;
use n2n\util\ex\IllegalStateException;
use n2n\core\N2nApplication;

class N2nContextUtils {
	static function fork(N2nApplication $n2nApplication, N2nContext $n2nContext, bool $keepTransactionContext = false): AppN2nContext {
		$transactionManager = null;
		if ($keepTransactionContext) {
			$transactionManager = $n2nContext->getTransactionManager();
			IllegalStateException::assertTrue(!$transactionManager->hasOpenTransaction(),
					'Current TransactionManager can not be kept because it has open transactions.');
		}

		return $n2nApplication->createN2nContext($transactionManager);
	}
}