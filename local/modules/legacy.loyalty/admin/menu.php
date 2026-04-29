<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

return [
    "parent_menu" => "global_menu_marketing",
    "section" => "legacy_loyalty",
    "sort" => 500,
    "text" => Loc::getMessage("LEGACY_LOYALTY_MENU"),
    "title" => Loc::getMessage("LEGACY_LOYALTY_MENU"),
    "icon" => "sale_menu_icon",
    "page_icon" => "sale_page_icon",
    "items_id" => "menu_legacy_loyalty",
    "items" => [
        [
            "text" => Loc::getMessage("LEGACY_LOYALTY_MENU_PROGRAM"),
            "url" => "menu_program.php",
            "more_url" => [
                "program_bonus.php",
                "program_level.php",
                "program_event.php",
                "bonus_rule_edit.php",
                "level_rule_edit.php",
            ],
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