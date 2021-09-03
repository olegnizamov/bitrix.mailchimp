<?php

use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager,
    Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if (class_exists("onizamov_mailchimp")) {
    return;
}

class onizamov_mailchimp extends CModule
{

    function __construct()
    {
        $arModuleVersion = include(dirname(__FILE__) . "/version.php");
        $this->MODULE_ID = $arModuleVersion["MODULE_ID"];
        $this->MODULE_NAME = $arModuleVersion["MODULE_NAME"];
        $this->MODULE_DESCRIPTION = $arModuleVersion["MODULE_DESCR"];
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->PARTNER_NAME = $arModuleVersion["PARTNER_NAME"];
        $this->PARTNER_URI = $arModuleVersion["PARTNER_URI"];
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (!CheckVersion(ModuleManager::getVersion('main'), '14.0.0')) {
            $APPLICATION->ThrowException('Ваша система не поддерживает D7');
        } else {
            ModuleManager::RegisterModule($this->MODULE_ID);
        }

        $eventManager = EventManager::getInstance();
        $eventManager->registerEventHandler(
            'sender',
            'GroupOnAfterAdd',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\SegmentEvents',
            'onAfterAdd'
        );
        $eventManager->registerEventHandler(
            'sender',
            'GroupOnAfterUpdate',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\SegmentEvents',
            'onAfterUpdate'
        );
        $eventManager->registerEventHandler(
            'sender',
            'GroupOnAfterDelete',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\SegmentEvents',
            'onAfterDelete'
        );
        $eventManager->registerEventHandler(
            'sender',
            '\Bitrix\Sender\GroupConnector::OnAfterAdd',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\GroupConnectorTable',
            'OnAfterAdd'
        );

        $eventManager->registerEventHandler(
            'crm',
            'OnAfterCrmContactDelete',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\ContactEvents',
            'onCrmContactDelete'
        );
        $eventManager->registerEventHandler(
            'crm',
            'OnAfterCrmCompanyDelete',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\CompanyEvents',
            'onCrmCompanyDelete'
        );
        $eventManager->registerEventHandler(
            'crm',
            'OnAfterCrmCompanyUpdate',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\CompanyEvents',
            'onCrmCompanyUpdate'
        );


        $APPLICATION->IncludeAdminFile(
            "Установка модуля" . $this->MODULE_ID,
            dirname(__FILE__) . "/step.php"
        );
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        $eventManager = EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            'sender',
            'GroupOnAfterAdd',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\SegmentEvents',
            'onAfterAdd'
        );
        $eventManager->unRegisterEventHandler(
            'sender',
            'GroupOnAfterUpdate',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\SegmentEvents',
            'onAfterUpdate'
        );
        $eventManager->unRegisterEventHandler(
            'sender',
            'GroupOnAfterDelete',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\SegmentEvents',
            'onAfterDelete'
        );
        $eventManager->unRegisterEventHandler(
            'sender',
            '\Bitrix\Sender\GroupConnector::OnAfterAdd',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\GroupConnectorTable',
            'OnAfterAdd'
        );
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnAfterCrmContactDelete',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\ContactEvents',
            'onCrmContactDelete'
        );
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnAfterCrmCompanyDelete',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\CompanyEvents',
            'onCrmCompanyDelete'
        );
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnAfterCrmCompanyUpdate',
            'onizamov.mailchimp',
            '\\Onizamov\\MailChimp\\Events\\CompanyEvents',
            'onCrmCompanyUpdate'
        );

        ModuleManager::UnRegisterModule($this->MODULE_ID);
        $APPLICATION->IncludeAdminFile(
            "Деинсталляция модуля " . $this->MODULE_ID,
            dirname(__FILE__) . "/unstep.php"
        );
    }
}
