<?php

namespace Legacy\Loyalty\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Legacy\Loyalty\Tables\EventHistoryTable;
use Legacy\Loyalty\Tables\EventRuleTable;

final class EventService {
    public const AGENT_DAILY = '\Legacy\Loyalty\Service\EventService::runDailyAgent();';

    public static function runDailyAgent(): string {
        self::runForToday(null);
        return self::AGENT_DAILY;
    }

    public static function runForToday(?int $onlyUserId = null): array {
        if (!Loader::includeModule('main')) {
            return ['processedUsers' => 0, 'processedRules' => 0, 'awards' => 0];
        }
        if (!ProgramService::isEventEnabled()) {
            return ['processedUsers' => 0, 'processedRules' => 0, 'awards' => 0];
        }

        if (!ProgramService::isBonusEnabled()) {
            return ['processedUsers' => 0, 'processedRules' => 0, 'awards' => 0];
        }

        $todayYmd = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $todayMd = (new \DateTimeImmutable('today'))->format('m-d');

        $rules = EventRuleTable::getList([
            'filter' => ['=ACTIVE' => 'Y'],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ])->fetchAll();

        if ($rules === []) {
            return ['processedUsers' => 0, 'processedRules' => 0, 'awards' => 0];
        }

        $users = self::collectUsers($onlyUserId);
        if ($users === []) {
            return ['processedUsers' => 0, 'processedRules' => count($rules), 'awards' => 0];
        }

        $awards = 0;
        foreach ($users as $userId => $user) {
            foreach ($rules as $rule) {
                if (!self::ruleTriggersToday($rule, $user, $todayMd)) {
                    continue;
                }
                $amount = self::extractBonusAmount($rule['PRIVILEGES'] ?? null);
                if ($amount <= 0) {
                    continue;
                }

                if (!self::canAwardByFireMode($userId, $rule, $todayYmd)) {
                    continue;
                }

                BonusService::addBonus($userId, $amount, (int)$rule['ID'], 'eventsystem');
                self::addHistory($userId, (int)$rule['ID'], $todayYmd, $amount);
                $awards++;
            }
        }

        return [
            'processedUsers' => count($users),
            'processedRules' => count($rules),
            'awards' => $awards,
        ];
    }

    private static function collectUsers(?int $onlyUserId): array {
        $out = [];

        if ($onlyUserId !== null) {
            $onlyUserId = (int)$onlyUserId;
            if ($onlyUserId <= 0) {
                return [];
            }
            $row = UserTable::getList([
                'filter' => ['=ID' => $onlyUserId],
                'select' => ['ID', 'DATE_REGISTER', 'PERSONAL_BIRTHDAY'],
                'limit' => 1,
            ])->fetch();
            if ($row && (int)$row['ID'] > 0) {
                $out[(int)$row['ID']] = [
                    'DATE_REGISTER' => $row['DATE_REGISTER'] ?? null,
                    'PERSONAL_BIRTHDAY' => $row['PERSONAL_BIRTHDAY'] ?? null,
                ];
            }
            return $out;
        }

        $lastId = 0;
        while (true) {
            $batch = UserTable::getList([
                'filter' => ['>ID' => $lastId],
                'order' => ['ID' => 'ASC'],
                'select' => ['ID', 'DATE_REGISTER', 'PERSONAL_BIRTHDAY'],
                'limit' => 500,
            ]);
            $ids = [];
            while ($row = $batch->fetch()) {
                $id = (int)$row['ID'];
                if ($id <= 0) {
                    continue;
                }
                $out[$id] = [
                    'DATE_REGISTER' => $row['DATE_REGISTER'] ?? null,
                    'PERSONAL_BIRTHDAY' => $row['PERSONAL_BIRTHDAY'] ?? null,
                ];
                $ids[] = $id;
            }
            if ($ids === []) {
                break;
            }
            $lastId = max($ids);
        }

        return $out;
    }

    private static function ruleTriggersToday(array $rule, array $user, string $todayMd): bool {
        $value = (string)($rule['VALUE'] ?? '');
        $cond = $rule['CONDITIONS'] ?? [];
        if (!is_array($cond)) {
            $cond = [];
        }

        if ($value === 'date') {
            $md = (string)($cond['md'] ?? '');
            return $md !== '' && $md === $todayMd;
        }

        if ($value === 'day') {
            $type = (string)($cond['type'] ?? '');
            if ($type === 'birthday') {
                $md = self::extractMonthDay($user['PERSONAL_BIRTHDAY'] ?? null);
                return $md !== '' && $md === $todayMd;
            }
            if ($type === 'registration') {
                $md = self::extractMonthDay($user['DATE_REGISTER'] ?? null);
                return $md !== '' && $md === $todayMd;
            }
        }

        return false;
    }

    private static function extractMonthDay($value): string {
        if ($value instanceof DateTime) {
            return $value->format('m-d');
        }
        if ($value instanceof Date) {
            return $value->format('m-d');
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('m-d');
        }
        if (is_string($value) && trim($value) !== '') {
            $ts = strtotime($value);
            return $ts !== false ? date('m-d', $ts) : '';
        }
        return '';
    }

    private static function extractBonusAmount($priv): int {
        if (is_string($priv) && $priv !== '') {
            $u = @unserialize($priv, ['allowed_classes' => false]);
            $priv = is_array($u) ? $u : [];
        }
        if (!is_array($priv)) {
            $priv = [];
        }
        return max(0, (int)($priv['bonus'] ?? 0));
    }

    private static function canAwardByFireMode(int $userId, array $rule, string $todayYmd): bool {
        $ruleId = (int)($rule['ID'] ?? 0);
        if ($ruleId <= 0) {
            return false;
        }

        $mode = (string)($rule['FIRE_MODE'] ?? 'Once');
        if ($mode !== 'Days' && $mode !== 'Every') {
            $mode = 'Once';
        }

        if ($mode === 'Every') {
            return true;
        }

        $last = EventHistoryTable::getList([
            'filter' => [
                '=USER_ID' => $userId,
                '=RULE_ID' => $ruleId,
            ],
            'select' => ['EVENT_DATE'],
            'order' => ['EVENT_DATE' => 'DESC', 'ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        if (empty($last['EVENT_DATE'])) {
            return true;
        }

        $lastDate = $last['EVENT_DATE'];
        if ($lastDate instanceof Date) {
            $lastYmd = $lastDate->format('Y-m-d');
        } elseif ($lastDate instanceof \DateTimeInterface) {
            $lastYmd = $lastDate->format('Y-m-d');
        } else {
            $lastYmd = (string)$lastDate;
        }

        if ($mode === 'Once') {
            return false;
        }

        $days = (int)($rule['FIRE_DAYS'] ?? 0);
        $days = max(1, $days);
        $today = new \DateTimeImmutable($todayYmd);
        $prev = new \DateTimeImmutable($lastYmd);
        $diffDays = (int)$prev->diff($today)->format('%a');
        return $diffDays >= $days;
    }

    private static function addHistory(int $userId, int $ruleId, string $todayYmd, int $amount): void {
        EventHistoryTable::add([
            'USER_ID' => $userId,
            'RULE_ID' => $ruleId,
            'EVENT_DATE' => new Date($todayYmd, 'Y-m-d'),
            'BONUS_AMOUNT' => $amount,
        ]);
    }
}

