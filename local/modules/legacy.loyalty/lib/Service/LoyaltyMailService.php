<?php

namespace Legacy\Loyalty\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Legacy\Loyalty\Mail\MailEventsInstaller;

/**
 * Отправка почтовых уведомлений (типы и шаблоны — MailEventsInstaller при установке модуля).
 */
class LoyaltyMailService {
    public const EVENT_BONUS_ORDER = MailEventsInstaller::EVENT_BONUS_ORDER;
    public const EVENT_BONUS_ADMIN = MailEventsInstaller::EVENT_BONUS_ADMIN;
    public const EVENT_BONUS_EXPIRE = MailEventsInstaller::EVENT_BONUS_EXPIRE;
    public const EVENT_LEVEL_CHANGED = MailEventsInstaller::EVENT_LEVEL_CHANGED;

    private static function optionYes(string $name): bool {
        return Option::get('legacy.loyalty', $name, 'N') === 'Y';
    }

    private static function userMailContext(int $userId): ?array {
        if ($userId <= 0) {
            return null;
        }

        $rs = \CUser::GetByID($userId);
        $u = $rs->Fetch();
        if (!$u || (string)($u['EMAIL'] ?? '') === '') {
            return null;
        }

        $siteId = trim((string)($u['LID'] ?? ''));
        if ($siteId === '') {
            $siteId = (defined('SITE_ID') && SITE_ID) ? SITE_ID : 's1';
        }

        $siteName = '';
        $siteDomain = '';
        if ($siteId !== '') {
            $s = \CSite::GetByID($siteId)->Fetch();
            if (is_array($s)) {
                $siteName = (string)($s['SITE_NAME'] ?? '');
                $siteDomain = (string)($s['SERVER_NAME'] ?? '');
            }
        }
        if ($siteDomain === '' && !empty($_SERVER['SERVER_NAME'])) {
            $siteDomain = (string)$_SERVER['SERVER_NAME'];
        }

        return [
            'SITE_ID' => $siteId,
            'EMAIL' => (string)$u['EMAIL'],
            'NAME' => (string)($u['NAME'] ?? ''),
            'LAST_NAME' => (string)($u['LAST_NAME'] ?? ''),
            'SECOND_NAME' => (string)($u['SECOND_NAME'] ?? ''),
            'LOGIN' => (string)($u['LOGIN'] ?? ''),
            'USER_ID' => $userId,
            'SITE_NAME' => $siteName,
            'SITE' => $siteDomain,
            'SITE_URL' => $siteDomain,
        ];
    }

    private static function send(string $eventName, int $userId, array $fields): void {
        $ctx = self::userMailContext($userId);
        if ($ctx === null) {
            return;
        }

        $payload = array_merge($ctx, $fields);
        \CEvent::Send($eventName, $ctx['SITE_ID'], $payload, 'N', '', [], '');
    }

    public static function notifyBonusFromOrder(int $userId, int $amount, string $activateDateYmd, ?int $orderId): void {
        if (!ProgramService::isBonusEnabled()) {
            return;
        }
        if (!self::optionYes('mail_bonus_order')) {
            return;
        }

        $orderNum = '';
        if ($orderId !== null && $orderId > 0 && Loader::includeModule('sale')) {
            $order = Order::load($orderId);
            if ($order) {
                $orderNum = (string)$order->getField('ACCOUNT_NUMBER');
            }
        }

        self::send(self::EVENT_BONUS_ORDER, $userId, [
            'BONUS_NAME' => ProgramService::getBonusDisplayName(),
            'BONUS_AMOUNT' => (string)$amount,
            'ACTIVATE_DATE' => self::formatDateRu($activateDateYmd),
            'ORDER_ID' => $orderId !== null && $orderId > 0 ? (string)$orderId : '',
            'ORDER_ACCOUNT' => $orderNum,
        ]);
    }

    public static function notifyBonusFromAdmin(int $userId, int $amount, string $activateDateYmd, ?string $expireDateYmd): void {
        if (!ProgramService::isBonusEnabled()) {
            return;
        }
        if (!self::optionYes('mail_bonus_admin')) {
            return;
        }

        $expireFormatted = ($expireDateYmd !== null && $expireDateYmd !== '') ? self::formatDateRu($expireDateYmd) : '';

        self::send(self::EVENT_BONUS_ADMIN, $userId, [
            'BONUS_NAME' => ProgramService::getBonusDisplayName(),
            'BONUS_AMOUNT' => (string)$amount,
            'ACTIVATE_DATE' => self::formatDateRu($activateDateYmd),
            'EXPIRE_DATE' => $expireFormatted,
            'EXPIRE_DATE_TEXT' => $expireFormatted !== ''
                ? ('Срок действия бонусов: <b>' . \htmlspecialcharsbx($expireFormatted) . '</b>')
                : '',
        ]);
    }

    public static function notifyBonusExpireWarning(
        int $userId,
        int $amount,
        string $expireDateYmd,
        int $daysBefore
    ): void {
        if (!ProgramService::isBonusEnabled()) {
            return;
        }
        if (!self::optionYes('mail_bonus_expire')) {
            return;
        }

        self::send(self::EVENT_BONUS_EXPIRE, $userId, [
            'BONUS_NAME' => ProgramService::getBonusDisplayName(),
            'BONUS_AMOUNT' => (string)$amount,
            'EXPIRE_DATE' => self::formatDateRu($expireDateYmd),
            'DAYS_BEFORE' => (string)$daysBefore,
        ]);
    }

    public static function notifyLevelChanged(
        int $userId,
        ?int $oldLevelId,
        int $newLevelId,
        string $oldLevelName,
        string $newLevelName
    ): void {
        if (!ProgramService::isLevelEnabled()) {
            return;
        }
        if (!self::optionYes('mail_level_change')) {
            return;
        }

        self::send(self::EVENT_LEVEL_CHANGED, $userId, [
            'OLD_LEVEL_ID' => $oldLevelId !== null && $oldLevelId > 0 ? (string)$oldLevelId : '',
            'NEW_LEVEL_ID' => (string)$newLevelId,
            'OLD_LEVEL_NAME' => $oldLevelName,
            'NEW_LEVEL_NAME' => $newLevelName,
        ]);
    }

    private static function formatDateRu(string $ymd): string {
        $ts = strtotime($ymd . ' 12:00:00');
        if ($ts === false) {
            return $ymd;
        }

        return \FormatDate('SHORT', $ts);
    }
}
