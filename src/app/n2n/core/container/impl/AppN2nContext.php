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
namespace n2n\test;

use n2n\web\http\HttpContext;
use n2n\core\HttpContextFactory;
use n2n\web\http\SimpleRequest;
use n2n\core\N2N;
use n2n\util\uri\Query;
use n2n\web\http\Method;
use n2n\util\uri\Url;
use n2n\web\http\controller\ControllerRegistry;
use n2n\core\container\impl\AppN2nContext;
use n2n\web\http\Response;
use n2n\util\StringUtils;
use n2n\core\container\N2nContext;
use n2n\core\config\AppConfig;
use n2n\core\config\WebConfig;
use n2n\util\type\CastUtils;
use n2n\web\http\SimpleSession;
use n2n\core\container\PdoPool;
use n2n\web\http\UploadDefinition;
use n2n\util\type\ArgUtils;
use n2n\util\io\fs\FsPath;
use n2n\io\managed\FileSource;
use n2n\io\managed\impl\FsFileSource;
use n2n\util\io\IoUtils;
use n2n\reflection\magic\MagicMethodInvoker;
use n2n\util\cache\impl\EphemeralCacheStore;
use n2n\context\config\SimpleLookupSession;

class HttpTestEnv {

	/**
	 * @var N2nContext
	 */
	private $n2nContext;

	/**
	 * @param N2nContext $n2nContext
	 */
	function __construct(N2nContext $n2nContext) {
		$this->n2nContext = $n2nContext;
	}

	/**
	 * @param string $subsystem
	 * @param Url $contextUrl
	 * @return TestRequest
	 */
	function newRequest($subsystemName = null, Url $contextUrl = null) {
		if ($contextUrl === null) {
			$contextUrl = Url::create('https://www.test-url.ch/');
		}

		$request = new SimpleRequest($contextUrl);
		$request->setN2nLocale($this->n2nContext->getN2nLocale());

		$appN2nContext = AppN2nContext::createCopy($this->n2nContext, new SimpleLookupSession(), new EphemeralCacheStore());
		$httpContext = HttpContextFactory::createFromAppConfig(N2N::getAppConfig(), $request, new SimpleSession(),$appN2nContext, null);


		$appN2nContext->setHttpContext($httpContext);
		if ($subsystemName !== null) {
			$httpContext->setActiveSubsystemRule($httpContext->findBestSubsystemRuleBySubsystemAndN2nLocale($subsystemName));
		}

//		$pdoPool = $appN2nContext->lookup(PdoPool::class);
//		foreach ($this->n2nContext->lookup(PdoPool::class)->getInitializedPdos() as $puName => $pdo) {
//			$pdoPool->setPdo($puName, $pdo);
//		}

		return new TestRequest($httpContext, $request);
	}

	/**
	 * @param FsPath $fsPath
	 * @return UploadDefinition
	 */
	function newUploadDefinitionFromFsPath(FsPath $fsPath) {
		$tmpFile = tempnam(sys_get_temp_dir(), 'n2n-test');
		IoUtils::putContents($tmpFile, IoUtils::getContents((string) $fsPath));

		return new UploadDefinition(UPLOAD_ERR_OK, $fsPath->getName(), $tmpFile,
				mime_content_type((string) $fsPath), $fsPath->getSize());
	}
}

class TestRequest {
	/**
	 * @var HttpContext
	 */
	private $httpContext;
	/**
	 * @var SimpleRequest
	 */
	private SimpleRequest $simpleRequest;

	/**
	 * @param HttpContext $httpContext
	 */
	function __construct(HttpContext $httpContext, SimpleRequest $simpleRequest) {
		$this->httpContext = $httpContext;
		$this->simpleRequest = $simpleRequest;
	}

	/**
	 * @param \Closure $closure
	 * @return $this
	 * @throws \ReflectionException
	 */
	function inject(\Closure $closure) {
		$invoker = new MagicMethodInvoker($this->httpContext->getN2nContext());
		$invoker->invoke(null, new \ReflectionFunction($closure));
		return $this;
	}

	function putLookupInjection(string $className, object $obj) {
		$this->httpContext->getN2nContext()->putLookupInjection($className, $obj);
		return $this;
	}

	function removeLookupInjection(string $className) {
		$this->httpContext->getN2nContext()->removeLookupInjection($className);
		return $this;
	}

