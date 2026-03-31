<?php

use \Bitrix\Main\ModuleManager;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Application;

Loc::loadMessages(__FILE__);

Class legacy_loyalty extends CModule
{
    function __construct()
    {
        $arModuleVersion = array();
        include(__DIR__."/version.php");

        $this->MODULE_ID = "legacy.loyalty";
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = Loc::getMessage("LEGACY_LOYALTY_NAME");
        $this->MODULE_DESCRIPTION = Loc::getMessage("LEGACY_LOYALTY_DESC");
        $this->PARTNER_NAME = Loc::getMessage("LEGACY_LOYALTY_PARTNER_NAME");
        $this->PARTNER_URL = Loc::getMessage("LEGACY_LOYALTY_PARTNER_URL");
        $this->MODULE_SORT = 1;
        $this->SHOW_SUPER_ADMIN_GROUP_RIGHTS = 'Y';
        $this->MODULE_GROUP_RIGHTS = 'Y';
    }

    function InstallFiles()
    {
        CopyDirFiles(
            __DIR__ . "/../admin",
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin",
            true,
            true
        );

        return true;
    }

    function InstallDB()
    {
        global $DB;

        $this->errors = $DB->RunSQLBatch(__DIR__ . "/db/mysql/install.sql");

        if (!empty($this->errors)) {
            return false;
        }

        return true;
    }

    function InstallEvents()
    {
        return true;
    }

    function UnInstallFiles()
    {
        DeleteDirFiles(
            __DIR__ . "/../admin",
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin"
        );

        return true;
    }

    function UnInstallDB()
    {
        global $DB;

        $DB->RunSQLBatch(__DIR__ . "/db/mysql/uninstall.sql");

        return true;
    }

    function UnInstallEvents()
    {
        return true;
    }

    function DoInstall()
    {
        global $APPLICATION;

        $this->InstallDB();
        //$this->InstallEvents();
        $this->InstallFiles();

        ModuleManager::registerModule($this->MODULE_ID);
        $APPLICATION->IncludeAdminFile(Loc::getMessage("LEGACY_LOYALTY_INSTALL_TITLE"), __DIR__ . "/step.php");
    }

    function DoUninstall()
    {
        global $APPLICATION;
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();

        if($request["step"]<2)
        {
            $APPLICATION->IncludeAdminFile(Loc::getMessage("LEGACY_LOYALTY_UNINSTALL_TITLE"), __DIR__ . "/unstep1.php");
        }
        elseif($request["step"]==2)
        {
            $this->UnInstallFiles();
            //$this->UnInstallEvents();

            if($request["savedata"] != "Y")
                $this->UnInstallDB();

            ModuleManager::unRegisterModule($this->MODULE_ID);
            $APPLICATION->IncludeAdminFile(Loc::getMessage("LEGACY_LOYALTY_UNINSTALL_TITLE"), __DIR__. "/unstep2.php");
        }
    }
}