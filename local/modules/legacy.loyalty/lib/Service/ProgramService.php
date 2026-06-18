<?php

namespace Legacy\Loyalty\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Legacy\Loyalty\Tables\ProgramTable;

class ProgramService {
    // получить название бонусов (программа начисления и списания бонусов)
    //todo сделать склонения
    public static function getBonusDisplayName(): string {
        $name = trim((string)Option::get('legacy.loyalty', 'bonus_name', 'Бонусы'));

        return $name !== '' ? $name : 'Бонусы';
    }

    // проверить включена ли программа начисления и списания бонусов
    public static function isBonusEnabled(): bool {
        return self::isEnabled('bonus');
    }

    // проверить включена ли программа разбиения пользователей на уровни
    public static function isLevelEnabled(): bool {
        return self::isEnabled('level');
    }

    // проверить включена ли программа вознаграждения пользователей за события
    public static function isEventEnabled(): bool {
        return self::isEnabled('event');
    }

    // проверить включена ли программа
    private static function isEnabled(string $type): bool {
        try {
            $program = ProgramTable::getList([
                'filter' => ['=TYPE' => $type],
                'select' => ['ACTIVE'],
            ])->fetch();

            return is_array($program) && $program['ACTIVE'] === 'Y';
        } catch (\Throwable $exception) {
            return false;
        }
    }

    // получить статус заказа при переходе в который начисляются бонусы за заказ
    public static function getBonusAccrualOrderStatus(): string {
        return trim((string)Option::get('legacy.loyalty', 'bonus_accrual_order_status', 'F'));
    }

    // получить статус заказа при переходе в который пересчитывается уровень пользователя
    public static function getLevelCompleteOrderStatus(): string {
        return trim((string)Option::get('legacy.loyalty', 'level_complete_order_status', 'F'));
    }

    // проверить начислять ли бонусы сразу (не дожидаясь нужного статуса) для начислени бонуссов
    public static function isBonusAccrualOnPaidEnabled(): bool {
        return Option::get('legacy.loyalty', 'bonus_accrual_on_paid', 'Y') === 'Y';
    }
}
