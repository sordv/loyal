<?php

if (!\Bitrix\Main\Loader::includeModule('main')) {
    return;
}

$types = [
    'LEGACY_LOYALTY_BONUS_ORDER_ADD',
    'LEGACY_LOYALTY_BONUS_ADMIN_ADD',
    'LEGACY_LOYALTY_BONUS_EXPIRE_WARNING',
    'LEGACY_LOYALTY_LEVEL_CHANGED',
];

$rsMess = CEventMessage::GetList($by = 'site_id', $order = 'desc', ['TYPE_ID' => $types]);
while ($mess = $rsMess->Fetch()) {
    CEventMessage::Delete($mess['ID']);
}

$et = new CEventType();
foreach ($types as $typeId) {
    $et->Delete($typeId);
}
