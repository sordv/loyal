<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Sale;
use Legacy\Loyalty\Service\BonusCalculator;
use Legacy\Loyalty\Service\LevelService;
use Legacy\Loyalty\Service\ProgramService;

if (!Loader::includeModule('legacy.loyalty') || !Loader::includeModule('sale')) {
    return;
}

if (Option::get('legacy.loyalty', 'integration_show_cart_bonus', 'Y') !== 'Y') {
    return;
}

$bonusEnabled = ProgramService::isBonusEnabled();
$levelEnabled = ProgramService::isLevelEnabled();
if (!$bonusEnabled && !$levelEnabled) {
    return;
}

global $USER;

$request = Context::getCurrent()->getRequest();
$siteId = Context::getCurrent()->getSite();
$userId = is_object($USER) && $USER->IsAuthorized() ? (int)$USER->GetID() : 0;
$basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), $siteId);

$showItems = ($arParams['SHOW_ITEM_BONUS'] ?? 'Y') === 'Y'
    && Option::get('legacy.loyalty', 'integration_show_cart_item_bonus', 'Y') === 'Y';
$showOrder = ($arParams['SHOW_ORDER_BONUS'] ?? 'Y') === 'Y'
    && Option::get('legacy.loyalty', 'integration_show_cart_order_bonus', 'Y') === 'Y';
$showSpend = ($arParams['SHOW_SPEND_BONUS'] ?? 'Y') === 'Y'
    && Option::get('legacy.loyalty', 'integration_show_cart_spend_bonus', 'Y') === 'Y';

$context = [
    'userId' => $userId,
    'siteId' => $siteId,
];

$deliveryIds = $request->getPost('DELIVERY_ID') ?: $request->getPost('delivery_id');
if ($deliveryIds) {
    $context['deliveryIds'] = $deliveryIds;
}

$paymentSystemIds = $request->getPost('PAY_SYSTEM_ID') ?: $request->getPost('pay_system_id');
if ($paymentSystemIds) {
    $context['paymentSystemIds'] = $paymentSystemIds;
}

$personTypeId = $request->getPost('PERSON_TYPE') ?: $request->getPost('PERSON_TYPE_ID') ?: $request->getPost('person_type_id');
if ($personTypeId) {
    $context['personTypeId'] = $personTypeId;
}

$bonusPayment = $request->getPost('legacy_loyalty_has_bonus_payment');
if ($bonusPayment !== null) {
    $context['bonusPayment'] = $bonusPayment === 'Y' || $bonusPayment === '1';
}

$add = $bonusEnabled ? BonusCalculator::calculateAdd($basket, $context) : null;
$spend = $bonusEnabled ? BonusCalculator::calculateSpend($basket, $context) : null;
$level = $levelEnabled && $userId > 0 ? LevelService::getLevel($userId) : null;
$levelPrivileges = is_array($level) ? LevelService::normalizePrivileges($level['PRIVILEGES'] ?? []) : [];

$requestedSpend = (int)($request->getPost('legacy_loyalty_spend') ?: $request->get('legacy_loyalty_spend'));
if ($requestedSpend < 0) {
    $requestedSpend = 0;
}

$acceptedSpend = $bonusEnabled ? min($requestedSpend, (int)$spend['amount']) : 0;

if (!$bonusEnabled) {
    $emptyCalculation = [
        'amount' => 0,
        'rule' => null,
        'items' => [],
        'balance' => ['available' => 0],
    ];
    $add = $emptyCalculation;
    $spend = $emptyCalculation;
}

$arResult = [
    'BONUS_ENABLED' => $bonusEnabled,
    'LEVEL_ENABLED' => $levelEnabled,
    'SHOW_ITEMS' => $bonusEnabled && $showItems,
    'SHOW_ORDER' => $bonusEnabled && $showOrder,
    'SHOW_SPEND' => $bonusEnabled && $showSpend,
    'BONUS_NAME' => ProgramService::getBonusDisplayName(),
    'ADD' => $add,
    'SPEND' => $spend,
    'LEVEL' => $level,
    'LEVEL_PRIVILEGES' => $levelPrivileges,
    'REQUESTED_SPEND' => $requestedSpend,
    'ACCEPTED_SPEND' => $acceptedSpend,
    'IS_AUTHORIZED' => $userId > 0,
    'IS_EMPTY' => $bonusEnabled ? empty($add['items']) : false,
    'AJAX_URL' => $componentPath . '/ajax.php',
    'COMPONENT_PARAMS' => [
        'SHOW_ITEM_BONUS' => $arParams['SHOW_ITEM_BONUS'] ?? 'Y',
        'SHOW_ORDER_BONUS' => $arParams['SHOW_ORDER_BONUS'] ?? 'Y',
        'SHOW_SPEND_BONUS' => $arParams['SHOW_SPEND_BONUS'] ?? 'Y',
    ],
];

$this->IncludeComponentTemplate();
