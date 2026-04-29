<?php
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Application;

$request = Application::getInstance()->getContext()->getRequest();

$APPLICATION->IncludeComponent(
    'legacy:loyalty.cart.bonus',
    '',
    [
        'SHOW_ITEM_BONUS' => $request->getPost('SHOW_ITEM_BONUS') ?: 'Y',
        'SHOW_ORDER_BONUS' => $request->getPost('SHOW_ORDER_BONUS') ?: 'Y',
        'SHOW_SPEND_BONUS' => $request->getPost('SHOW_SPEND_BONUS') ?: 'Y',
        'CACHE_TIME' => '0',
    ]
);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
