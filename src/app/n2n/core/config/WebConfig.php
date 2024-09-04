<?php
/*
 * Copyright (c) 2012-2016, Hofmänner New Media.
 * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS FILE HEADER.
 *
 * This file is part of the N2N FRAMEWORK.
 *
 * The N2N FRAMEWORK is free software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * N2N is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
 * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details: http://www.gnu.org/licenses/
 *
 * The following people participated in this project:
 *
 * Andreas von Burg.....: Architect, Lead Developer
 * Bert Hofmänner.......: Idea, Frontend UI, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\core\config;

use n2n\util\type\ArgUtils;
use n2n\l10n\N2nLocale;
use n2n\util\crypt\EncryptionDescriptor;
use n2n\core\config\web\SessionSaveMode;

class WebConfig {
	const PAYLOAD_CACHING_ENABLED_DEFAULT = true;
	const RESPONSE_CACHING_ENABLED_DEFAULT = true;
	const RESPONSE_BROWSER_CACHING_ENABLED_DEFAULT = true;
	const RESPONSE_SEND_ETAG_ALLOWED_DEFAULT = true;
	const RESPONSE_SEND_LAST_MODIFIED_ALLOWED_DEFAULT = true;
	const RESPONSE_SERVER_PUSH_ALLOWED_DEFAULT = true;
	const RESPONSE_CONTENT_SECURITY_POLICY_ENABLED_DEFAULT = false;
	const VIEW_CACHING_ENABLED_DEFAULT = true;
	const DISPATCH_TARGET_CRYPT_ALGORITHM_DEFAULT = EncryptionDescriptor::ALGORITHM_AES_256_CTR;
	const SESSION_SAVE_MODE_DEFAULT = SessionSaveMode::FILESYSTEM;

	/**
	 * @param bool $payloadCachingEnabled
	 * @param bool $responseCachingEnabled
	 * @param bool $responseBrowserCachingEnabled
	 * @param bool $responseSendEtagAllowed
	 * @param bool $responseServerPushAllowed
	 * @param bool $responseSendLastModifiedAllowed
	 * @param array $responseDefaultHeaders
	 * @param bool $viewCachingEnabled
	 * @param string[] $viewClassNames
	 * @param string[] $dispatchPropertyProviderClassNames
	 * @param string|null $dispatchTargetCryptAlgorithm
	 * @param N2nLocale[] $aliasN2nLocales
	 * @param bool $responseContentSecurityPolicyEnabled
	 * @param SessionSaveMode $sessionSaveMode
	 */
	public function __construct(private bool $payloadCachingEnabled = self::PAYLOAD_CACHING_ENABLED_DEFAULT,
			private bool $responseCachingEnabled = self::RESPONSE_CACHING_ENABLED_DEFAULT,
			private bool $responseBrowserCachingEnabled = self::RESPONSE_BROWSER_CACHING_ENABLED_DEFAULT,
			private bool $responseSendEtagAllowed = self::RESPONSE_SEND_ETAG_ALLOWED_DEFAULT,
			private bool $responseSendLastModifiedAllowed = self::RESPONSE_SEND_LAST_MODIFIED_ALLOWED_DEFAULT,
			private bool $responseServerPushAllowed = self::RESPONSE_SERVER_PUSH_ALLOWED_DEFAULT,
			private array $responseDefaultHeaders = [],
			private bool $viewCachingEnabled = self::VIEW_CACHING_ENABLED_DEFAULT,
			private array $viewClassNames = [],
			private array $dispatchPropertyProviderClassNames = [],
			private ?string $dispatchTargetCryptAlgorithm = self::DISPATCH_TARGET_CRYPT_ALGORITHM_DEFAULT,
			private array $aliasN2nLocales = [],
			private bool $responseContentSecurityPolicyEnabled = self::RESPONSE_CONTENT_SECURITY_POLICY_ENABLED_DEFAULT,
			private SessionSaveMode $sessionSaveMode = self::SESSION_SAVE_MODE_DEFAULT) {

		ArgUtils::valArray($this->aliasN2nLocales, N2nLocale::class);
	}

	/**
	 * @return boolean
	 */
	public function isPayloadCachingEnabled() {
		return $this->payloadCachingEnabled;
	}

	/**
	 * @return boolean
	 */
	public function isResponseCachingEnabled() {
		return $this->responseCachingEnabled;
	}

	/**
	 * @return boolean
	 */
	public function isResponseBrowserCachingEnabled() {
		return $this->responseBrowserCachingEnabled;
	}
	
	/**
	 * @return boolean
	 */
	public function isResponseSendEtagAllowed() {
		return $this->responseSendEtagAllowed;
	}
	
	/**
	 * @return boolean
	 */
	public function isResponseSendLastModifiedAllowed() {
		return $this->responseSendLastModifiedAllowed;
	}
		
	/**
	* @return boolean
	*/
	public function isResponseServerPushAllowed() {
		return $this->responseServerPushAllowed;
	}

	/**
	 * @return string[]
	 * @deprecated
	 */
	function getResponseDefaultHeaders() {
		return $this->responseDefaultHeaders;
	}
	
	/**
	 * @return boolean
	 */
	public function isViewCachingEnabled() {
		return $this->viewCachingEnabled;
	}
	
	/**
	 * @return string[]
	 */
	public function getViewClassNames() {
		return $this->viewClassNames;
	}
	

	
	/**
	 * @return string[] 
	 */
	public function getDispatchPropertyProviderClassNames() {
		return $this->dispatchPropertyProviderClassNames;
	}
	
	/**
	 * @return string
	 */
	public function getDispatchTargetCryptAlgorithm() {
		return $this->dispatchTargetCryptAlgorithm;
	}
	
	/**
	 * @return N2nLocale[] 
	 */
	public function getAliasN2nLocales() {
		return $this->aliasN2nLocales;
	}

	/**
	 * @return bool
	 */
	public function isResponseContentSecurityPolicyEnabled(): bool {
		return $this->responseContentSecurityPolicyEnabled;
	}

	/**
	 * @param bool $responseContentSecurityPolicyEnabled
	 * @return WebConfig
	 */
	public function setResponseContentSecurityPolicyEnabled(bool $responseContentSecurityPolicyEnabled): WebConfig {
		$this->responseContentSecurityPolicyEnabled = $responseContentSecurityPolicyEnabled;
		return $this;
	}

	public RoutingConfig $legacyRoutingConfig;

	/**
	 * @return N2nLocale[]
	 * @deprecated
	 */
	public function getAllN2nLocales() {
		return $this->legacyRoutingConfig->getAllN2nLocales();
	}

	public function getSessionSaveMode(): SessionSaveMode {
		return $this->sessionSaveMode;
	}
}
