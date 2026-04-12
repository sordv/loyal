<?php

namespace Legacy\Loyalty\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Application;

class BonusService {
    private static function getSettings() {
        return [
            'lifetime' => max(0, (int)Option::get("legacy.loyalty", "bonus_lifetime", 30)),
            'delay' => max(0, (int)Option::get("legacy.loyalty", "bonus_delay", 0)),
        ];
    }

    private static function getDate($offset = 0) {
        $date = new \DateTime();

        if ($offset !== 0) {
            $date->modify("+{$offset} days");
        }

        return $date->format('Y-m-d');
    }

    public static function cleanupExpiredBonuses() {
        $connection = Application::getConnection();
        $today = self::getDate(0);

        $connection->queryExecute("
            DELETE FROM b_legacy_loyalty_bonus_user
            WHERE EXPIRE_AT IS NOT NULL 
                AND EXPIRE_AT < '{$today}'
        ");
    }

    public static function addBonus($userId, $amount) {
        if ($amount < 0) return;

        $settings = self::getSettings();
        $connection = Application::getConnection();
        $userId = (int)$userId;
        $amount = (int)$amount;
        $activateDate = self::getDate($settings['delay']);
        $expireDate = $settings['lifetime'] > 0 ? self::getDate($settings['delay'] + $settings['lifetime']) : null;

        $activateSql = "'" . $activateDate . "'";
        $expireSql = $expireDate ? "'" . $expireDate . "'" : "NULL";

        $connection->queryExecute("
            INSERT INTO b_legacy_loyalty_bonus_user (USER_ID, AMOUNT, ACTIVATE_AT, EXPIRE_AT)
            VALUES ({$userId}, {$amount}, {$activateSql}, {$expireSql})
        ");

        $connection->queryExecute("
            INSERT INTO b_legacy_loyalty_bonus_history (USER_ID, TYPE, AMOUNT, SOURCE)
            VALUES ({$userId}, 'add', {$amount}, 'system');
        ");
    }

    public static function addBonusByAdmin($userId, $amount) {
        if ($amount < 0) return;

        $settings = self::getSettings();
        $connection = Application::getConnection();
        $userId = (int)$userId;
        $amount = (int)$amount;

        $activateDate = self::getDate(0);
        $expireDate = $settings['lifetime'] > 0 ? self::getDate($settings['lifetime']) : null;

        $activateSql = "'" . $activateDate . "'";
        $expireSql = $expireDate ? "'" . $expireDate . "'" : "NULL";

        $connection->queryExecute("
            INSERT INTO b_legacy_loyalty_bonus_user (USER_ID, AMOUNT, ACTIVATE_AT, EXPIRE_AT)
            VALUES ({$userId}, {$amount}, {$activateSql}, {$expireSql})
        ");

        $connection->queryExecute("
            INSERT INTO b_legacy_loyalty_bonus_history (USER_ID, TYPE, AMOUNT, SOURCE)
            VALUES ({$userId}, 'add', {$amount}, 'admin'); 
        ");
    }
    public static function spendBonus($userId, $amount) {
        if ($amount < 0) return;

        $connection = Application::getConnection();
        $userId = (int)$userId;
        $needToSpend = (int)$amount;
        $today = self::getDate(0);

        $records = $connection->query("
            SELECT ID, AMOUNT, EXPIRE_AT
            FROM b_legacy_loyalty_bonus_user
            WHERE USER_ID = {$userId} 
                AND ACTIVATE_AT <= '{$today}'
                AND (EXPIRE_AT IS NULL OR EXPIRE_AT >= '{$today}')
                AND AMOUNT > 0
            ORDER BY 
                CASE WHEN EXPIRE_AT IS NULL THEN 1 ELSE 0 END,
                EXPIRE_AT ASC,
                ID ASC
        ");

        while ($row = $records->fetch() && $needToSpend > 0) {
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
            INSERT INTO b_legacy_loyalty_bonus_history (USER_ID, TYPE, AMOUNT, SOURCE)
            VALUES ({$userId}, 'spend', {$amount}, 'system')
        ");
    }

    public static function spendBonusByAdmin($userId, $amount) {
        if ($amount < 0) return;

        $connection = Application::getConnection();
        $userId = (int)$userId;
        $needToSpend = (int)$amount;
        $today = self::getDate(0);

        $records = $connection->query("
            SELECT ID, AMOUNT, EXPIRE_AT
            FROM b_legacy_loyalty_bonus_user
            WHERE USER_ID = {$userId}
                AND (EXPIRE_AT IS NULL OR EXPIRE_AT >= '{$today}')
                AND AMOUNT > 0
            ORDER BY 
                CASE WHEN EXPIRE_AT IS NULL THEN 1 ELSE 0 END,
                EXPIRE_AT ASC,
                ID ASC
        ");

        while ($row = $records->fetch() && $needToSpend > 0) {
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
            INSERT INTO b_legacy_loyalty_bonus_history (USER_ID, TYPE, AMOUNT, SOURCE)
            VALUES ({$userId}, 'spend', {$amount}, 'admin')
        ");
    }
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
}