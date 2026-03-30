<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

return [
    "parent_menu" => "global_menu_services",
    "section" => "legacy_loyalty",
    "sort" => 500,
    "text" => Loc::getMessage("LEGACY_LOYALTY_MENU"),
    "title" => Loc::getMessage("LEGACY_LOYALTY_MENU"),
    "icon" => "default_menu_icon",
    "page_icon" => "default_page_icon",
    "items_id" => "menu_legacy_loyalty",
    "items" => [
        [
            "text" => Loc::getMessage("LEGACY_LOYALTY_MENU_PROGRAM"),
            "url" => "menu_program.php",
        ],
        [
            "text" => Loc::getMessage("LEGACY_LOYALTY_MENU_USER"),
            "url" => "menu_user.php",
        ],
        [
            "text" => Loc::getMessage("LEGACY_LOYALTY_MENU_HISTORY"),
            "url" => "menu_history.php",
        ],
        [
            "text" => Loc::getMessage("LEGACY_LOYALTY_MENU_MANUAL"),
            "url" => "menu_manual.php",
        ],
    ]
];