<?php

namespace Legacy\Loyalty\Service;

use Bitrix\Main\Loader;
use Bitrix\Sale;
use Legacy\Loyalty\RuleBuilder\BonusRuleTable;

class BonusCalculator {
    public static function calculate(string $type, $source = null, array $context = []): array {
        $type = strtolower($type);
        if (!in_array($type, ['add', 'spend'], true)) {
            throw new \InvalidArgumentException('Unknown bonus rule type: ' . $type);
        }

        if (!ProgramService::isBonusEnabled()) {
            $orderContext = self::buildContext($source, $context);
            return [
                'type' => $type,
                'amount' => 0,
                'rawAmount' => 0.0,
                'rule' => null,
                'matchedItems' => [],
                'items' => self::formatItems($orderContext['items'], []),
                'balance' => $orderContext['balance'],
                'order' => self::formatOrderContext($orderContext),
                'rejectedRules' => [],
            ];
        }

        $orderContext = self::buildContext($source, $context);
        $rules = BonusRuleTable::getList([
            'filter' => ['=ACTIVE' => 'Y', '=TYPE' => $type],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);

        $rejectedRules = [];

        while ($rule = $rules->fetch()) {
            $ruleId = (int)$rule['ID'];

            if (!self::matchTree($rule['CONDITIONS_ORDER'] ?? [], $orderContext, 'order')) {
                $rejectedRules[] = [
                    'ruleId' => $ruleId,
                    'reason' => 'order_conditions_failed',
                ];
                continue;
            }

            $ruleAmount = 0.0;
            $matchedItems = [];

            foreach ($orderContext['items'] as $item) {
                if (!self::matchTree($rule['CONDITIONS_PRODUCT'] ?? [], $item, 'product')) {
                    continue;
                }

                $itemAmount = self::calculateItemAmount($rule, $item);
                if ($itemAmount <= 0) {
                    continue;
                }

                $ruleAmount += $itemAmount;
                $matchedItems[] = [
                    'basketId' => $item['basketId'],
                    'productId' => $item['productId'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'basePrice' => $item['basePrice'],
                    'discount' => $item['discount'],
                    'amount' => $itemAmount,
                ];
            }

            if ($ruleAmount <= 0) {
                $rejectedRules[] = [
                    'ruleId' => $ruleId,
                    'reason' => 'product_conditions_failed',
                ];
                continue;
            }

            $multiplier = ProgramService::isLevelEnabled()
                ? self::getBonusMultiplier($type, $orderContext['levelPrivileges'] ?? [])
                : 1.0;
            $ruleAmount *= $multiplier;
            if ($multiplier !== 1.0) {
                foreach ($matchedItems as &$matchedItem) {
                    $matchedItem['amount'] *= $multiplier;
                }
                unset($matchedItem);
            }
            $amount = (int)floor($ruleAmount);
            if ($type === 'spend') {
                $amount = min($amount, (int)$orderContext['balance']['available']);
            }

            return [
                'type' => $type,
                'amount' => $amount,
                'rawAmount' => $ruleAmount,
                'rule' => self::formatRule($rule),
                'matchedItems' => $matchedItems,
                'items' => self::formatItems($orderContext['items'], $matchedItems),
                'balance' => $orderContext['balance'],
                'order' => self::formatOrderContext($orderContext),
                'rejectedRules' => $rejectedRules,
            ];
        }

        return [
            'type' => $type,
            'amount' => 0,
            'rawAmount' => 0.0,
            'rule' => null,
            'matchedItems' => [],
            'items' => self::formatItems($orderContext['items'], []),
            'balance' => $orderContext['balance'],
            'order' => self::formatOrderContext($orderContext),
            'rejectedRules' => $rejectedRules,
        ];
    }

    public static function calculateAdd($source = null, array $context = []): array {
        return self::calculate('add', $source, $context);
    }

    public static function calculateSpend($source = null, array $context = []): array {
        return self::calculate('spend', $source, $context);
    }

    private static function buildContext($source, array $context): array {
        if (!Loader::includeModule('sale')) {
            throw new \RuntimeException('Sale module is required for bonus calculation.');
        }

        if (is_numeric($source)) {
            $source = Sale\Order::load((int)$source);
        }

        if ($source instanceof Sale\Order) {
            $context = self::contextFromOrder($source, $context);
        } elseif ($source instanceof Sale\BasketBase) {
            $context = self::contextFromBasket($source, $context);
        } else {
            $context = self::normalizeManualContext($context);
        }

        $context['userId'] = (int)($context['userId'] ?? 0);
        $context['userGroups'] = self::normalizeStringList($context['userGroups'] ?? []);
        if (empty($context['userGroups']) && $context['userId'] > 0) {
            $context['userGroups'] = self::normalizeStringList(\CUser::GetUserGroup($context['userId']));
        }

        if (!isset($context['userLevel'])) {
            $level = LevelService::getLevel($context['userId']);
            $context['userLevel'] = is_array($level) ? (int)($level['ID'] ?? 0) : 0;
            $context['levelPrivileges'] = is_array($level) ? ($level['PRIVILEGES'] ?? []) : [];
        }
        $context['levelPrivileges'] = LevelService::normalizePrivileges($context['levelPrivileges'] ?? []);
        $context['balance'] = $context['balance'] ?? BonusService::getBalance($context['userId']);
        $context['bonusPayment'] = !empty($context['bonusPayment']) ? 'Y' : 'N';
        $context['orderCount'] = (int)($context['orderCount'] ?? self::getUserOrderCount($context['userId']));
        $context['nextOrderNumber'] = (int)$context['orderCount'] + 1;
        $context['items'] = array_values($context['items'] ?? []);

        $context['cartSum'] = (float)($context['cartSum'] ?? array_sum(array_column($context['items'], 'finalPrice')));
        $context['deliverySum'] = (float)($context['deliverySum'] ?? 0);
        $context['orderSum'] = (float)($context['orderSum'] ?? ($context['cartSum'] + $context['deliverySum']));
        $context['itemCount'] = (float)($context['itemCount'] ?? self::countItems($context['items']));
        $context['siteId'] = (string)($context['siteId'] ?? (defined('SITE_ID') ? SITE_ID : ''));
        $context['deliveryIds'] = self::normalizeStringList($context['deliveryIds'] ?? []);
        $context['paymentSystemIds'] = self::normalizeStringList($context['paymentSystemIds'] ?? []);
        $context['personTypeId'] = (string)($context['personTypeId'] ?? '');

        return $context;
    }

    private static function contextFromOrder(Sale\Order $order, array $context): array {
        $basket = $order->getBasket();
        $context = self::contextFromBasket($basket, $context);

        $context['orderId'] = (int)$order->getId();
        $context['userId'] = (int)$order->getUserId();
        $context['siteId'] = (string)$order->getSiteId();
        $context['personTypeId'] = (string)$order->getPersonTypeId();
        $context['cartSum'] = (float)$basket->getPrice();
        $context['deliverySum'] = (float)$order->getDeliveryPrice();
        $context['orderSum'] = (float)$order->getPrice();
        $context['deliveryIds'] = self::normalizeStringList($order->getDeliverySystemId());

        $paymentSystemIds = [];
        $bonusPayment = false;
        foreach ($order->getPaymentCollection() as $payment) {
            $paymentSystemIds[] = $payment->getPaymentSystemId();
            if ((float)$payment->getSum() > 0 && $payment->isPaid()) {
                $bonusPayment = true;
            }
        }
        $context['paymentSystemIds'] = self::normalizeStringList($paymentSystemIds);
        $context['bonusPayment'] = $context['bonusPayment'] ?? $bonusPayment;

        return $context;
    }

    private static function contextFromBasket(Sale\BasketBase $basket, array $context): array {
        $items = [];
        foreach ($basket as $basketItem) {
            if (method_exists($basketItem, 'canBuy') && !$basketItem->canBuy()) {
                continue;
            }

            $items[] = self::buildItemContext([
                'basketId' => (int)$basketItem->getId(),
                'productId' => (int)$basketItem->getProductId(),
                'name' => (string)$basketItem->getField('NAME'),
                'quantity' => (float)$basketItem->getQuantity(),
                'basePrice' => (float)$basketItem->getField('BASE_PRICE'),
                'price' => (float)$basketItem->getPrice(),
                'discount' => (float)$basketItem->getField('DISCOUNT_PRICE'),
                'finalPrice' => (float)$basketItem->getFinalPrice(),
            ]);
        }

        $context['items'] = $items;
        $context['cartSum'] = (float)$basket->getPrice();
        $context['itemCount'] = self::countItems($items);

        return $context;
    }

    private static function normalizeManualContext(array $context): array {
        $items = [];
        foreach (($context['items'] ?? []) as $item) {
            $items[] = self::buildItemContext($item);
        }
        $context['items'] = $items;

        return $context;
    }

    private static function buildItemContext(array $item): array {
        $productId = (int)($item['productId'] ?? $item['PRODUCT_ID'] ?? $item['ID'] ?? 0);
        $quantity = (float)($item['quantity'] ?? $item['QUANTITY'] ?? 1);
        $price = (float)($item['price'] ?? $item['PRICE'] ?? 0);
        $basePrice = (float)($item['basePrice'] ?? $item['BASE_PRICE'] ?? $price);
        $discount = (float)($item['discount'] ?? $item['DISCOUNT_PRICE'] ?? max(0, $basePrice - $price));
        $finalPrice = (float)($item['finalPrice'] ?? $item['POSITION_FINAL_PRICE'] ?? $price * $quantity);

        $result = [
            'basketId' => (int)($item['basketId'] ?? $item['BASKET_ID'] ?? $item['ID'] ?? 0),
            'productId' => $productId,
            'name' => (string)($item['name'] ?? $item['NAME'] ?? ''),
            'quantity' => $quantity,
            'basePrice' => $basePrice,
            'price' => $price,
            'discount' => $discount,
            'finalPrice' => $finalPrice,
            'iblockId' => (int)($item['iblockId'] ?? $item['IBLOCK_ID'] ?? 0),
            'sectionIds' => self::normalizeIntList($item['sectionIds'] ?? $item['SECTIONS'] ?? []),
            'properties' => $item['properties'] ?? $item['PROPERTIES'] ?? [],
            'parentProductId' => (int)($item['parentProductId'] ?? $item['PARENT_PRODUCT_ID'] ?? 0),
            'parentIblockId' => (int)($item['parentIblockId'] ?? $item['PARENT_IBLOCK_ID'] ?? 0),
        ];

        if ($productId > 0 && ($result['iblockId'] <= 0 || empty($result['sectionIds']) || empty($result['properties']))) {
            $result = self::loadProductData($result);
        }

        return $result;
    }

    private static function loadProductData(array $item): array {
        if (!Loader::includeModule('iblock')) {
            return $item;
        }

        if (Loader::includeModule('catalog') && empty($item['parentProductId'])) {
            $skuInfo = \CCatalogSku::GetProductInfo((int)$item['productId']);
            if (is_array($skuInfo) && !empty($skuInfo['ID'])) {
                $item['parentProductId'] = (int)$skuInfo['ID'];
            }
        }

        $element = \CIBlockElement::GetList(
            [],
            ['=ID' => (int)$item['productId']],
            false,
            false,
            ['ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID']
        )->GetNextElement();

        if (!$element) {
            return $item;
        }

        $fields = $element->GetFields();
        $item['iblockId'] = (int)($fields['IBLOCK_ID'] ?? $item['iblockId']);

        if (empty($item['sectionIds'])) {
            $sections = [];
            $sectionRes = \CIBlockElement::GetElementGroups((int)$item['productId'], true, ['ID']);
            while ($section = $sectionRes->Fetch()) {
                $sections[] = (int)$section['ID'];
            }
            if (empty($sections) && !empty($fields['IBLOCK_SECTION_ID'])) {
                $sections[] = (int)$fields['IBLOCK_SECTION_ID'];
            }
            $item['sectionIds'] = $sections;
        }

        if (!empty($item['parentProductId'])) {
            $parentElement = \CIBlockElement::GetList(
                [],
                ['=ID' => (int)$item['parentProductId']],
                false,
                false,
                ['ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID']
            )->GetNextElement();

            if ($parentElement) {
                $parentFields = $parentElement->GetFields();
                $item['parentIblockId'] = (int)($parentFields['IBLOCK_ID'] ?? 0);
            }

            $parentSections = [];
            $parentSectionRes = \CIBlockElement::GetElementGroups((int)$item['parentProductId'], true, ['ID']);
            while ($section = $parentSectionRes->Fetch()) {
                $parentSections[] = (int)$section['ID'];
            }
            $item['sectionIds'] = array_values(array_unique(array_merge($item['sectionIds'], $parentSections)));
        }

        if (empty($item['properties'])) {
            $item['properties'] = $element->GetProperties();
        }

        if (!empty($item['parentProductId']) && !empty($parentElement)) {
            $item['properties'] = array_replace($parentElement->GetProperties(), $item['properties']);
        }

        return $item;
    }

    private static function calculateItemAmount(array $rule, array $item): float {
        $amount = (float)($rule['AMOUNT'] ?? 0);
        if ($amount <= 0) {
            return 0.0;
        }

        if (($rule['AMOUNT_TYPE'] ?? '') === 'percent') {
            return $item['finalPrice'] * $amount / 100;
        }

        return $amount * max(1, (float)$item['quantity']);
    }

    private static function getBonusMultiplier(string $type, array $privileges): float {
        $key = $type === 'spend' ? 'spendBonusMultiplier' : 'addBonusMultiplier';
        return max(0.0, (float)($privileges[$key] ?? 1));
    }

    private static function matchTree($tree, array $context, string $scope): bool {
        if (!is_array($tree) || empty($tree)) {
            return true;
        }

        $children = array_values($tree['children'] ?? []);
        if (empty($children)) {
            return true;
        }

        $all = (($tree['values']['All'] ?? 'AND') === 'OR') ? 'OR' : 'AND';
        $true = (($tree['values']['True'] ?? 'True') !== 'False');
        $matched = self::matchChildren($children, $context, $scope, $all);

        return $true ? $matched : !$matched;
    }

    private static function matchChildren(array $children, array $context, string $scope, string $all): bool {
        if ($all === 'OR') {
            foreach ($children as $child) {
                if (self::matchNode($child, $context, $scope)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($children as $child) {
            if (!self::matchNode($child, $context, $scope)) {
                return false;
            }
        }

        return true;
    }

    private static function matchNode(array $node, array $context, string $scope): bool {
        if (($node['controlId'] ?? '') === 'CondGroup') {
            return self::matchTree($node, $context, $scope);
        }

        if (!empty($node['children'])) {
            return self::matchTree($node, $context, $scope);
        }

        if ($scope === 'order') {
            return self::matchOrderCondition($node, $context);
        }

        return self::matchProductCondition($node, $context);
    }

    private static function matchOrderCondition(array $node, array $context): bool {
        $id = (string)($node['controlId'] ?? '');
        $values = $node['values'] ?? [];
        $logic = (string)($values['logic'] ?? 'Equal');

        switch ($id) {
            case 'site':
                return self::compareList($context['siteId'], $values['value'] ?? [], $logic);
            case 'userGroups':
                return self::compareList($context['userGroups'], $values['value'] ?? [], $logic);
            case 'cartSum':
                return self::compareNumber($context['cartSum'], $values['value'] ?? 0, $logic);
            case 'itemCount':
                return self::compareNumber($context['itemCount'], $values['value'] ?? 0, $logic);
            case 'orderSum':
                return self::compareNumber($context['orderSum'], $values['value'] ?? 0, $logic);
            case 'delivery':
                return self::compareList($context['deliveryIds'], $values['value'] ?? [], $logic);
            case 'bonusPayment':
                return (string)($context['bonusPayment'] ?? 'N') === (string)($values['value'] ?? '');
            case 'userLevel':
                $levelId = (int)($context['userLevel'] ?? 0);
                return self::compareList([(string)$levelId, 'level_' . $levelId], $values['value'] ?? [], $logic);
            case 'everyNthOrder':
                $n = max(1, (int)($values['value'] ?? 1));
                return ((int)$context['nextOrderNumber']) % $n === 0;
            case 'onlyNthOrder':
                $n = max(1, (int)($values['value'] ?? 1));
                return ((int)$context['nextOrderNumber']) === $n;
            case 'personTypes':
                return self::compareList($context['personTypeId'], $values['value'] ?? [], $logic);
            case 'paymentSystem':
                return self::compareList($context['paymentSystemIds'], $values['value'] ?? [], $logic);
        }

        return true;
    }

    private static function matchProductCondition(array $node, array $item): bool {
        $id = (string)($node['controlId'] ?? '');
        $values = $node['values'] ?? [];
        $logic = (string)($values['logic'] ?? 'Equal');

        switch ($id) {
            case 'iblock':
                return self::compareList([$item['iblockId'], $item['parentIblockId'] ?? 0], $values['value'] ?? [], $logic);
            case 'section':
                return self::compareList($item['sectionIds'], $values['value'] ?? [], $logic);
            case 'product':
                return self::compareList([$item['productId'], $item['parentProductId'] ?? 0], $values['value'] ?? [], $logic);
            case 'productPrice':
                return self::compareNumber($item['price'], $values['value'] ?? 0, $logic);
            case 'hasDiscount':
                return (($item['discount'] ?? 0) > 0 ? 'Y' : 'N') === (string)($values['value'] ?? '');
        }

        if (strpos($id, 'CondIBProp') === 0) {
            return self::matchIblockPropertyCondition($id, $values, $item);
        }

        return true;
    }

    private static function matchIblockPropertyCondition(string $controlId, array $values, array $item): bool {
        $parts = explode(':', $controlId);
        $propId = isset($parts[2]) ? (int)$parts[2] : 0;
        if ($propId <= 0) {
            return true;
        }

        $property = $item['properties'][$propId] ?? null;
        if ($property === null) {
            foreach ($item['properties'] as $candidate) {
                if ((int)($candidate['ID'] ?? 0) === $propId) {
                    $property = $candidate;
                    break;
                }
            }
        }

        if ($property === null) {
            return false;
        }

        $actual = $property['VALUE_ENUM_ID'] ?? $property['VALUE'] ?? null;
        $logic = (string)($values['logic'] ?? 'Equal');
        $expected = $values['value'] ?? null;

        if (is_array($actual) || is_array($expected)) {
            return self::compareList($actual, $expected, $logic);
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return self::compareNumber($actual, $expected, $logic);
        }

        return self::compareScalar((string)$actual, (string)$expected, $logic);
    }

    private static function compareNumber($actual, $expected, string $logic): bool {
        $actual = (float)str_replace(',', '.', (string)$actual);
        $expected = (float)str_replace(',', '.', (string)$expected);

        switch ($logic) {
            case 'Not':
                return $actual != $expected;
            case 'Greater':
            case 'Great':
            case 'EqGr':
                return $actual > $expected;
            case 'Less':
                return $actual < $expected;
            case 'GreaterEqual':
                return $actual >= $expected;
            case 'LessEqual':
            case 'EqLs':
                return $actual <= $expected;
            case 'Equal':
            default:
                return $actual == $expected;
        }
    }

    private static function compareList($actual, $expected, string $logic): bool {
        $actual = self::normalizeStringList($actual);
        $expected = self::normalizeStringList($expected);

        if (empty($expected)) {
            return true;
        }

        $hasIntersection = count(array_intersect($actual, $expected)) > 0;

        return $logic === 'Not' ? !$hasIntersection : $hasIntersection;
    }

    private static function compareScalar(string $actual, string $expected, string $logic): bool {
        switch ($logic) {
            case 'Not':
                return $actual !== $expected;
            case 'Contain':
                return strpos($actual, $expected) !== false;
            case 'NotCont':
                return strpos($actual, $expected) === false;
            case 'Equal':
            default:
                return $actual === $expected;
        }
    }

    private static function getUserOrderCount(int $userId): int {
        if ($userId <= 0 || !Loader::includeModule('sale')) {
            return 0;
        }

        $row = Sale\Order::getList([
            'filter' => [
                '=USER_ID' => $userId,
                '=STATUS_ID' => 'F',
                '=CANCELED' => 'N',
            ],
            'select' => ['CNT'],
            'runtime' => [
                new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)'),
            ],
        ])->fetch();

        return (int)($row['CNT'] ?? 0);
    }

    private static function countItems(array $items): float {
        $count = 0.0;
        foreach ($items as $item) {
            $count += (float)($item['quantity'] ?? 0);
        }
        return $count;
    }

    private static function normalizeStringList($value): array {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_array($value)) {
            $value = [$value];
        }

        $result = [];
        foreach ($value as $item) {
            if ($item === null || $item === '') {
                continue;
            }
            $result[] = (string)$item;
        }

        return array_values(array_unique($result));
    }

    private static function normalizeIntList($value): array {
        if (!is_array($value)) {
            $value = $value !== null && $value !== '' ? [$value] : [];
        }

        $value = array_filter($value, static function ($item) {
            return $item !== null && $item !== '';
        });

        return array_values(array_unique(array_map('intval', $value)));
    }

    private static function formatRule(array $rule): array {
        return [
            'id' => (int)$rule['ID'],
            'name' => (string)($rule['NAME'] ?? ''),
            'sort' => (int)($rule['SORT'] ?? 100),
            'type' => (string)($rule['TYPE'] ?? ''),
            'amountType' => (string)($rule['AMOUNT_TYPE'] ?? ''),
            'amount' => (float)($rule['AMOUNT'] ?? 0),
        ];
    }

    private static function formatOrderContext(array $context): array {
        return [
            'orderId' => (int)($context['orderId'] ?? 0),
            'userId' => (int)($context['userId'] ?? 0),
            'siteId' => (string)($context['siteId'] ?? ''),
            'cartSum' => (float)($context['cartSum'] ?? 0),
            'deliverySum' => (float)($context['deliverySum'] ?? 0),
            'orderSum' => (float)($context['orderSum'] ?? 0),
            'itemCount' => (float)($context['itemCount'] ?? 0),
            'orderCount' => (int)($context['orderCount'] ?? 0),
            'nextOrderNumber' => (int)($context['nextOrderNumber'] ?? 1),
        ];
    }

    private static function formatItems(array $items, array $matchedItems): array {
        $amountByBasket = [];
        $amountByProduct = [];
        foreach ($matchedItems as $item) {
            if (!empty($item['basketId'])) {
                $amountByBasket[(int)$item['basketId']] = (float)$item['amount'];
            }
            $amountByProduct[(int)$item['productId']] = (float)$item['amount'];
        }

        $result = [];
        foreach ($items as $item) {
            $basketId = (int)($item['basketId'] ?? 0);
            $productId = (int)($item['productId'] ?? 0);
            $amount = $basketId > 0 && isset($amountByBasket[$basketId])
                ? $amountByBasket[$basketId]
                : ($amountByProduct[$productId] ?? 0.0);

            $result[] = [
                'basketId' => $basketId,
                'productId' => $productId,
                'name' => (string)($item['name'] ?? ''),
                'quantity' => (float)($item['quantity'] ?? 0),
                'price' => (float)($item['price'] ?? 0),
                'finalPrice' => (float)($item['finalPrice'] ?? 0),
                'amount' => (int)floor($amount),
                'rawAmount' => $amount,
                'matched' => $amount > 0,
            ];
        }

        return $result;
    }
}
