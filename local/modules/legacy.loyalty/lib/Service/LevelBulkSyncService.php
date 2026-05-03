<?php

namespace Legacy\Loyalty\Service;

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

/**
 * Массовый пересчёт уровней: ежедневный агент + немедленный пересчёт из LevelRuleSyncHandler при изменении правил.
 * Агенты через \CAgent.
 */
class LevelBulkSyncService {
    public const AGENT_DAILY = '\Legacy\Loyalty\Service\LevelBulkSyncService::runDailyAgent();';

    /**
     * Ближайший запуск в заданное время суток (локальное время PHP; для GMT+3 выставьте date.timezone = Europe/Moscow).
     */
    private static function nextRunDateTimeFull(int $hour, int $minute = 0): string {
        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));
        $ts = mktime($hour, $minute, 0, (int)date('n'), (int)date('j'), (int)date('Y'));
        if ($ts <= time()) {
            $ts += 86400;
        }

        return ConvertTimeStamp($ts, 'FULL');
    }

    public static function nextDailyAgentRunFull(): string {
        return self::nextRunDateTimeFull(3, 0);
    }

    public static function registerDailyAgent(): void {
        self::unregisterDailyAgent();

        \CAgent::AddAgent(
            self::AGENT_DAILY,
            'legacy.loyalty',
            'N',
            86400,
            '',
            'Y',
            self::nextDailyAgentRunFull(),
            30
        );
    }

    public static function unregisterDailyAgent(): void {
        \CAgent::RemoveAgent(self::AGENT_DAILY, 'legacy.loyalty');
    }

    /**
     * Снятие всех агентов модуля (без автозагрузки класса сервиса — см. install/index.php).
     */
    public static function unregisterAllAgents(): void {
        self::unregisterDailyAgent();
    }

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
                LevelService::syncUserLevelFromRules($userId);
            }

            $lastId = max($ids);
        }
    }
}
