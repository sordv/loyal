<?php

namespace Legacy\Loyalty\Service;

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

class LevelBulkSyncService {
    public const AGENT_DAILY = '\Legacy\Loyalty\Service\LevelBulkSyncService::runDailyAgent();';

    public static function runDailyAgent(): string {
        self::syncAllRegisteredUsers();
        return self::AGENT_DAILY;
    }

    public static function syncAllRegisteredUsers(): void {
        if (!Loader::includeModule('main')) {
            return;
        }

        if (!ProgramService::isLevelEnabled()) {
            return;
        }

        if (!Loader::includeModule('sale')) {
            return;
        }

        $lastId = 0;
        while (true) {
            $batch = UserTable::getList([
                'select' => ['ID'],
                'filter' => ['>ID' => $lastId],
                'order' => ['ID' => 'ASC'],
                'limit' => 500,
            ]);

            $ids = [];
            while ($row = $batch->fetch()) {
                $ids[] = (int)$row['ID'];
            }

            if ($ids === []) {
                break;
            }

            foreach ($ids as $userId) {
                LevelService::syncUserLevel($userId);
            }

            $lastId = max($ids);
        }
    }
}
