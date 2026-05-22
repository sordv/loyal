<?php

namespace Legacy\Loyalty\EventHandler;

use Legacy\Loyalty\Service\LevelService;
use Legacy\Loyalty\Service\ProgramService;

class UserLevelHandler {
    public static function onAfterUserRegister(array &$arFields): void {
        self::syncLevelFromFields($arFields);
    }

    public static function onAfterUserAdd(array &$arFields): void {
        self::syncLevelFromFields($arFields);
    }

    private static function syncLevelFromFields(array $arFields): void {
        if (!ProgramService::isLevelEnabled()) {
            return;
        }

        $userId = 0;
        if (isset($arFields['USER_ID'])) {
            $userId = (int)$arFields['USER_ID'];
        } elseif (isset($arFields['ID'])) {
            $userId = (int)$arFields['ID'];
        }

        if ($userId <= 0) {
            return;
        }

        LevelService::syncUserLevel($userId);
    }
}

