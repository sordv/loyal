<?php

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
Loc::loadMessages($_SERVER["DOCUMENT_ROOT"] . "/local/modules/legacy.loyalty/admin/program_event.php");

$APPLICATION->SetTitle(Loc::getMessage("LEGACY_LOYALTY_TYPE_EVENT"));

$request = Application::getInstance()->getContext()->getRequest();
if ($request->isPost() && check_bitrix_sessid() && $request->getPost('save_settings')) {
    LocalRedirect('menu_program.php?lang=' . LANG);
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$aTabs = [
    ["DIV" => "rules", "TAB" => Loc::getMessage("LEGACY_LOYALTY_TAB_RULE"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_TAB_RULE")],
    ["DIV" => "settings", "TAB" => Loc::getMessage("LEGACY_LOYALTY_TAB_SETTINGS"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_TAB_SETTINGS")],
];

$tabControl = new CAdminTabControl("tabControl", $aTabs);
?>

<form method="post">
    <?= bitrix_sessid_post() ?>
    <?php
    $tabControl->Begin();
    $tabControl->BeginNextTab();
    ?>
    <p>Тут будут правила событий</p>
    <?php
    $tabControl->EndTab();
    $tabControl->BeginNextTab();
    ?>

    <div class="leglol-settings-actions">
        <p>Тут будут базовые настройки</p>
        <input type="hidden" name="save_settings" value="Y">
        <input type="submit" name="save" value="<?= htmlspecialcharsbx(Loc::getMessage('LEGACY_LOYALTY_BTN_SAVE_SETTINGS')) ?>" class="adm-btn-save">
    </div>

    <?php
    $tabControl->EndTab();
    $tabControl->End();
    ?>
    <div class="leglol-program-footer-nav">
        <a class="adm-btn" href="menu_program.php?lang=<?= LANG ?>"><?= htmlspecialcharsbx(Loc::getMessage('LEGACY_LOYALTY_BTN_BACK')) ?></a>
    </div>
</form>

<style>
    .leglol-settings-actions {
        padding-top: 16px;
    }

    .leglol-program-footer-nav {
        margin-top: 16px;
        padding-top: 12px;
        border-top: 1px solid #e0e0e0;
    }
</style>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");