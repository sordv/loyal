<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Legacy\Loyalty\ProgramTable;

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

if (check_bitrix_sessid())
{
    if ($request->get('action') === 'toggle')
    {
        $id = (int)$request->get('id');

        if ($id > 0)
        {
            $program = ProgramTable::getById($id)->fetch();

            if ($program)
            {
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
    ["id" => "ID", "content" => Loc::getMessage("LEGACY_LOYALTY_PROGRAM_ID"), "sort" => "ID", "default" => true],
    ["id" => "TYPE", "content" => Loc::getMessage("LEGACY_LOYALTY_PROGRAM_TYPE"), "default" => true],
    ["id" => "ACTIVE", "content" => Loc::getMessage("LEGACY_LOYALTY_PROGRAM_STATUS"), "default" => true],
]);

while ($program = $result->fetch())
{
    $row = &$lAdmin->AddRow($program['ID'], $program);

    $row->AddField("ID", $program['ID']);

    $row->AddField(
        "TYPE",
        htmlspecialcharsbx($programTypes[$program['TYPE']] ?? $program['NAME'])
    );

    $row->AddViewField(
        "ACTIVE",
        $program['ACTIVE'] === 'Y'
            ? '<span style="color:green;">Включена</span>'
            : '<span style="color:red;">Выключена</span>'
    );

    $actions = [];

    $actions[] = [
        "TEXT" => $program['ACTIVE'] === 'Y' ? Loc::getMessage("LEGACY_LOYALTY_TURN_OFF") : Loc::getMessage("LEGACY_LOYALTY_TURN_ON"),
        "ACTION" => "window.location='?action=toggle&id=".$program['ID']."&".bitrix_sessid_get()."'",
        "DEFAULT" => true
    ];

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

    $actions[] = [
        "TEXT" => Loc::getMessage("LEGACY_LOYALTY_EDIT"),
        "ACTION" => "window.location='".$editUrl."?id=".$program['ID']."'"
    ];

    $row->AddActions($actions);
}

$lAdmin->AddAdminContextMenu([]);
$lAdmin->CheckListMode();

if ($message)
{
    CAdminMessage::ShowMessage($message);
}

$lAdmin->DisplayList();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");