<?php

use \Bitrix\Main\Localization\Loc;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

Loc::loadMessages($_SERVER["DOCUMENT_ROOT"]."/local/modules/legacy.loyalty/admin/menu_history.php");

$APPLICATION->SetTitle(Loc::getMessage("LEGACY_LOYALTY_MENU_HISTORY"));

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<p><?= Loc::getMessage("LEGACY_LOYALTY_HISTORY_DESC") ?></p>

<a href="/bitrix/admin/perfmon_table.php?lang=ru&table_name=b_legacy_loyalty_bonus_user">
    <?= Loc::getMessage("LEGACY_LOYALTY_TABLE_BONUS_USER") ?>
</a><br><br>

<a href="/bitrix/admin/perfmon_table.php?lang=ru&table_name=b_legacy_loyalty_bonus_history">
    <?= Loc::getMessage("LEGACY_LOYALTY_TABLE_BONUS_HISTORY") ?>
</a><br><br>

<a href="/bitrix/admin/perfmon_table.php?lang=ru&table_name=b_legacy_loyalty_level_history">
    <?= Loc::getMessage("LEGACY_LOYALTY_TABLE_LEVEL_HISTORY") ?>
</a><br><br>

<a href="/bitrix/admin/perfmon_table.php?lang=ru&table_name=b_legacy_loyalty_event_history">
    <?= Loc::getMessage("LEGACY_LOYALTY_TABLE_EVENT_HISTORY") ?>
</a><br><br>

<a href="/bitrix/admin/perfmon_table.php?lang=ru&table_name=b_legacy_loyalty_system_log">
    <?= Loc::getMessage("LEGACY_LOYALTY_TABLE_SYSTEM_LOG") ?>
</a>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");