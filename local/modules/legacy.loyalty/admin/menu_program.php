<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Legacy\Loyalty\Tables\ProgramTable;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

Loc::loadMessages($_SERVER["DOCUMENT_ROOT"]."/local/modules/legacy.loyalty/admin/menu_program.php");

$APPLICATION->SetTitle(Loc::getMessage("LEGACY_LOYALTY_MENU_PROGRAM"));

if (!Loader::includeModule('legacy.loyalty')) {
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
    echo Loc::getMessage("LEGACY_LOYALTY_MODULE_NOT_INSTALLED");
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    return;
}

$request = Application::getInstance()->getContext()->getRequest();
$message = null;

if (check_bitrix_sessid()) {
    if ($request->get('action') === 'toggle') {
        $id = (int)$request->get('id');

        if ($id > 0) {
            $program = ProgramTable::getById($id)->fetch();

            if ($program) {
                $newStatus = ($program['ACTIVE'] === 'Y') ? 'N' : 'Y';

                ProgramTable::update($id, [
                    'ACTIVE' => $newStatus
                ]);

                $message = [
                    "TYPE" => "OK",
                    "MESSAGE" => ($newStatus === 'Y') ? "Программа включена" : "Программа выключена"
                ];
            }
        }
    }
}

$programTypes = [
    'bonus' => Loc::getMessage("LEGACY_LOYALTY_TYPE_BONUS"),
    'level' => Loc::getMessage("LEGACY_LOYALTY_TYPE_LEVEL"),
    'event' => Loc::getMessage("LEGACY_LOYALTY_TYPE_EVENT"),
];

$result = ProgramTable::getList([
    'order' => ['ID' => 'ASC']
]);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$sTableID = "tbl_loyalty_programs";
$oSort = new CAdminSorting($sTableID, "ID", "asc");
$lAdmin = new CAdminList($sTableID, $oSort);

$lAdmin->AddHeaders([
    ["id" => "TOGGLE", "content" => Loc::getMessage("LEGACY_LOYALTY_PROGRAM_TOGGLE"), "default" => true],
    ["id" => "ID", "content" => Loc::getMessage("LEGACY_LOYALTY_PROGRAM_ID"), "sort" => "ID", "default" => true],
    ["id" => "NAME", "content" => Loc::getMessage("LEGACY_LOYALTY_PROGRAM_TYPE"), "default" => true],
]);

while ($program = $result->fetch()) {
    $row = &$lAdmin->AddRow($program['ID'], $program);

    $toggleUrl = htmlspecialcharsbx(
        '?action=toggle&id=' . (int)$program['ID'] . '&' . bitrix_sessid_get()
    );

    $toggleTitle = htmlspecialcharsbx(Loc::getMessage("LEGACY_LOYALTY_PROGRAM_TOGGLE_TITLE"));

    $checkboxHtml =
        '<label class="leglol-prog-toggle" title="' . $toggleTitle . '">'
        . '<input type="checkbox"'
        . ($program['ACTIVE'] === 'Y' ? ' checked' : '')
        . ' onchange="window.location.href=\'' . $toggleUrl . '\'">'
        . '</label>';

    $row->AddViewField("TOGGLE", $checkboxHtml);

    $row->AddField("ID", $program['ID']);

    $editUrl = '';
    switch ($program['TYPE']) {
        case 'bonus':
            $editUrl = 'program_bonus.php';
            break;
        case 'level':
            $editUrl = 'program_level.php';
            break;
        case 'event':
            $editUrl = 'program_event.php';
            break;
    }

    $nameText = $programTypes[$program['TYPE']] ?? $program['NAME'];

    if ($editUrl !== '') {
        $nameHtml = '<a href="' . htmlspecialcharsbx($editUrl . '?id=' . (int)$program['ID']) . '">'
            . htmlspecialcharsbx($nameText) . '</a>';
    } else {
        $nameHtml = htmlspecialcharsbx($nameText);
    }

    $row->AddViewField("NAME", $nameHtml);
}

$lAdmin->AddAdminContextMenu([]);
$lAdmin->CheckListMode();

if ($message) {
    CAdminMessage::ShowMessage($message);
}

$lAdmin->DisplayList();

?>
<style>
    .leglol-prog-toggle {
        display: inline-flex;
        align-items: center;
        cursor: pointer;
        margin: 0;
    }

    .leglol-prog-toggle input[type="checkbox"] {
        margin: 0;
        cursor: pointer;
    }
</style>
<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
