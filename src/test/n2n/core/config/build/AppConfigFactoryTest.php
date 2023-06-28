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
namespace n2n\core\config\build;

use n2n\core\config\N2nLocaleConfig;
use n2n\core\config\SmtpConfig;
use n2n\core\config\WebConfig;
use n2n\util\uri\Url;
use n2n\core\config\ErrorConfig;
use n2n\core\config\FilesConfig;
use n2n\core\config\IoConfig;
use n2n\core\config\MailConfig;
use n2n\core\config\DbConfig;
use PHPUnit\Framework\TestCase;
use n2n\util\io\fs\FsPath;
use n2n\config\source\impl\IniFileConfigSource;
use n2n\util\type\ArgUtils;
use n2n\core\N2N;
use n2n\core\config\AppConfig;
use n2n\core\config\GeneralConfig;
use n2n\util\crypt\EncryptionDescriptor;
use n2n\l10n\N2nLocale;
use n2n\l10n\L10nConfig;

class AppConfigFactoryTest extends TestCase {

	private function createFromFsPath(string $iniFileName, array $additionalIniFileNames = [],
			string $stage = N2N::STAGE_LIVE): AppConfig {
		ArgUtils::valArray($additionalIniFileNames, 'string');

		$source = new CombinedConfigSource(new IniFileConfigSource($this->determineFsPath($iniFileName)));
		foreach ($additionalIniFileNames as $key => $additionalIniFileName) {
			$source->putAdditional($key, new IniFileConfigSource($this->determineFsPath($additionalIniFileName)));
		}

		$appConfigFactory = new AppConfigFactory(new FsPath('public'));
		return $appConfigFactory->create($source, $stage);
	}

	private function determineFsPath(string $iniFileName): FsPath {
		return (new FsPath(__DIR__))->getParent()->ext(['mock', 'ini', $iniFileName]);
	}

