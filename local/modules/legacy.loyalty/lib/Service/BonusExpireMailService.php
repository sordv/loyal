<?php

namespace Legacy\Loyalty\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;

/**
 * Ежедневная рассылка предупреждений об истечении бонусов через N дней.
 * Для каждого N: строки в b_legacy_loyalty_bonus_user, где дата истечения = сегодня + N календарных дней.
 */
class BonusExpireMailService {
    public const AGENT_RUN = '\Legacy\Loyalty\Service\BonusExpireMailService::runDailyAgent();';

    public static function registerAgent(): void {
        self::unregisterAgent();

        $next = LevelBulkSyncService::nextDailyAgentRunFull();

        \CAgent::AddAgent(
            self::AGENT_RUN,
            'legacy.loyalty',
            'N',
            86400,
            '',
            'Y',
            $next,
            35
        );
    }

    public static function unregisterAgent(): void {
        \CAgent::RemoveAgent(self::AGENT_RUN, 'legacy.loyalty');
    }

    public static function runDailyAgent(): string {
        if (!ProgramService::isBonusEnabled()) {
            return self::AGENT_RUN;
        }

        if (Option::get('legacy.loyalty', 'mail_bonus_expire', 'N') !== 'Y') {
            return self::AGENT_RUN;
        }

        $daysList = self::parseDaysOption(Option::get('legacy.loyalty', 'mail_bonus_expire_days', '7,3,1'));
        if ($daysList === []) {
            return self::AGENT_RUN;
        }

        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $today = new \DateTimeImmutable('today');

        foreach ($daysList as $n) {
            if ($n <= 0) {
                continue;
            }
            $expireOn = $today->modify('+' . $n . ' days')->format('Y-m-d');
            $expireSql = $sqlHelper->forSql($expireOn);

            $rows = $connection->query("
                SELECT USER_ID, EXPIRE_AT, SUM(AMOUNT) AS TOTAL
                FROM b_legacy_loyalty_bonus_user
                WHERE EXPIRE_AT IS NOT NULL
                  AND EXPIRE_AT = '{$expireSql}'
                  AND AMOUNT > 0
                GROUP BY USER_ID, EXPIRE_AT
            ");

            while ($row = $rows->fetch()) {
                $userId = (int)$row['USER_ID'];
                $expireAt = (string)$row['EXPIRE_AT'];
                $total = (int)$row['TOTAL'];
                if ($userId <= 0 || $total <= 0) {
                    continue;
                }

                try {
                    LoyaltyMailService::notifyBonusExpireWarning($userId, $total, $expireAt, $n);
                } catch (\Throwable $e) {
                }
            }
        }

        return self::AGENT_RUN;
    }

    /**
     * @return int[]
     */
    public static function parseDaysOption(string $raw): array {
        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }
        $out = [];
        foreach ($parts as $p) {
            $v = (int)$p;
            if ($v > 0) {
                $out[$v] = $v;
            }
        }

        return array_values($out);
    }
}
