<?php

namespace Legacy\Loyalty\EventHandler;

use Bitrix\Main\Loader;
use Legacy\Loyalty\Service\LevelBulkSyncService;

class LevelRuleSyncHandler {
    public static function onAfterRuleChanged(\Bitrix\Main\Entity\Event $event): void {
        if (!Loader::includeModule('legacy.loyalty')) {
            return;
        }

        LevelBulkSyncService::syncAllRegisteredUsers();
    }
}
