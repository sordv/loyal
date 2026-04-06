<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

Loc::loadMessages($_SERVER["DOCUMENT_ROOT"]."/local/modules/legacy.loyalty/admin/program_bonus.php");

$APPLICATION->SetTitle(Loc::getMessage("LEGACY_LOYALTY_TYPE_BONUS"));

if (!Loader::includeModule('legacy.loyalty')) {
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
    echo Loc::getMessage("LEGACY_LOYALTY_MODULE_NOT_INSTALLED");
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    return;
}

$request = Application::getInstance()->getContext()->getRequest();
$message = null;

if ($request->isPost() && check_bitrix_sessid()) {
    Option::set("legacy.loyalty", "bonus_name", $request->getPost("bonus_name"));
    Option::set("legacy.loyalty", "bonus_lifetime", $request->getPost("bonus_lifetime"));
    Option::set("legacy.loyalty", "bonus_delay", $request->getPost("bonus_delay"));

    if ($request->getPost("apply")) {
        $message = ["TYPE" => "OK", "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_SAVED")];
    } else {
        LocalRedirect("menu_program.php");
    }
}

$bonusName = Option::get("legacy.loyalty", "bonus_name", "Бонусы");
$bonusLifetime = Option::get("legacy.loyalty", "bonus_lifetime", "30");
$bonusDelay = Option::get("legacy.loyalty", "bonus_delay", "0");

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if ($message) {
    CAdminMessage::ShowMessage($message);
}

$aTabs = [
    ["DIV" => "rules_add", "TAB" => Loc::getMessage("LEGACY_LOYALTY_TAB_ADD"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_TAB_ADD")],
    ["DIV" => "rules_spend", "TAB" => Loc::getMessage("LEGACY_LOYALTY_TAB_SPEND"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_TAB_SPEND")],
    ["DIV" => "settings", "TAB" => Loc::getMessage("LEGACY_LOYALTY_TAB_SETTINGS"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_TAB_SETTINGS")],
];

$tabControl = new CAdminTabControl("tabControl", $aTabs);

?>

<form method="post">
    <?=bitrix_sessid_post()?>
    <?php
    $tabControl->Begin();
    $tabControl->BeginNextTab();
    ?>
    <p>Тут будут правила начисления</p>
    <?php
    $tabControl->EndTab();
    $tabControl->BeginNextTab();
    ?>
    <p>Тут будут правила списания</p>
    <?php
    $tabControl->EndTab();
    $tabControl->BeginNextTab();
    ?>
    <tr>
        <td><?=Loc::getMessage("LEGACY_LOYALTY_BONUS_NAME")?></td>
        <td><input type="text" name="bonus_name" value="<?=htmlspecialcharsbx($bonusName)?>"></td>
    </tr>
    <tr>
        <td><?=Loc::getMessage("LEGACY_LOYALTY_BONUS_LIFE")?></td>
        <td><input type="number" name="bonus_lifetime" value="<?=htmlspecialcharsbx($bonusLifetime)?>"></td>
    </tr>
    <tr>
        <td><?=Loc::getMessage("LEGACY_LOYALTY_BONUS_DELAY")?></td>
        <td><input type="number" name="bonus_delay" value="<?=htmlspecialcharsbx($bonusDelay)?>"></td>
    </tr>
    <?php
    $tabControl->EndTab();

    $tabControl->Buttons([
        "btnSave" => true,
        "btnApply" => true,
        "btnCancel" => true,
        "back_url" => "menu_program.php"
    ]);

    $tabControl->End();
    ?>
</form>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");