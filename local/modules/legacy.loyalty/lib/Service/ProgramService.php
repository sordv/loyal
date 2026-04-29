<?php

namespace Legacy\Loyalty\Service;

use Bitrix\Main\Loader;
use Legacy\Loyalty\ProgramTable;

class ProgramService {
    public static function isBonusEnabled(): bool {
        return self::isEnabled('bonus');
    }

    public static function isLevelEnabled(): bool {
        return self::isEnabled('level');
    }

    public static function isEnabled(string $type): bool {
        if (!Loader::includeModule('legacy.loyalty')) {
            return false;
        }

        try {
            $program = ProgramTable::getList([
                'filter' => ['=TYPE' => $type],
                'select' => ['ACTIVE'],
                'limit' => 1,
            ])->fetch();
        } catch (\Throwable $exception) {
            return false;
        }

        return is_array($program) && ($program['ACTIVE'] ?? 'N') === 'Y';
    }
}