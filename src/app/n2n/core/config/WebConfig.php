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

class WebConfig {
	private $responseCachingEnabled;
	private $responseBrowserCachingEnabled; 
	private $responseSendEtagAllowed;
	private $responseSendLastModifiedAllowed; 
	private $responseServerPushAllowed;
	private $viewCachingEnabled; 
	private $viewClassNames;
	private $dispatchPropertyProviderClassNames;
	private $dispatchTargetCryptAlgorithm;
	private $aliasN2nLocales;
	private $responseDefaultHeaders;

	private $responseContentSecurityPolicyEnabled;


	/**
	 * @param bool $responseCachingEnabled
	 * @param bool $responseBrowserCachingEnabled
	 * @param bool $responseSendEtagAllowed
	 * @param bool $responseServerPushAllowed
	 * @param bool $responseSendLastModifiedAllowed
	 * @param array $responseDefaultHeaders
	 * @param bool $viewCachingEnabled
	 * @param string[] $viewClassNames
	 * @param string[] $dispatchPropertyProviderClassNames
	 * @param string $dispatchTargetCryptAlgorithm
	 * @param N2nLocale[] $aliasN2nLocales
	 * @param bool $responseContentSecurityPolicyEnabled
	 */
	public function __construct(bool $responseCachingEnabled, bool $responseBrowserCachingEnabled, 
			bool $responseSendEtagAllowed, bool $responseServerPushAllowed, bool $responseSendLastModifiedAllowed,
			array $responseDefaultHeaders,
			bool $viewCachingEnabled, array $viewClassNames,
			array $dispatchPropertyProviderClassNames, string $dispatchTargetCryptAlgorithm, array $aliasN2nLocales,
			bool $responseContentSecurityPolicyEnabled) {

		$this->responseCachingEnabled = $responseCachingEnabled;
		$this->responseBrowserCachingEnabled = $responseBrowserCachingEnabled;
		$this->responseSendEtagAllowed = $responseSendEtagAllowed;
		$this->responseSendLastModifiedAllowed = $responseSendLastModifiedAllowed;
		$this->responseServerPushAllowed = $responseServerPushAllowed;
		$this->responseDefaultHeaders = $responseDefaultHeaders;
		$this->viewCachingEnabled = $viewCachingEnabled;
		$this->viewClassNames = $viewClassNames;
		$this->dispatchPropertyProviderClassNames = $dispatchPropertyProviderClassNames;
		$this->dispatchTargetCryptAlgorithm = $dispatchTargetCryptAlgorithm;
		$this->aliasN2nLocales = $aliasN2nLocales;
		$this->responseContentSecurityPolicyEnabled = $responseContentSecurityPolicyEnabled;
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
		$n2nLocales = $this->legacyRoutingConfig->getN2nLocales();
		foreach ($this->legacyRoutingConfig->getRoutingRules() as $routingRule) {
			$n2nLocales = array_merge($n2nLocales, $routingRule->getN2nLocales());
		}
		return $n2nLocales;
	}
}