	function testAllDefaults(): void {
		$appConfig = $this->createFromFsPath('empty.app.ini');

		$this->assertEquals(GeneralConfig::PAGE_NAME_DEFAULT, $appConfig->general()->getPageName());
		$this->assertEquals(GeneralConfig::APPLICATION_NAME_DEFAULT, $appConfig->general()->getApplicationName());
		$this->assertEquals(GeneralConfig::APPLICATION_REPLICATABLE_DEFAULT, $appConfig->general()->isApplicationReplicatable());

		$this->assertEquals(WebConfig::RESPONSE_CACHING_ENABLED_DEFAULT, $appConfig->web()->isResponseCachingEnabled());
		$this->assertEquals(WebConfig::RESPONSE_BROWSER_CACHING_ENABLED_DEFAULT, $appConfig->web()->isResponseBrowserCachingEnabled());
		$this->assertEquals(WebConfig::RESPONSE_SEND_ETAG_ALLOWED_DEFAULT, $appConfig->web()->isResponseSendEtagAllowed());
		$this->assertEquals(WebConfig::RESPONSE_SEND_LAST_MODIFIED_ALLOWED_DEFAULT, $appConfig->web()->isResponseSendLastModifiedAllowed());
		$this->assertEquals(WebConfig::RESPONSE_SERVER_PUSH_ALLOWED_DEFAULT, $appConfig->web()->isResponseServerPushAllowed());
		$this->assertEquals(WebConfig::RESPONSE_CONTENT_SECURITY_POLICY_ENABLED_DEFAULT, $appConfig->web()->isResponseContentSecurityPolicyEnabled());
		$this->assertEquals(WebConfig::VIEW_CACHING_ENABLED_DEFAULT, $appConfig->web()->isViewCachingEnabled());
		$this->assertEquals(EncryptionDescriptor::ALGORITHM_AES_256_CTR, $appConfig->web()->getDispatchTargetCryptAlgorithm());

        $this->assertEquals(MailConfig::MAIL_SENDING_ENABLED_DEFAULT, $appConfig->mail()->isSendingMailEnabled());
        $this->assertEquals(SmtpConfig::PORT_DEFAULT, $appConfig->mail()->getDefaultSmtpConfig()->getPort());
        $this->assertEquals(SmtpConfig::SECURITY_MODE_DEFAULT, $appConfig->mail()->getDefaultSmtpConfig()->getSecurityMode());
        $this->assertEquals(SmtpConfig::SMTP_AUTHENTICATION_REQUIRED_DEFAULT, $appConfig->mail()->getDefaultSmtpConfig()->doAuthenticate());

        $this->assertEquals(IoConfig::PUBLIC_DIR_PERMISSION_DEFAULT, $appConfig->io()->getPublicDirPermission());
        $this->assertEquals(IoConfig::PUBLIC_FILE_PERMISSION_DEFAULT, $appConfig->io()->getPublicFilePermission());
        $this->assertEquals(IoConfig::PRIVATE_DIR_PERMISSION_DEFAULT, $appConfig->io()->getPrivateDirPermission());
        $this->assertEquals(IoConfig::PRIVATE_FILE_PERMISSION_DEFAULT, $appConfig->io()->getPrivateFilePermission());

        $this->assertEquals(ErrorConfig::STRICT_ATTITUDE_DEFAULT, $appConfig->error()->isStrictAttitudeEnabled());
        $this->assertEquals(ErrorConfig::STARTUP_DETECT_ERRORS_DEFAULT, $appConfig->error()->isDetectStartupErrorsEnabled());
        $this->assertEquals(ErrorConfig::STARTUP_DETECT_BAD_REQUESTS_DEFAULT, $appConfig->error()->isStartupDetectBadRequestsEnabled());
        $this->assertEquals(ErrorConfig::LOG_SAVE_DETAIL_INFO_DEFAULT, $appConfig->error()->isLogSaveDetailInfoEnabled());
        $this->assertEquals(ErrorConfig::LOG_SEND_MAIL_DEFAULT, $appConfig->error()->isLogSendMailEnabled());
        $this->assertEquals(ErrorConfig::LOG_HANDLE_HTTP_STATUS_EXCEPTIONS_DEFAULT, $appConfig->error()->isLogHandleStatusExceptionsEnabled());
        $this->assertEquals(ErrorConfig::MONITOR_ENABLED_DEFAULT, $appConfig->error()->isMonitorEnabled());

        $this->assertEquals(FsPath::create(['public', FilesConfig::ASSETS_DIR_DEFAULT]), $appConfig->files()->getAssetsDir());
        $this->assertEquals(Url::create(FilesConfig::ASSETS_URL_DEFAULT), $appConfig->files()->getAssetsUrl());
        $this->assertEquals(FsPath::create(['public', FilesConfig::MANAGER_PUBLIC_DIR_DEFAULT]), $appConfig->files()->getManagerPublicDir());
        $this->assertEquals(Url::create(FilesConfig::MANAGER_PUBLIC_URL_DEFAULT), $appConfig->files()->getManagerPublicUrl());

        $this->assertEquals(N2nLocaleConfig::FALLBACK_LOCALE_ID_DEFAULT, $appConfig->locale()->getFallbackN2nLocale());
        $this->assertEquals(N2nLocaleConfig::DEFAULT_LOCALE_ID_DEFAULT, $appConfig->locale()->getDefaultN2nLocale());
        $this->assertEquals(N2nLocaleConfig::ADMIN_LOCALE_ID_DEFAULT, $appConfig->locale()->getAdminN2nLocale());

        $this->assertEquals(L10nConfig::L10N_ENABLED_DEFAULT, $appConfig->l10n()->isEnabled());
    }

	function testGeneral() {
		$appConfig = $this->createFromFsPath('general.app.ini');

		$this->assertEquals("TestMe", $appConfig->general()->getPageName());
		$this->assertEquals("myapp.test", $appConfig->general()->getPageUrl());
		$this->assertEquals("TestMeApp", $appConfig->general()->getApplicationName());
		$this->assertTrue($appConfig->general()->isApplicationReplicatable());
	}
function testWeb() {
		$appConfig = $this->createFromFsPath('web.app.ini');

		$this->assertFalse($appConfig->web()->isResponseCachingEnabled());
		$this->assertFalse($appConfig->web()->isResponseBrowserCachingEnabled());
		$this->assertFalse($appConfig->web()->isResponseSendEtagAllowed());
		$this->assertFalse($appConfig->web()->isResponseSendLastModifiedAllowed());
		$this->assertFalse($appConfig->web()->isResponseServerPushAllowed());
		$this->assertTrue($appConfig->web()->isResponseContentSecurityPolicyEnabled());
		$this->assertFalse($appConfig->web()->isViewCachingEnabled());
		$this->assertEmpty($appConfig->web()->getDispatchTargetCryptAlgorithm());
	}

