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
            "text" => "Раздел 1",
            "url" => "legacy_loyalty_page1.php",
        ],
        [
            "text" => "Раздел 2",
            "url" => "legacy_loyalty_page2.php",
        ],
    ]
];