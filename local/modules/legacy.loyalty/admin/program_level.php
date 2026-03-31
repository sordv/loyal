<?php

use Bitrix\Main\Localization\Loc;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

Loc::loadMessages($_SERVER["DOCUMENT_ROOT"]."/local/modules/legacy.loyalty/admin/program_level.php");

$APPLICATION->SetTitle(Loc::getMessage("LEGACY_LOYALTY_TYPE_LEVEL"));

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$aTabs = [
    ["DIV" => "rules", "TAB" => Loc::getMessage("LEGACY_LOYALTY_TAB_RULE"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_TAB_RULE")],
];

$tabControl = new CAdminTabControl("tabControl", $aTabs);
?>

<form method="post">
    <?php
    $tabControl->Begin();
    $tabControl->BeginNextTab();
    ?>
    <p>Тут будут правила уровней</p>
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