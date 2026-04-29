<?php

namespace Legacy\Loyalty\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Order;

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
}