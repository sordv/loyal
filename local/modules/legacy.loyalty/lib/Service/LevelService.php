<?php

namespace Legacy\Loyalty\Service;

use Bitrix\Main\Application;

class LevelService
{
    public static function setLevel($userId, $levelId, $source = 'system')
    {
        $connection = Application::getConnection();

        $current = $connection->query("
            SELECT LEVEL_ID
            FROM b_legacy_loyalty_level_user
            WHERE USER_ID = " . (int)$userId . "
            ORDER BY ID DESC
            LIMIT 1
        ")->fetch();

        $oldLevel = $current ? (int)$current['LEVEL_ID'] : null;

        $connection->queryExecute("
            INSERT INTO b_legacy_loyalty_level_history (USER_ID, OLD_LEVEL_ID, NEW_LEVEL_ID, SOURCE)
            VALUES (
                " . (int)$userId . ",
                " . ($oldLevel ? $oldLevel : "NULL") . ",
                " . (int)$levelId . ",
                '" . $connection->getSqlHelper()->forSql($source) . "'
            )
        ");

        $connection->queryExecute("
            INSERT INTO b_legacy_loyalty_level_user (USER_ID, LEVEL_ID)
            VALUES (" . (int)$userId . ", " . (int)$levelId . ")
        ");
    }

    public static function getLevel($userId)
    {
        $connection = Application::getConnection();
        $userId = (int)$userId;

        $userLevel = $connection->query("
            SELECT LEVEL_ID
            FROM b_legacy_loyalty_level_user
            WHERE USER_ID = {$userId}
            ORDER BY ID DESC
            LIMIT 1
        ")->fetch();

        if (!$userLevel || !$userLevel['LEVEL_ID'])
        {
            return null;
        }

        $levelId = (int)$userLevel['LEVEL_ID'];

        $levelRule = $connection->query("
            SELECT NAME
            FROM b_legacy_loyalty_level_rule
            WHERE ID = {$levelId}
        ")->fetch();

        return [
            'ID' => $levelId,
            'NAME' => $levelRule['NAME'] ?? ''
        ];
    }

    public static function getAllLevels()
    {
        $connection = Application::getConnection();
        $levels = [];

        $levels[] = [
            'ID' => 0,
            'NAME' => 'Без уровня'
        ];

        $res = $connection->query("
            SELECT ID, NAME
            FROM b_legacy_loyalty_level_rule
            WHERE ACTIVE = 'Y'
            ORDER BY SORT ASC, ID ASC
        ");

        while ($row = $res->fetch())
        {
            $levels[] = [
                'ID' => (int)$row['ID'],
                'NAME' => $row['NAME']
            ];
        }

        return $levels;
    }
}