	function testRouting() {
		$appConfig = $this->createFromFsPath('routing.app.ini');

		$routingConfig = $appConfig->routing();
		$this->assertCount(2, $routingConfig->getMainControllerDefs());
		$this->assertCount(1, $routingConfig->getN2nLocales());

		$this->assertCount(1, $routingConfig->getRoutingRules());

		$this->assertCount(2, $appConfig->web()->getAllN2nLocales());
	}
    function testNoMail() {
        $appConfig = $this->createFromFsPath('nomail.app.ini');

        $this->assertFalse($appConfig->mail()->isSendingMailEnabled());
    }
    function testMail() {
        $appConfig = $this->createFromFsPath('mail.app.ini');

        $this->assertTrue($appConfig->mail()->isSendingMailEnabled());
        $this->assertEquals('info@myapp.test', $appConfig->mail()->getDefaultAddresser());
        $this->assertEquals('support@myapp.test', $appConfig->mail()->getSystemManagerAddress());
        $this->assertEquals('customer@myapp.test', $appConfig->mail()->getCustomerAddress());
        $this->assertContainsEquals('notification@myapp.test', $appConfig->mail()->getNotificationRecipientsAddresses());
    }
    function testMailSmtp() {
        $appConfig = $this->createFromFsPath('smtp.app.ini');

        $this->assertTrue($appConfig->mail()->isSendingMailEnabled());
        $this->assertEquals('ssl://smtp.myapp.test', $appConfig->mail()->getDefaultSmtpConfig()->getHost());
        $this->assertEquals('587', $appConfig->mail()->getDefaultSmtpConfig()->getPort());
        $this->assertEquals('ssl', $appConfig->mail()->getDefaultSmtpConfig()->getSecurityMode());
        $this->assertTrue($appConfig->mail()->getDefaultSmtpConfig()->doAuthenticate());
        $this->assertEquals('username', $appConfig->mail()->getDefaultSmtpConfig()->getUser());
        $this->assertEquals('pass', $appConfig->mail()->getDefaultSmtpConfig()->getPassword());
    }

    function testIo() {
        $appConfig = $this->createFromFsPath('io.app.ini');

        $this->assertEquals('0770', $appConfig->io()->getPublicDirPermission());
        $this->assertEquals('0660', $appConfig->io()->getPublicFilePermission());
        $this->assertEquals('0770', $appConfig->io()->getPrivateDirPermission());
        $this->assertEquals('0660', $appConfig->io()->getPrivateFilePermission());
    }
    function testError() {
        $appConfig = $this->createFromFsPath('error.app.ini');

        $this->assertFalse($appConfig->error()->isStrictAttitudeEnabled());
        $this->assertFalse($appConfig->error()->isDetectStartupErrorsEnabled());
        $this->assertFalse($appConfig->error()->isStartupDetectBadRequestsEnabled());
        $this->assertFalse($appConfig->error()->isLogSaveDetailInfoEnabled());
        $this->assertTrue($appConfig->error()->isLogSendMailEnabled());
        $this->assertTrue($appConfig->error()->isLogHandleStatusExceptionsEnabled());
		$this->assertFalse($appConfig->error()->isMonitorEnabled());
	}
    function testDatabase() {
        $appConfig = $this->createFromFsPath('database.app.ini');

		$persistenceUnitConfigs = $appConfig->db()->getPersistenceUnitConfigs();
		$this->assertCount(2, $persistenceUnitConfigs);
		$this->assertTrue(isset($persistenceUnitConfigs['default']));
		$this->assertTrue(isset($persistenceUnitConfigs['myapp']));
		$persistenceUnitConfig1 = $persistenceUnitConfigs['default'];
		$persistenceUnitConfig2 = $persistenceUnitConfigs['myapp'];

        $this->assertEquals('mysql:host=localhost;dbname=dbname', $persistenceUnitConfig1->getDsnUri());
        $this->assertEquals('dbuser', $persistenceUnitConfig1->getUser());
        $this->assertEquals('pass', $persistenceUnitConfig1->getPassword());
        $this->assertEquals('n2n\persistence\meta\impl\mysql\MysqlDialect', $persistenceUnitConfig1->getDialectClassName());
        $this->assertEquals('SERIALIZABLE', $persistenceUnitConfig1->getTransactionIsolationLevel());

		$this->assertEquals('path/to/ca.crt', $persistenceUnitConfig2->getSslCaCertificatePath());
		$this->assertEquals(false, $persistenceUnitConfig2->isSslVerify());
    }
	function testOrm() {
		$appConfig = $this->createFromFsPath('orm.app.ini');

		$this->assertEquals(['example\bo\Example'], $appConfig->orm()->getEntityClassNames());
		$this->assertEquals(['ent'], $appConfig->orm()->getEntityPropertyProviderClassNames());
		$this->assertEquals('namingStrat', $appConfig->orm()->getNamingStrategyClassName());
	}
    function testLocales() {
		$appConfig = $this->createFromFsPath('locales.app.ini');

		$this->assertEquals('fr', $appConfig->locale()->getFallbackN2nLocale());
		$this->assertEquals('it', $appConfig->locale()->getDefaultN2nLocale());
		$this->assertEquals('sp', $appConfig->locale()->getAdminN2nLocale());
    }

