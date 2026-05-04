<?php

namespace Legacy\Loyalty\Mail;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;

/**
 * Регистрация типов почтовых событий и шаблонов при установке / снятие при удалении модуля.
 */
final class MailEventsInstaller {
    public const EVENT_BONUS_ORDER = 'LEGACY_LOYALTY_BONUS_ORDER_ADD';
    public const EVENT_BONUS_ADMIN = 'LEGACY_LOYALTY_BONUS_ADMIN_ADD';
    public const EVENT_BONUS_EXPIRE = 'LEGACY_LOYALTY_BONUS_EXPIRE_WARNING';
    public const EVENT_LEVEL_CHANGED = 'LEGACY_LOYALTY_LEVEL_CHANGED';

    public static function install(): void {
        if (!Loader::includeModule('main')) {
            return;
        }

        $siteIds = self::collectSiteIds() ?: ['s1'];
        $emailFrom = \COption::GetOptionString('main', 'email_from', '');
        $bcc = \COption::GetOptionString('main', 'all_bcc', '');

        foreach (self::collectLanguageIds() as $langLid) {
            foreach (self::eventTypes() as $eventName => $row) {
                self::ensureEventType($eventName, $langLid, $row['name'], $row['desc']);
            }
        }

        foreach (self::templates() as $eventName => $row) {
            self::ensureMessageTemplate($eventName, $row['subject'], $row['body'], $siteIds, $emailFrom, $bcc);
        }
    }

    public static function uninstall(): void {
        if (!Loader::includeModule('main')) {
            return;
        }

        $codes = [
            self::EVENT_BONUS_ORDER,
            self::EVENT_BONUS_ADMIN,
            self::EVENT_BONUS_EXPIRE,
            self::EVENT_LEVEL_CHANGED,
        ];

        $rsMess = \CEventMessage::GetList('site_id', 'desc', ['TYPE_ID' => $codes]);
        if ($rsMess) {
            while ($mess = $rsMess->Fetch()) {
                \CEventMessage::Delete($mess['ID']);
            }
        }

        $et = new \CEventType();
        foreach ($codes as $typeId) {
            $et->Delete($typeId);
        }
    }

    /** @return array<string, array{name: string, desc: string}> */
    private static function eventTypes(): array {
        return [
            self::EVENT_BONUS_ORDER => [
                'name' => 'Legacy Loyalty: бонусы за заказ',
                'desc' => 'legacy.loyalty',
            ],
            self::EVENT_BONUS_ADMIN => [
                'name' => 'Legacy Loyalty: бонусы от администратора',
                'desc' => 'legacy.loyalty',
            ],
            self::EVENT_BONUS_EXPIRE => [
                'name' => 'Legacy Loyalty: истечение бонусов',
                'desc' => 'legacy.loyalty',
            ],
            self::EVENT_LEVEL_CHANGED => [
                'name' => 'Legacy Loyalty: смена уровня',
                'desc' => 'legacy.loyalty',
            ],
        ];
    }

    /** @return array<string, array{subject: string, body: string}> */
    private static function templates(): array {
        return [
            self::EVENT_BONUS_ORDER => [
                'subject' => 'Бонусы за заказ',
                'body' => "Здравствуйте, #NAME#!\n"
                    . "Начислено #BONUS_AMOUNT# (#BONUS_NAME#), активация #ACTIVATE_DATE#, заказ #ORDER_ACCOUNT# (#ORDER_ID#).\n#SITE_NAME#",
            ],
            self::EVENT_BONUS_ADMIN => [
                'subject' => 'Начислены бонусы',
                'body' => "Здравствуйте, #NAME#!\n"
                    . "Начислено #BONUS_AMOUNT# (#BONUS_NAME#), активация #ACTIVATE_DATE#. #EXPIRE_DATE_TEXT#\n#SITE_NAME#",
            ],
            self::EVENT_BONUS_EXPIRE => [
                'subject' => 'Срок бонусов',
                'body' => "Здравствуйте, #NAME#!\n"
                    . "Через #DAYS_BEFORE# дн. сгорит #BONUS_AMOUNT# (#BONUS_NAME#), дата #EXPIRE_DATE#.\n#SITE_NAME#",
            ],
            self::EVENT_LEVEL_CHANGED => [
                'subject' => 'Уровень лояльности',
                'body' => "Здравствуйте, #NAME#!\nУровень: #OLD_LEVEL_NAME# -> #NEW_LEVEL_NAME#.\n#SITE_NAME#",
            ],
        ];
    }

    /** @return string[] */
    private static function collectSiteIds(): array {
        $out = [];
        $rs = \CSite::GetList('sort', 'desc', []);
        while ($row = $rs->Fetch()) {
            $out[] = (string)$row['ID'];
        }

        return $out;
    }

    /** @return string[] */
    private static function collectLanguageIds(): array {
        $out = [];
        if (class_exists('\CLanguage')) {
            $rs = \CLanguage::GetList('sort', 'asc', ['ACTIVE' => 'Y']);
            while ($row = $rs->Fetch()) {
                $out[] = (string)$row['LID'];
            }
        }
        if ($out === []) {
            $out[] = (defined('LANGUAGE_ID') && LANGUAGE_ID) ? (string)LANGUAGE_ID : 'ru';
        }

        return array_values(array_unique($out));
    }

    private static function ensureEventType(string $eventName, string $langLid, string $name, string $description): void {
        if (self::eventTypeExists($eventName, $langLid)) {
            return;
        }

        (new \CEventType())->Add([
            'EVENT_NAME' => $eventName,
            'NAME' => $name,
            'LID' => $langLid,
            'DESCRIPTION' => $description,
        ]);
    }

    private static function eventTypeExists(string $eventName, string $lid): bool {
        $connection = Application::getConnection();
        $h = $connection->getSqlHelper();
        $row = $connection->query(
            "SELECT ID FROM b_event_type WHERE EVENT_NAME = '" . $h->forSql($eventName) . "'"
            . " AND LID = '" . $h->forSql($lid) . "' LIMIT 1"
        )->fetch();

        return !empty($row['ID']);
    }

    private static function ensureMessageTemplate(
        string $eventName,
        string $subject,
        string $body,
        array $siteIds,
        string $emailFrom,
        string $bcc
    ): void {
        $connection = Application::getConnection();
        $h = $connection->getSqlHelper();
        $row = $connection->query(
            "SELECT ID FROM b_event_message WHERE EVENT_NAME = '" . $h->forSql($eventName) . "' LIMIT 1"
        )->fetch();
        if (!empty($row['ID'])) {
            return;
        }

        (new \CEventMessage())->Add([
            'ACTIVE' => 'Y',
            'EVENT_NAME' => $eventName,
            'SUBJECT' => $subject,
            'MESSAGE' => $body,
            'LID' => $siteIds,
            'EMAIL_FROM' => $emailFrom,
            'EMAIL_TO' => '#EMAIL#',
            'BCC' => $bcc,
            'BODY_TYPE' => 'text',
        ]);
    }
}
