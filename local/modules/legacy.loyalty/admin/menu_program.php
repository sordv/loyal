<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Legacy\Loyalty\ProgramTable;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

Loc::loadMessages($_SERVER["DOCUMENT_ROOT"]."/local/modules/legacy.loyalty/admin/menu_program.php");

$APPLICATION->SetTitle(Loc::getMessage("LEGACY_LOYALTY_MENU_PROGRAM"));

// Подключаем модуль
if (!Loader::includeModule('legacy.loyalty')) {
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
    echo Loc::getMessage("LEGACY_LOYALTY_MODULE_NOT_INSTALLED");
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    return;
}

$request = Application::getInstance()->getContext()->getRequest();

// ======================
// ACTIONS
// ======================
if (check_bitrix_sessid())
{
    // CREATE
    if ($request->get('action') === 'add_program')
    {
        $type = $request->get('type');

        $exists = ProgramTable::getList([
            'filter' => ['TYPE' => $type]
        ])->fetch();

        $message = null;

        if (!in_array($type, ['bonus', 'level', 'event']))
        {
            $message = [
                "TYPE" => "ERROR",
                "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_ERROR_TYPE")
            ];
        }
        elseif ($exists)
        {
            $message = [
                "TYPE" => "ERROR",
                "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_ERROR_EXISTS")
            ];
        }
        else
        {
            ProgramTable::add([
                'TYPE' => $type
            ]);

            $message = [
                "TYPE" => "OK",
                "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_SUCCESS_ADD")
            ];
        }
    }

    // DELETE
    if ($request->get('action') === 'delete_program')
    {
        $id = (int)$request->get('id');

        if ($id > 0) {
            ProgramTable::delete($id);
        }
    }
}

// ======================
// ДАННЫЕ
// ======================
$programTypes = [
    'bonus' => Loc::getMessage("LEGACY_LOYALTY_TYPE_BONUS"),
    'level' => Loc::getMessage("LEGACY_LOYALTY_TYPE_LEVEL"),
    'event' => Loc::getMessage("LEGACY_LOYALTY_TYPE_EVENT"),
];

$result = ProgramTable::getList([
    'order' => ['ID' => 'ASC']
]);

// ======================
// CAdminList
// ======================
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$sTableID = "tbl_loyalty_programs";
$oSort = new CAdminSorting($sTableID, "ID", "asc");
$lAdmin = new CAdminList($sTableID, $oSort);

// Заголовки
$lAdmin->AddHeaders([
    ["id" => "ID", "content" => Loc::getMessage("LEGACY_LOYALTY_ID"), "sort" => "ID", "default" => true],
    ["id" => "TYPE", "content" => Loc::getMessage("LEGACY_LOYALTY_TYPE"), "default" => true],
]);

// Заполняем строки
while ($program = $result->fetch())
{
    $row = &$lAdmin->AddRow($program['ID'], $program);

    $row->AddField("ID", $program['ID']);
    $row->AddField("TYPE", htmlspecialcharsbx($programTypes[$program['TYPE']] ?? $program['TYPE']));

    // Действия
    $actions = [];

    $actions[] = [
        "TEXT" => Loc::getMessage("LEGACY_LOYALTY_EDIT"),
        "ACTION" => "",
        "DEFAULT" => true
    ];

    $actions[] = [
        "TEXT" => Loc::getMessage("LEGACY_LOYALTY_DELETE"),
        "ACTION" => "if(confirm('".Loc::getMessage("LEGACY_LOYALTY_CONFIRM_DELETE")."')) window.location='?action=delete_program&id=".$program['ID']."&".bitrix_sessid_get()."'"
    ];

    $row->AddActions($actions);
}

// ======================
// КНОПКА СОЗДАНИЯ
// ======================
$aContext = [
    [
        "TEXT" => "Новая программа",
        "TITLE" => "Создать программу",
        "MENU" => [
            [
                "TEXT" => $programTypes['bonus'],
                "ACTION" => "window.location='?action=add_program&type=bonus&".bitrix_sessid_get()."'"
            ],
            [
                "TEXT" => $programTypes['level'],
                "ACTION" => "window.location='?action=add_program&type=level&".bitrix_sessid_get()."'"
            ],
            [
                "TEXT" => $programTypes['event'],
                "ACTION" => "window.location='?action=add_program&type=event&".bitrix_sessid_get()."'"
            ],
        ]
    ]
];

$lAdmin->AddAdminContextMenu($aContext);

// ======================
// ВЫВОД
// ======================
$lAdmin->CheckListMode();
if ($message)
{
    CAdminMessage::ShowMessage($message);
}
$lAdmin->DisplayList();

?>

<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");