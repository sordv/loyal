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
$orderPost = $request->getPost('order');
if (!is_array($orderPost)) {
    $orderPost = [];
}
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

$deliveryIds = $request->getPost('DELIVERY_ID')
    ?: ($orderPost['DELIVERY_ID'] ?? null)
    ?: $request->getPost('delivery_id');
if ($deliveryIds) {
    $context['deliveryIds'] = $deliveryIds;
}

$paymentSystemIds = $request->getPost('PAY_SYSTEM_ID')
    ?: ($orderPost['PAY_SYSTEM_ID'] ?? null)
    ?: $request->getPost('pay_system_id');
if ($paymentSystemIds) {
    $context['paymentSystemIds'] = $paymentSystemIds;
}

$personTypeId = $request->getPost('PERSON_TYPE')
    ?: ($orderPost['PERSON_TYPE'] ?? null)
    ?: $request->getPost('PERSON_TYPE_ID')
    ?: $request->getPost('person_type_id');
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

$requestedSpendRaw = $request->getPost('LEGACY_LOYALTY_SPEND')
    ?: ($orderPost['LEGACY_LOYALTY_SPEND'] ?? null)
    ?: ($orderPost['LEGACY_LOYALTY_SPEND_ACCEPTED'] ?? null)
    ?: $request->getPost('legacy_loyalty_spend')
    ?: $request->get('LEGACY_LOYALTY_SPEND')
    ?: $request->get('legacy_loyalty_spend');
$requestedSpend = (int)$requestedSpendRaw;
if ($requestedSpend < 0) {
    $requestedSpend = 0;
}

$acceptedSpend = $bonusEnabled ? min($requestedSpend, (int)$spend['amount']) : 0;

$_SESSION['LEGACY_LOYALTY_SPEND_REQUESTED'] = $requestedSpend;
$_SESSION['LEGACY_LOYALTY_SPEND_ACCEPTED'] = $acceptedSpend;

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

$paymentBonusPropIds = [];
if (Loader::includeModule('sale')) {
    $personTypes = \CSalePersonType::GetList(['SORT' => 'ASC'], []);
    while ($personType = $personTypes->Fetch()) {
        $personTypeId = (int)$personType['ID'];
        $props = \CSaleOrderProps::GetList(
            ['SORT' => 'ASC'],
            ['PERSON_TYPE_ID' => $personTypeId, 'CODE' => 'LEGACY_LOYALTY_PAYMENT_BONUS'],
            false,
            false,
            ['ID']
        );
        if ($prop = $props->Fetch()) {
            $paymentBonusPropIds[$personTypeId] = (int)$prop['ID'];
        }
    }
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
    'PAYMENT_BONUS_PROP_IDS' => $paymentBonusPropIds,
    'COMPONENT_PARAMS' => [
        'SHOW_ITEM_BONUS' => $arParams['SHOW_ITEM_BONUS'] ?? 'Y',
        'SHOW_ORDER_BONUS' => $arParams['SHOW_ORDER_BONUS'] ?? 'Y',
        'SHOW_SPEND_BONUS' => $arParams['SHOW_SPEND_BONUS'] ?? 'Y',
    ],
];

$this->IncludeComponentTemplate();
