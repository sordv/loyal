<?php

namespace Legacy\Loyalty\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Legacy\Loyalty\Tables\EventRuleTable;

class BonusService {
    public const AGENT_CLEANUP_EXPIRED = '\Legacy\Loyalty\Service\BonusService::cleanupExpiredBonuses();';

    // получить сроки жизни и задержки из настроек модуля
    private static function getSettings() {
        return [
            'lifetime' => max(0, (int)Option::get("legacy.loyalty", "bonus_lifetime", 365)),
            'delay' => max(0, (int)Option::get("legacy.loyalty", "bonus_delay", 1)),
        ];
    }

    // получить правильную дату для правила (с учетом заддержки активации)
    private static function getDate($offset = 0) {
        $date = new \DateTime();
        if ($offset !== 0) {
            $date->modify("+{$offset} days");
        }
        return $date->format('Y-m-d');
    }

    // удаление бонусов с истекшим сроком действия (поле EXPIRE_AT)
    public static function cleanupExpiredBonuses(): string {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $today = $sqlHelper->forSql(self::getDate(0));
        $connection->queryExecute("
            DELETE FROM b_legacy_loyalty_bonus_user
            WHERE EXPIRE_AT IS NOT NULL 
                AND EXPIRE_AT < '{$today}'
        ");

        return self::AGENT_CLEANUP_EXPIRED;
    }

    // начисление бонусов
    // используется системой (программой начисления, списания бонусов и программой вознаграждения)
    // начисляет бонусы с задержкой (если есть)
    public static function addBonus($userId, $amount, int $sourceId, string $sourceType) {
        $userId = (int)$userId;
        $amount = (int)$amount;
        $sourceId = (int)$sourceId;
        if ($userId <= 0 || $amount <= 0 || $sourceId <= 0) {
            return;
        }
        if ($sourceType !== 'bonussystem' && $sourceType !== 'eventsystem') {
            return;
        }

        $settings = self::getSettings();
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $activateDate = self::getDate($settings['delay']);
        $expireDate = $settings['lifetime'] > 0 ? self::getDate($settings['delay'] + $settings['lifetime']) : null;

        $whenExpire = $expireDate
            ? "EXPIRE_AT = '" . $sqlHelper->forSql($expireDate) . "'"
            : "EXPIRE_AT IS NULL";

        try{
            $connection->startTransaction();

            $existingDates = $connection->query("
                SELECT ID, AMOUNT
                FROM b_legacy_loyalty_bonus_user
                WHERE USER_ID = {$userId}
                    AND ACTIVATE_AT = '" . $sqlHelper->forSql($activateDate) . "'
                    AND {$whenExpire}
                LIMIT 1
            ")->fetch();

            if($existingDates) {
                $newAmount = (int)$existingDates['AMOUNT'] + $amount;
                $connection->queryExecute("
                    UPDATE b_legacy_loyalty_bonus_user
                    SET AMOUNT = {$newAmount}
                    WHERE ID = " . (int)$existingDates['ID'] . "
                ");
            } else {
                $activateSql = "'" . $sqlHelper->forSql($activateDate) . "'";
                $expireSql = $expireDate ? "'" . $sqlHelper->forSql($expireDate) . "'" : "NULL";

                $connection->queryExecute("
                    INSERT INTO b_legacy_loyalty_bonus_user (USER_ID, AMOUNT, ACTIVATE_AT, EXPIRE_AT)
                    VALUES ({$userId}, {$amount}, {$activateSql}, {$expireSql})
                ");
            }

            $sourceTypeSql = $sqlHelper->forSql($sourceType);
            $connection->queryExecute("
                INSERT INTO b_legacy_loyalty_bonus_history (USER_ID, TYPE, AMOUNT, SOURCE_TYPE, SOURCE_ID, SOURCE)
                VALUES ({$userId}, 'add', {$amount}, '{$sourceTypeSql}', {$sourceId}, 'system');
            ");

            $connection->commitTransaction();

            try {
                if ($sourceType === 'eventsystem') {
                    $ruleName = '';
                    try {
                        $rule = EventRuleTable::getById($sourceId)->fetch();
                        if (is_array($rule) && !empty($rule['NAME'])) {
                            $ruleName = (string)$rule['NAME'];
                        }
                    } catch (\Throwable $e) {
                    }
                    LoyaltyMailService::notifyBonusFromEvent($userId, $amount, $activateDate, $sourceId, $ruleName);
                } elseif ($sourceType === 'bonussystem') {
                    LoyaltyMailService::notifyBonusFromOrder($userId, $amount, $activateDate, $sourceId);
                }
            } catch (\Throwable $e) {
            }
        } catch (\Throwable $ex) {
            $connection->rollbackTransaction();
            throw $ex;
        }
    }

    // начисление бонусов администратором
    // используется при ручном управлении пользователями
    // начисляет бонусы без задержки (даже если по обычным правилам она есть)
    public static function addBonusByAdmin($userId, $amount, $adminId) {
        $userId = (int)$userId;
        $amount = (int)$amount;
        $adminId = (int)$adminId;
        if ($userId <= 0 || $amount <= 0 || $adminId <= 0) return;

        $settings = self::getSettings();
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $activateDate = self::getDate(0);
        $expireDate = $settings['lifetime'] > 0 ? self::getDate($settings['lifetime']) : null;

        $whenExpire = $expireDate
            ? "EXPIRE_AT = '" . $sqlHelper->forSql($expireDate) . "'"
            : "EXPIRE_AT IS NULL";

        try {
            $connection->startTransaction();

            $existingDates = $connection->query("
                SELECT ID, AMOUNT
                FROM b_legacy_loyalty_bonus_user
                WHERE USER_ID = {$userId}
                    AND ACTIVATE_AT = '" . $sqlHelper->forSql($activateDate) . "'
                    AND {$whenExpire}
                LIMIT 1
            ")->fetch();

            if($existingDates) {
                $newAmount = (int)$existingDates['AMOUNT'] + $amount;
                $connection->queryExecute("
                    UPDATE b_legacy_loyalty_bonus_user
                    SET AMOUNT = {$newAmount}
                    WHERE ID = " . (int)$existingDates['ID'] . "
                ");
            } else {
                $activateSql = "'" . $sqlHelper->forSql($activateDate) . "'";
                $expireSql = $expireDate ? "'" . $sqlHelper->forSql($expireDate) . "'" : "NULL";

                $connection->queryExecute("
                    INSERT INTO b_legacy_loyalty_bonus_user (USER_ID, AMOUNT, ACTIVATE_AT, EXPIRE_AT)
                    VALUES ({$userId}, {$amount}, {$activateSql}, {$expireSql})
                ");
            }

            $connection->queryExecute("
                INSERT INTO b_legacy_loyalty_bonus_history (USER_ID, TYPE, AMOUNT, SOURCE_TYPE, SOURCE_ID, SOURCE)
                VALUES ({$userId}, 'add', {$amount}, 'manual', {$adminId}, 'admin');
            ");

            $connection->commitTransaction();

            try {
                LoyaltyMailService::notifyBonusFromAdmin($userId, $amount, $activateDate, $expireDate);
            } catch (\Throwable $e) {
            }
        } catch (\Throwable $ex) {
            $connection->rollbackTransaction();
            throw $ex;
        }
    }

    // списание бонусов
    // используется системой (программой начисления, списания бонусов)
    // списывает бонусы у которых раньше кончается срок действия
    // не списывает еще не активные бонусы
    public static function spendBonus($userId, $amount, int $sourceId) {
        $sourceId = (int)$sourceId;
        if ($amount <= 0 || $sourceId <= 0) {
            return;
        }

        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        $userId = (int)$userId;
        $needToSpend = (int)$amount;
        $today = self::getDate(0);
        $todaySql = $sqlHelper->forSql($today);

        try {
            $connection->startTransaction();

            $records = $connection->query("
                SELECT ID, AMOUNT, EXPIRE_AT
                FROM b_legacy_loyalty_bonus_user
                WHERE USER_ID = {$userId} 
                    AND ACTIVATE_AT <= '{$todaySql}'
                    AND (EXPIRE_AT IS NULL OR EXPIRE_AT >= '{$todaySql}')
                    AND AMOUNT > 0
                ORDER BY 
                    CASE WHEN EXPIRE_AT IS NULL THEN 1 ELSE 0 END,
                    EXPIRE_AT ASC,
                    ID ASC
            ");

            while ($row = $records->fetch()) {
                if ($needToSpend <= 0) break;

                $rowId = (int)$row['ID'];
                $rowAmount = (int)$row['AMOUNT'];

                if ($rowAmount <= $needToSpend) {
                    $connection->queryExecute("
                        DELETE FROM b_legacy_loyalty_bonus_user
                        WHERE ID = {$rowId}
                    ");
                    $needToSpend -= $rowAmount;
                } else {
                    $newAmount = $rowAmount - $needToSpend;
                    $connection->queryExecute("
                        UPDATE b_legacy_loyalty_bonus_user
                        SET AMOUNT = {$newAmount}
                        WHERE ID = {$rowId}
                    ");
                    $needToSpend = 0;
                }
            }

            $connection->queryExecute("
                INSERT INTO b_legacy_loyalty_bonus_history (USER_ID, TYPE, AMOUNT, SOURCE_TYPE, SOURCE_ID, SOURCE)
                VALUES ({$userId}, 'spend', {$amount}, 'bonussystem', {$sourceId}, 'system')
            ");

            $connection->commitTransaction();
        } catch (\Exception $ex) {
            $connection->rollbackTransaction();
            throw $ex;
        }
    }

    // списание бонусов администратором
    // используется при ручном управлении пользователями
    // списывает бонусы у которых раньше кончается срок действия
    // списывает еще не активные бонусы
    public static function spendBonusByAdmin($userId, $amount, $adminId) {
        if ($amount <= 0) return;

        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        $userId = (int)$userId;
        $adminId = (int)$adminId;
        if ($adminId <= 0) return;
        $needToSpend = (int)$amount;
        $today = self::getDate(0);
        $todaySql = $sqlHelper->forSql($today);

        try {
            $connection->startTransaction();

            $records = $connection->query("
                SELECT ID, AMOUNT, EXPIRE_AT
                FROM b_legacy_loyalty_bonus_user
                WHERE USER_ID = {$userId}
                    AND (EXPIRE_AT IS NULL OR EXPIRE_AT >= '{$todaySql}')
                    AND AMOUNT > 0
                ORDER BY 
                    CASE WHEN EXPIRE_AT IS NULL THEN 1 ELSE 0 END,
                    EXPIRE_AT ASC,
                    ID ASC
            ");

            while ($row = $records->fetch()) {
                if ($needToSpend <= 0) break;

                $rowId = (int)$row['ID'];
                $rowAmount = (int)$row['AMOUNT'];

                if ($rowAmount <= $needToSpend) {
                    $connection->queryExecute("
                        DELETE FROM b_legacy_loyalty_bonus_user
                        WHERE ID = {$rowId}
                    ");
                    $needToSpend -= $rowAmount;
                } else {
                    $newAmount = $rowAmount - $needToSpend;
                    $connection->queryExecute("
                        UPDATE b_legacy_loyalty_bonus_user
                        SET AMOUNT = {$newAmount}
                        WHERE ID = {$rowId}
                    ");
                    $needToSpend = 0;
                }
            }

            $connection->queryExecute("
                INSERT INTO b_legacy_loyalty_bonus_history (USER_ID, TYPE, AMOUNT, SOURCE_TYPE, SOURCE_ID, SOURCE)
                VALUES ({$userId}, 'spend', {$amount}, 'manual', {$adminId}, 'admin')
            ");

            $connection->commitTransaction();
        } catch (\Exception $ex) {
            $connection->rollbackTransaction();
            throw $ex;
        }
    }

    // получить баланс пользователя: активные + в задержке
    public static function getBalance($userId) {
        $connection = Application::getConnection();
        $userId = (int)$userId;
        $today = self::getDate(0);

        $available = $connection->query("
            SELECT SUM(AMOUNT) AS TOTAL
            FROM b_legacy_loyalty_bonus_user
            WHERE USER_ID = {$userId}
                AND ACTIVATE_AT <= '{$today}'
                AND (EXPIRE_AT IS NULL OR EXPIRE_AT >= '{$today}')
            ")->fetch();

        $pending = $connection->query("
            SELECT SUM(AMOUNT) AS TOTAL
            FROM b_legacy_loyalty_bonus_user
            WHERE USER_ID = {$userId}
                AND ACTIVATE_AT > '{$today}'
                AND (EXPIRE_AT IS NULL OR EXPIRE_AT >= '{$today}')
            ")->fetch();

        return [
            'available' => (int)($available['TOTAL'] ?? 0),
            'pending' => (int)($pending['TOTAL'] ?? 0),
        ];
    }

    public static function hasOrderOperation(int $sourceId, string $type): bool {
        if ($sourceId <= 0 || ($type !== 'add' && $type !== 'spend')) {
            return false;
        }

        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $typeSql = $sqlHelper->forSql($type);

        $row = $connection->query("
            SELECT ID
            FROM b_legacy_loyalty_bonus_history
            WHERE SOURCE_ID = {$sourceId}
              AND TYPE = '{$typeSql}'
            ORDER BY ID DESC
            LIMIT 1
        ")->fetch();

        return !empty($row['ID']);
    }
}