	function testLocaleConfigDefaults() {
		$n2nLocalesConfig = new N2nLocaleConfig();
		$this->assertEquals(N2nLocaleConfig::FALLBACK_LOCALE_ID_DEFAULT,
				$n2nLocalesConfig->getFallbackN2nLocale()->getId());
	}
    function testL10n() {
		$appConfig = $this->createFromFsPath('l10n.app.ini');

		$l10nStyles = $appConfig->l10n()->getL10nStyles();
		$this->assertCount(1, $l10nStyles);
		$this->assertTrue(isset($l10nStyles['de_CH']));
		$l10Style = $l10nStyles['de_CH'];
		
		$this->assertEquals('medium', $l10Style->geDefaultInputDateStyle());
		$this->assertEquals('short', $l10Style->getDefaultInputTimeStyle());
		$this->assertEquals('full', $l10Style->getDefaultDateStyle());
		$this->assertEquals('long', $l10Style->getDefaultTimeStyle());
		$this->assertEquals('{date} {time}', $l10Style->getDateTimeFormat());
    }
    function testPseudoL10n() {

		$appConfig = $this->createFromFsPath('pseudo_l10n.app.ini');

		$l10nStyles = $appConfig->pseudoL10n()->getL10nStyles();
		$this->assertCount(1, $l10nStyles);
		$this->assertTrue(isset($l10nStyles['de_CH']));
		$l10Style = $l10nStyles['de_CH'];
		
		$this->assertEquals('short', $l10Style->geDefaultInputDateStyle());
		$this->assertEquals('medium', $l10Style->getDefaultInputTimeStyle());
		
		$this->assertEquals('long', $l10Style->getDefaultDateStyle());
		$this->assertEquals('full', $l10Style->getDefaultTimeStyle());
		$this->assertEquals('{date} {time}', $l10Style->getDateTimeFormat());

		$l10nFormats = $appConfig->pseudoL10n()->getL10nFormats();
		$this->assertCount(1, $l10nFormats);
		$this->assertTrue(isset($l10nFormats['de_CH']));
		$l10Format = $l10nFormats['de_CH'];
		
		$this->assertEquals('d.m.Y', $l10Format->getDateInputPattern());
		$this->assertEquals('H:i:s', $l10Format->getTimeInputPattern());
		
		$this->assertEquals('d.m.Y', $l10Format->getDatePatterns()['short']);
		$this->assertEquals('d.M.Y', $l10Format->getDatePatterns()['medium']);
		$this->assertEquals('D d.M.Y', $l10Format->getDatePatterns()['long']);
		$this->assertEquals('l d.F.Y', $l10Format->getDatePatterns()['full']);
		
		$this->assertEquals('H:i', $l10Format->getTimePatterns()['short']);
		$this->assertEquals('H:i:s', $l10Format->getTimePatterns()['medium']);
		$this->assertEquals('H:i:s O', $l10Format->getTimePatterns()['long']);
		$this->assertEquals('H:i:s O e', $l10Format->getTimePatterns()['full']);
    }
}