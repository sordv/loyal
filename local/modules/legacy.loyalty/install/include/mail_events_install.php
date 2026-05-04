<?php

use Bitrix\Main\Loader;

if (!Loader::includeModule('main')) {
    return;
}

$lang = defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru';
$langFile = __DIR__ . '/../../lang/' . $lang . '/install/include/mail_events_install.php';
if (!is_file($langFile)) {
    $langFile = __DIR__ . '/../../lang/ru/install/include/mail_events_install.php';
}
if (is_file($langFile)) {
    include $langFile;
}

$sites = [];
$rsSites = CSite::GetList($by = 'sort', $order = 'desc', []);
while ($arSite = $rsSites->Fetch()) {
    $sites[] = $arSite['ID'];
}
if ($sites === []) {
    $sites[] = 's1';
}

$emailFrom = COption::GetOptionString('main', 'email_from', '');
$bcc = COption::GetOptionString('main', 'all_bcc', '');

$mailEvents = [
    [
        'EVENT' => [
            'EVENT_NAME' => 'LEGACY_LOYALTY_BONUS_ORDER_ADD',
            'NAME' => GetMessage('LEGACY_LOYALTY_MAIL_EV_BONUS_ORDER_NAME'),
            'LID' => 'ru',
            'DESCRIPTION' => GetMessage('LEGACY_LOYALTY_MAIL_EV_BONUS_ORDER_DESC'),
        ],
        'TEMPLATE' => [
            'ACTIVE' => 'Y',
            'EVENT_NAME' => 'LEGACY_LOYALTY_BONUS_ORDER_ADD',
            'SUBJECT' => GetMessage('LEGACY_LOYALTY_MAIL_MT_BONUS_ORDER_SUBJ'),
            'MESSAGE' => GetMessage('LEGACY_LOYALTY_MAIL_MT_BONUS_ORDER_BODY'),
            'LID' => $sites,
            'EMAIL_FROM' => $emailFrom,
            'EMAIL_TO' => '#EMAIL#',
            'BCC' => $bcc,
            'BODY_TYPE' => 'html',
        ],
    ],
    [
        'EVENT' => [
            'EVENT_NAME' => 'LEGACY_LOYALTY_BONUS_ADMIN_ADD',
            'NAME' => GetMessage('LEGACY_LOYALTY_MAIL_EV_BONUS_ADMIN_NAME'),
            'LID' => 'ru',
            'DESCRIPTION' => GetMessage('LEGACY_LOYALTY_MAIL_EV_BONUS_ADMIN_DESC'),
        ],
        'TEMPLATE' => [
            'ACTIVE' => 'Y',
            'EVENT_NAME' => 'LEGACY_LOYALTY_BONUS_ADMIN_ADD',
            'SUBJECT' => GetMessage('LEGACY_LOYALTY_MAIL_MT_BONUS_ADMIN_SUBJ'),
            'MESSAGE' => GetMessage('LEGACY_LOYALTY_MAIL_MT_BONUS_ADMIN_BODY'),
            'LID' => $sites,
            'EMAIL_FROM' => $emailFrom,
            'EMAIL_TO' => '#EMAIL#',
            'BCC' => $bcc,
            'BODY_TYPE' => 'html',
        ],
    ],
    [
        'EVENT' => [
            'EVENT_NAME' => 'LEGACY_LOYALTY_BONUS_EXPIRE_WARNING',
            'NAME' => GetMessage('LEGACY_LOYALTY_MAIL_EV_BONUS_EXPIRE_NAME'),
            'LID' => 'ru',
            'DESCRIPTION' => GetMessage('LEGACY_LOYALTY_MAIL_EV_BONUS_EXPIRE_DESC'),
        ],
        'TEMPLATE' => [
            'ACTIVE' => 'Y',
            'EVENT_NAME' => 'LEGACY_LOYALTY_BONUS_EXPIRE_WARNING',
            'SUBJECT' => GetMessage('LEGACY_LOYALTY_MAIL_MT_BONUS_EXPIRE_SUBJ'),
            'MESSAGE' => GetMessage('LEGACY_LOYALTY_MAIL_MT_BONUS_EXPIRE_BODY'),
            'LID' => $sites,
            'EMAIL_FROM' => $emailFrom,
            'EMAIL_TO' => '#EMAIL#',
            'BCC' => $bcc,
            'BODY_TYPE' => 'html',
        ],
    ],
    [
        'EVENT' => [
            'EVENT_NAME' => 'LEGACY_LOYALTY_LEVEL_CHANGED',
            'NAME' => GetMessage('LEGACY_LOYALTY_MAIL_EV_LEVEL_NAME'),
            'LID' => 'ru',
            'DESCRIPTION' => GetMessage('LEGACY_LOYALTY_MAIL_EV_LEVEL_DESC'),
        ],
        'TEMPLATE' => [
            'ACTIVE' => 'Y',
            'EVENT_NAME' => 'LEGACY_LOYALTY_LEVEL_CHANGED',
            'SUBJECT' => GetMessage('LEGACY_LOYALTY_MAIL_MT_LEVEL_SUBJ'),
            'MESSAGE' => GetMessage('LEGACY_LOYALTY_MAIL_MT_LEVEL_BODY'),
            'LID' => $sites,
            'EMAIL_FROM' => $emailFrom,
            'EMAIL_TO' => '#EMAIL#',
            'BCC' => $bcc,
            'BODY_TYPE' => 'html',
        ],
    ],
];

foreach ($mailEvents as $event) {
    $exists = CEventType::GetList([], [
        'EVENT_NAME' => $event['EVENT']['EVENT_NAME'],
        'LID' => $event['EVENT']['LID'],
    ])->Fetch();
    if (!$exists) {
        $obEventType = new CEventType();
        $obEventType->Add($event['EVENT']);
    }

    $hasTemplate = CEventMessage::GetList($by = 'site_id', $order = 'desc', [
        'TYPE_ID' => $event['TEMPLATE']['EVENT_NAME'],
    ])->Fetch();
    if (!$hasTemplate) {
        $obTemplate = new CEventMessage();
        $obTemplate->Add($event['TEMPLATE']);
    }
}