	/**
	 * @param string $name
	 * @return \n2n\test\TestRequest
	 */
	function subsystem(?string $name) {
		if ($name === null) {
			$this->httpContext->setActiveSubsystemRule(null);
			return $this;
		}

		$this->httpContext->setActiveSubsystemRule($this->httpContext->findBestSubsystemRuleBySubsystemAndN2nLocale($name));
		return $this;
	}

	/**
	 * @return \n2n\test\TestRequest
	 */
	function get($cmdUrl) {
		$this->simpleRequest->setMethod(Method::GET);
		$this->simpleRequest->setCmdUrl(Url::create($cmdUrl));
		return $this;
	}

	/**
	 * @return \n2n\test\TestRequest
	 */
	function put($cmdUrl) {
		$this->simpleRequest->setMethod(Method::PUT);
		$this->simpleRequest->setCmdUrl(Url::create($cmdUrl));
		return $this;
	}

	/**
	 * @return \n2n\test\TestRequest
	 */
	function delete($cmdUrl) {
		$this->simpleRequest->setMethod(Method::DELETE);
		$this->simpleRequest->setCmdUrl(Url::create($cmdUrl));
		return $this;
	}

	/**
	 * @param mixed $cmdUrl will be passed to {@see Url::create()} for creation
	 * @param mixed $postQuery will be passed to {@see Query::create()} for creation
	 * @return \n2n\test\TestRequest
	 */
	function post($cmdUrl, $postQuery = null) {
		$this->simpleRequest->setMethod(Method::POST);
		$this->simpleRequest->setCmdUrl(Url::create($cmdUrl));

		if ($postQuery !== null) {
			$this->simpleRequest->setPostQuery(Query::create($postQuery));
		}

		return $this;
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @return \n2n\test\TestRequest
	 */
	function header(string $name, string $value) {
		$this->simpleRequest->setHeader($name, $value);
		return $this;
	}

	/**
	 * @param string|null $body
	 * @return \n2n\test\TestRequest
	 */
	function body(?string $body) {
		$this->simpleRequest->setBody($body);
		return $this;
	}

	/**
	 * @param array $data
	 * @return \n2n\test\TestRequest
	 */
	function bodyJson(array $data) {
		return $this->body(StringUtils::jsonEncode($data));
	}

	/**
	 * @param UploadDefinition[] $uploadDefinitions
	 * @return \n2n\test\TestRequest
	 */
	function uploadDefinitions(array $uploadDefinitions) {
		$this->simpleRequest->setUploadDefinitions($uploadDefinitions);
		return $this;
	}

	/**
	 * @return TestResponse
	 */
	function exec(bool $sendStatusView = false) {
		/**
		 * @var ControllerRegistry $controllerRegistry
		 */
		$controllerRegistry = $this->httpContext->getN2nContext()->lookup(ControllerRegistry::class);

		$controllingPlan = $controllerRegistry
				->createControllingPlan($this->simpleRequest->getCmdPath(), $this->httpContext->getActiveSubsystemRule());
		$result = $controllingPlan->execute();

		if (!$result->isSuccessful()) {
			if (!$sendStatusView) {
				$this->httpContext->getN2nContext()->finalize();
				throw $result->getStatusException();
			}

			$controllingPlan->sendStatusView($result->getStatusException());
		}

		$response = $this->httpContext->getResponse();
		$response->closeBuffer();

		$this->httpContext->getN2nContext()->finalize();

		return new TestResponse($response);
	}

	function __destruct() {
		$this->httpContext->getN2nContext()->finalize();
	}
}

class TestResponse {
	private $response;

	function __construct(Response $response) {
		$this->response = $response;
	}

	/**
	 * @return array
	 */
	function parseJson() {
		return StringUtils::jsonDecode($this->getContents(), true);
	}

	/**
	 * @return string
	 */
	function getContents() {
		return $this->response->getSentPayload()->getBufferedContents();
	}

	/**
	 * @return bool
	 */
	function isBufferable(): bool {
		return $this->response->getSentPayload()->isBufferable();
	}

	/**
	 * @return Payload
	 */
	function getSentPayload(): Payload {
		return $this->response->getSentPayload();
	}

	/**
	 * @return int
	 */
	function getStatus() {
		return $this->response->getStatus();
	}
}
