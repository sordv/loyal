<?php

namespace Legacy\Loyalty\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Bitrix\Sale\Order;
use Legacy\Loyalty\RuleBuilder\LevelRuleTable;

class LevelService {
    public static function setLevel($userId, $levelId)
    {
        $connection = Application::getConnection();
        $userId = (int)$userId;
        $levelId = (int)$levelId;

        try {
            $connection->startTransaction();

            $current = $connection->query("
                SELECT ID, LEVEL_ID
                FROM b_legacy_loyalty_level_user
                WHERE USER_ID = {$userId}
                ORDER BY ID DESC
                LIMIT 1
            ")->fetch();

            $oldLevel = $current ? (int)$current['LEVEL_ID'] : null;

            $connection->queryExecute("
                INSERT INTO b_legacy_loyalty_level_history (USER_ID, OLD_LEVEL_ID, NEW_LEVEL_ID, SOURCE)
                VALUES (
                    {$userId},
                    " . ($oldLevel !== null ? $oldLevel : "NULL") . ",
                    {$levelId},
                    'system'
                )
            ");

            if ($current) {
                $connection->queryExecute("
                    UPDATE b_legacy_loyalty_level_user
                    SET LEVEL_ID = {$levelId}, UPDATED_AT = NOW()
                    WHERE ID = " . (int)$current['ID'] . "
                ");
            } else {
                $connection->queryExecute("
                    INSERT INTO b_legacy_loyalty_level_user (USER_ID, LEVEL_ID, UPDATED_AT)
                    VALUES ({$userId}, {$levelId}, NOW())
                ");
            }

            $connection->commitTransaction();

            if ((int)($oldLevel ?? 0) !== (int)$levelId) {
                try {
                    LoyaltyMailService::notifyLevelChanged(
                        $userId,
                        $oldLevel,
                        $levelId,
                        self::fetchLevelRuleName($oldLevel),
                        self::fetchLevelRuleName($levelId)
                    );
                } catch (\Throwable $e) {
                }
            }
        } catch (\Exception $ex) {
            $connection->rollbackTransaction();
            throw $ex;
        }
    }

    public static function setLevelByAdmin($userId, $levelId)
    {
        $connection = Application::getConnection();
        $userId = (int)$userId;
        $levelId = (int)$levelId;

        try {
            $connection->startTransaction();

            $current = $connection->query("
                SELECT ID, LEVEL_ID
                FROM b_legacy_loyalty_level_user
                WHERE USER_ID = {$userId}
                ORDER BY ID DESC
                LIMIT 1
            ")->fetch();

            $oldLevel = $current ? (int)$current['LEVEL_ID'] : null;

            $connection->queryExecute("
                INSERT INTO b_legacy_loyalty_level_history (USER_ID, OLD_LEVEL_ID, NEW_LEVEL_ID, SOURCE)
                VALUES (
                    {$userId},
                    " . ($oldLevel !== null ? $oldLevel : "NULL") . ",
                    {$levelId},
                    'admin'
                )
            ");

            if ($current) {
                $connection->queryExecute("
                    UPDATE b_legacy_loyalty_level_user
                    SET LEVEL_ID = {$levelId}, UPDATED_AT = NOW()
                    WHERE ID = " . (int)$current['ID'] . "
                ");
            } else {
                $connection->queryExecute("
                    INSERT INTO b_legacy_loyalty_level_user (USER_ID, LEVEL_ID, UPDATED_AT)
                    VALUES ({$userId}, {$levelId}, NOW())
                ");
            }

            $connection->commitTransaction();

            if ((int)($oldLevel ?? 0) !== (int)$levelId) {
                try {
                    LoyaltyMailService::notifyLevelChanged(
                        $userId,
                        $oldLevel,
                        $levelId,
                        self::fetchLevelRuleName($oldLevel),
                        self::fetchLevelRuleName($levelId)
                    );
                } catch (\Throwable $e) {
                }
            }
        } catch (\Exception $ex) {
            $connection->rollbackTransaction();
            throw $ex;
        }
    }

    public static function getLevel($userId) {
        $connection = Application::getConnection();
        $userId = (int)$userId;
        $userLevel = $connection->query("
            SELECT LEVEL_ID
            FROM b_legacy_loyalty_level_user
            WHERE USER_ID = {$userId}
            ORDER BY ID DESC
            LIMIT 1
        ")->fetch();

        if (!$userLevel || !$userLevel['LEVEL_ID']) {
            return null;
        }

        $levelId = (int)$userLevel['LEVEL_ID'];
        $levelRule = $connection->query("
            SELECT NAME, PRIVILEGES
            FROM b_legacy_loyalty_level_rule
            WHERE ID = {$levelId}
        ")->fetch();

        return [
            'ID' => $levelId,
            'NAME' => $levelRule['NAME'] ?? '',
            'PRIVILEGES' => self::normalizePrivileges($levelRule['PRIVILEGES'] ?? [])
        ];
    }

    public static function getAllLevels() {
        $connection = Application::getConnection();
        $levels = [['ID' => 0, 'NAME' => 'Без уровня']];

        $res = $connection->query("
            SELECT ID, NAME
            FROM b_legacy_loyalty_level_rule
            WHERE ACTIVE = 'Y'
            ORDER BY SORT ASC, ID ASC
        ");

        while ($row = $res->fetch()) {
            $levels[] = [
                'ID' => (int)$row['ID'],
                'NAME' => $row['NAME']
            ];
        }

        return $levels;
    }

    public static function normalizePrivileges($privileges): array {
        if (is_string($privileges) && $privileges !== '') {
            $unserialized = @unserialize($privileges, ['allowed_classes' => false]);
            $privileges = is_array($unserialized) ? $unserialized : [];
        }

        if (!is_array($privileges)) {
            $privileges = [];
        }

        return [
            'cartDiscountPercent' => max(0, min(100, (float)($privileges['cartDiscountPercent'] ?? 0))),
            'deliveryDiscountPercent' => max(0, min(100, (float)($privileges['deliveryDiscountPercent'] ?? 0))),
            'addBonusMultiplier' => max(0, (float)($privileges['addBonusMultiplier'] ?? 1)),
            'spendBonusMultiplier' => max(0, (float)($privileges['spendBonusMultiplier'] ?? 1)),
        ];
    }

    public static function getCompletedOrdersStats(int $userId, ?int $periodDays = null): array {
        if ($userId <= 0 || !Loader::includeModule('sale')) {
            return ['count' => 0, 'sum' => 0.0];
        }

        $filter = [
            '=USER_ID' => $userId,
            '=STATUS_ID' => 'F',
            '=CANCELED' => 'N',
        ];

        if ($periodDays !== null && $periodDays > 0) {
            $filter['>=DATE_INSERT'] = (new DateTime())->add('-' . $periodDays . ' days');
        }

        $row = Order::getList([
            'filter' => $filter,
            'select' => ['CNT', 'SUM_PRICE'],
            'runtime' => [
                new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)'),
                new \Bitrix\Main\Entity\ExpressionField('SUM_PRICE', 'SUM(%s)', ['PRICE']),
            ],
        ])->fetch();

        return [
            'count' => (int)($row['CNT'] ?? 0),
            'sum' => (float)($row['SUM_PRICE'] ?? 0),
        ];
    }

    public static function findBestMatchingLevelRuleId(int $userId): ?int {
        if ($userId <= 0 || !Loader::includeModule('sale')) {
            return null;
        }

        $rules = LevelRuleTable::getList([
            'filter' => ['=ACTIVE' => 'Y'],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);

        while ($rule = $rules->fetch()) {
            $tree = $rule['CONDITIONS'];
            if (!is_array($tree)) {
                $tree = [];
            }

            if (self::matchLevelConditionsTree($tree, $userId)) {
                return (int)$rule['ID'];
            }
        }

        return null;
    }

    public static function syncUserLevelFromRules(int $userId): void {
        if ($userId <= 0 || !ProgramService::isLevelEnabled()) {
            return;
        }

        if (!Loader::includeModule('sale')) {
            return;
        }

        $bestRuleId = self::findBestMatchingLevelRuleId($userId);

        $connection = Application::getConnection();
        $row = $connection->query(
            'SELECT LEVEL_ID FROM b_legacy_loyalty_level_user WHERE USER_ID = ' . (int)$userId . ' ORDER BY ID DESC LIMIT 1'
        )->fetch();

        $currentLevelId = ($row && (int)$row['LEVEL_ID'] > 0) ? (int)$row['LEVEL_ID'] : null;

        if ($bestRuleId === null) {
            if ($currentLevelId !== null) {
                self::clearUserLevel($userId);
            }
            return;
        }

        if ($currentLevelId === $bestRuleId) {
            return;
        }

        self::setLevel($userId, $bestRuleId);
    }

    private static function fetchLevelRuleName(?int $levelId): string {
        if ($levelId === null || $levelId <= 0) {
            return '';
        }

        $connection = Application::getConnection();
        $row = $connection->query(
            'SELECT NAME FROM b_legacy_loyalty_level_rule WHERE ID = ' . (int)$levelId . ' LIMIT 1'
        )->fetch();

        return is_array($row) ? trim((string)($row['NAME'] ?? '')) : '';
    }

    private static function clearUserLevel(int $userId): void {
        $connection = Application::getConnection();
        $userId = (int)$userId;

        $current = $connection->query(
            'SELECT ID, LEVEL_ID FROM b_legacy_loyalty_level_user WHERE USER_ID = ' . $userId . ' ORDER BY ID DESC LIMIT 1'
        )->fetch();

        if (!$current || (int)$current['LEVEL_ID'] <= 0) {
            return;
        }

        $oldLevel = (int)$current['LEVEL_ID'];
        $rowId = (int)$current['ID'];

        try {
            $connection->startTransaction();

            $connection->queryExecute(
                'INSERT INTO b_legacy_loyalty_level_history (USER_ID, OLD_LEVEL_ID, NEW_LEVEL_ID, SOURCE) VALUES ('
                . $userId . ', ' . $oldLevel . ', NULL, \'system\')'
            );

            $connection->queryExecute(
                'DELETE FROM b_legacy_loyalty_level_user WHERE ID = ' . $rowId
            );

            $connection->commitTransaction();
        } catch (\Exception $ex) {
            $connection->rollbackTransaction();
            throw $ex;
        }
    }

    private static function matchLevelConditionsTree(array $tree, int $userId): bool {
        if (!isset($tree['children']) || !is_array($tree['children']) || count($tree['children']) === 0) {
            return true;
        }

        $children = array_values($tree['children']);
        $all = (($tree['values']['All'] ?? 'AND') === 'OR') ? 'OR' : 'AND';
        $true = (($tree['values']['True'] ?? 'True') !== 'False');
        $matched = self::matchLevelConditionChildren($children, $userId, $all);

        return $true ? $matched : !$matched;
    }

    private static function matchLevelConditionChildren(array $children, int $userId, string $all): bool {
        if ($all === 'OR') {
            foreach ($children as $child) {
                if (self::matchLevelConditionNode(is_array($child) ? $child : [], $userId)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($children as $child) {
            if (!self::matchLevelConditionNode(is_array($child) ? $child : [], $userId)) {
                return false;
            }
        }

        return true;
    }

    private static function matchLevelConditionNode(array $node, int $userId): bool {
        if (($node['controlId'] ?? '') === 'CondGroup') {
            return self::matchLevelConditionsTree($node, $userId);
        }

        if (!empty($node['children'])) {
            return self::matchLevelConditionsTree($node, $userId);
        }

        return self::matchUserProfileCondition($node, $userId);
    }

    private static function matchUserProfileCondition(array $node, int $userId): bool {
        $id = (string)($node['controlId'] ?? '');
        $values = $node['values'] ?? [];
        $logic = (string)($values['logic'] ?? 'Equal');

        switch ($id) {
            case 'ordersSum':
                $stats = self::getCompletedOrdersStats($userId, null);

                return self::compareNumber($stats['sum'], (float)($values['value'] ?? 0), $logic);

            case 'ordersCount':
                $stats = self::getCompletedOrdersStats($userId, null);

                return self::compareNumber($stats['count'], (float)($values['value'] ?? 0), $logic);

            case 'ordersSumPeriod':
                $period = max(1, (int)($values['period'] ?? 30));
                $stats = self::getCompletedOrdersStats($userId, $period);

                return self::compareNumber($stats['sum'], (float)($values['value'] ?? 0), $logic);

            case 'ordersCountPeriod':
                $period = max(1, (int)($values['period'] ?? 30));
                $stats = self::getCompletedOrdersStats($userId, $period);

                return self::compareNumber($stats['count'], (float)($values['value'] ?? 0), $logic);

            case 'registrationAge':
                $ageDays = self::getUserRegistrationAgeDays($userId);
                if ($ageDays === null) {
                    return false;
                }

                return self::compareNumber($ageDays, (float)($values['value'] ?? 0), $logic);

            case 'registrationDate':
                $userTs = self::getUserRegistrationTimestamp($userId);
                if ($userTs === null) {
                    return false;
                }
                $expected = trim((string)($values['value'] ?? ''));
                if ($expected === '') {
                    return false;
                }
                $condTs = self::parseDateTimeToTs($expected);
                if ($condTs === null) {
                    return false;
                }

                return self::compareNumber($userTs, (float)$condTs, $logic);
        }

        return false;
    }

    private static function getUserRegistrationAgeDays(int $userId): ?int {
        $ts = self::getUserRegistrationTimestamp($userId);
        if ($ts === null) {
            return null;
        }

        return (int)floor((time() - $ts) / 86400);
    }

    private static function getUserRegistrationTimestamp(int $userId): ?int {
        $row = UserTable::getList([
            'filter' => ['=ID' => $userId],
            'select' => ['DATE_REGISTER'],
            'limit' => 1,
        ])->fetch();

        if (!$row || empty($row['DATE_REGISTER'])) {
            return null;
        }

        $dr = $row['DATE_REGISTER'];
        if ($dr instanceof \Bitrix\Main\Type\DateTime) {
            return $dr->getTimestamp();
        }

        if ($dr instanceof \DateTimeInterface) {
            return $dr->getTimestamp();
        }

        $parsed = MakeTimeStamp((string)$dr);
        if ($parsed === false) {
            return null;
        }

        return (int)$parsed;
    }

    private static function parseDateTimeToTs(string $value): ?int {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $ts = MakeTimeStamp($value);
        if ($ts !== false) {
            return (int)$ts;
        }

        $ts = strtotime($value);

        return $ts !== false ? (int)$ts : null;
    }

    private static function compareNumber($actual, $expected, string $logic): bool {
        $actual = (float)str_replace(',', '.', (string)$actual);
        $expected = (float)str_replace(',', '.', (string)$expected);

        switch ($logic) {
            case 'Not':
                return $actual != $expected;
            case 'Greater':
            case 'Great':
            case 'EqGr':
                return $actual > $expected;
            case 'Less':
                return $actual < $expected;
            case 'GreaterEqual':
                return $actual >= $expected;
            case 'LessEqual':
            case 'EqLs':
                return $actual <= $expected;
            case 'Equal':
            default:
                return $actual == $expected;
        }
    }
}