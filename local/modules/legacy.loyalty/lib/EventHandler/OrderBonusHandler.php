<?php

namespace Legacy\Loyalty\EventHandler;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Legacy\Loyalty\Service\BonusCalculator;
use Legacy\Loyalty\Service\BonusService;
use Legacy\Loyalty\Service\LevelService;
use Legacy\Loyalty\Service\ProgramService;

class OrderBonusHandler {
    /** @var array<string, bool> */
    private static array $spendDiscountApplied = [];

    public static function onSaleOrderBeforeSaved($event): void {
        self::safeRun(static function () use ($event) {
            $order = self::extractOrder($event);
            if (!$order || !ProgramService::isBonusEnabled()) {
                return;
            }
            self::applySpendDiscount($order);
        }, __METHOD__);
    }

    public static function onSaleComponentOrderCreated(
        $order,
        &$arUserResult,
        $request,
        &$arParams,
        &$arResult
    ): void {
        self::safeRun(static function () use ($order) {
            if (!ProgramService::isBonusEnabled() || !($order instanceof Order)) {
                return;
            }
            self::applySpendDiscount($order);
        }, __METHOD__);
    }

    public static function onSaleComponentOrderResultPrepared(
        $order,
        &$arUserResult,
        $request,
        &$arParams,
        &$arResult
    ): void {
        self::safeRun(static function () use ($order) {
            if (!ProgramService::isBonusEnabled() || !($order instanceof Order)) {
                return;
            }
            self::applySpendDiscount($order);
        }, __METHOD__);
    }

    public static function onSaleOrderSaved($event): void {
        self::safeRun(static function () use ($event) {
            $order = self::extractOrder($event);
            if (!$order || !ProgramService::isBonusEnabled()) {
                return;
            }
            self::processBySettings($order);
        }, __METHOD__);
    }

    public static function onSaleOrderPaid($event): void {
        self::safeRun(static function () use ($event) {
            $order = self::extractOrder($event);
            if (!$order || !ProgramService::isBonusEnabled()) {
                return;
            }
            if (!ProgramService::isBonusAccrualOnPaidEnabled()) {
                return;
            }
            if (method_exists($order, 'isPaid') && !$order->isPaid()) {
                return;
            }
            self::finalizeOrderBonusOperations($order);
        }, __METHOD__);
    }

    public static function onSaleStatusOrderChange($event): void {
        self::safeRun(static function () use ($event) {
            $order = self::extractOrder($event);
            if (!$order || !ProgramService::isBonusEnabled()) {
                return;
            }

            $requiredStatus = ProgramService::getBonusAccrualOrderStatus();
            if ($requiredStatus === '') {
                return;
            }
            if ((string)$order->getField('STATUS_ID') !== $requiredStatus) {
                return;
            }

            self::finalizeOrderBonusOperations($order);
        }, __METHOD__);
    }

    private static function processBySettings(Order $order): void {
        $statusMatch = false;
        $status = ProgramService::getBonusAccrualOrderStatus();
        if ($status !== '' && (string)$order->getField('STATUS_ID') === $status) {
            $statusMatch = true;
        }

        $paidMatch = ProgramService::isBonusAccrualOnPaidEnabled()
            && method_exists($order, 'isPaid')
            && $order->isPaid();

        if (!$statusMatch && !$paidMatch) {
            return;
        }

        self::finalizeOrderBonusOperations($order);
    }

    private static function finalizeOrderBonusOperations(Order $order): void {
        $userId = (int)$order->getUserId();
        $orderId = (int)$order->getId();
        if ($userId <= 0 || $orderId <= 0) {
            return;
        }

        if (!BonusService::hasOrderOperation($orderId, 'spend')) {
            $spendAmount = self::resolveSpendAmount($order);
            if ($spendAmount > 0) {
                BonusService::spendBonus($userId, $spendAmount, $orderId);
            }
        }

        if (!BonusService::hasOrderOperation($orderId, 'add')) {
            $addResult = BonusCalculator::calculateAdd($order, ['userId' => $userId]);
            $amount = (int)($addResult['amount'] ?? 0);
            if ($amount > 0) {
                BonusService::addBonus($userId, $amount, $orderId);
            }
        }
    }

    private static function applySpendDiscount(Order $order): void {
        $spendAmount = self::resolveSpendAmount($order);
        if ($spendAmount <= 0) {
            return;
        }

        $applyKey = self::buildSpendApplyKey($order, $spendAmount);
        if (isset(self::$spendDiscountApplied[$applyKey])) {
            return;
        }
        self::$spendDiscountApplied[$applyKey] = true;

        $basket = $order->getBasket();
        if (!$basket) {
            return;
        }

        $levelRatio = self::resolveLevelCartDiscountRatio($order);
        $items = [];
        $total = 0.0;
        foreach ($basket as $basketItem) {
            if (method_exists($basketItem, 'canBuy') && !$basketItem->canBuy()) {
                continue;
            }
            $final = (float)$basketItem->getFinalPrice();
            if ($levelRatio > 0) {
                $base = (float)$basketItem->getField('BASE_PRICE');
                if ($base <= 0) {
                    $base = (float)$basketItem->getPrice();
                }
                if ($base > 0) {
                    $levelPrice = max(0.0, round($base * (1 - $levelRatio), 2));
                    $final = min($final, $levelPrice);
                }
            }
            if ($final <= 0) {
                continue;
            }
            $items[] = ['item' => $basketItem, 'final' => $final];
            $total += $final;
        }
        if ($total <= 0) {
            return;
        }

        $spendAmount = min($spendAmount, (int)floor($total));
        if ($spendAmount <= 0) {
            return;
        }

        $remaining = (float)$spendAmount;
        $lastIndex = count($items) - 1;

        foreach ($items as $index => $row) {
            $basketItem = $row['item'];
            $itemFinal = $row['final'];

            $part = $index === $lastIndex
                ? $remaining
                : round($spendAmount * ($itemFinal / $total), 2);

            if ($part <= 0) {
                continue;
            }

            $remaining = max(0.0, $remaining - $part);

            $quantity = (float)$basketItem->getQuantity();
            if ($quantity <= 0) {
                continue;
            }

            $newItemFinal = max(0.0, $itemFinal - $part);
            $newPrice = round($newItemFinal / $quantity, 2);
            $basePrice = (float)$basketItem->getField('BASE_PRICE');
            if ($basePrice <= 0) {
                $basePrice = (float)$basketItem->getPrice();
            }

            if (method_exists($basketItem, 'markFieldCustom')) {
                $basketItem->markFieldCustom('PRICE');
            } else {
                $basketItem->setField('CUSTOM_PRICE', 'Y');
            }

            $basketItem->setField('PRICE', $newPrice);
            $basketItem->setField('BASE_PRICE', $basePrice);
            $basketItem->setField('DISCOUNT_PRICE', max(0, $basePrice - $newPrice));
        }
    }

    private static function resolveLevelCartDiscountRatio(Order $order): float {
        if (!ProgramService::isLevelEnabled()) {
            return 0.0;
        }

        $level = LevelService::getLevel((int)$order->getUserId());
        if (!is_array($level)) {
            return 0.0;
        }

        $privileges = LevelService::normalizePrivileges($level['PRIVILEGES'] ?? []);
        $percent = (float)($privileges['cartDiscountPercent'] ?? 0);
        if ($percent <= 0) {
            return 0.0;
        }

        return max(0.0, min(100.0, $percent)) / 100.0;
    }

    private static function resolveSpendAmount(Order $order): int {
        $propAmount = self::resolveSpendAmountFromOrderProperty($order);
        if ($propAmount > 0) {
            $amount = $propAmount;
        } else {
            $amount = self::resolveSpendAmountFromRequest();
            if ($amount <= 0) {
                $amount = (int)($_SESSION['LEGACY_LOYALTY_SPEND_ACCEPTED'] ?? 0);
            }
        }
        if ($amount <= 0) {
            return 0;
        }

        $userId = (int)$order->getUserId();
        $balance = BonusService::getBalance($userId);
        $available = (int)($balance['available'] ?? 0);
        if ($available <= 0) {
            return 0;
        }

        $maxByOrder = (int)floor((float)$order->getPrice());
        return max(0, min($amount, $available, $maxByOrder));
    }

    private static function resolveSpendAmountFromOrderProperty(Order $order): int {
        $props = $order->getPropertyCollection();
        if (!$props) {
            return 0;
        }

        foreach ($props as $prop) {
            $fields = $prop->getFields()->getValues();
            if (($fields['CODE'] ?? '') !== 'LEGACY_LOYALTY_PAYMENT_BONUS') {
                continue;
            }
            return max(0, (int)($fields['VALUE'] ?? 0));
        }

        return 0;
    }

    private static function resolveSpendAmountFromRequest(): int {
        $request = Application::getInstance()->getContext()->getRequest();
        $orderPost = $request->getPost('order');
        if (!is_array($orderPost)) {
            $orderPost = [];
        }

        if (!empty($orderPost)) {
            $propAmount = self::resolveSpendAmountFromOrderPostProperty($orderPost);
            if ($propAmount > 0) {
                return $propAmount;
            }
        }

        $raw = $request->getPost('LEGACY_LOYALTY_SPEND_ACCEPTED');
        if (($raw === null || $raw === '') && isset($orderPost['LEGACY_LOYALTY_SPEND_ACCEPTED'])) {
            $raw = $orderPost['LEGACY_LOYALTY_SPEND_ACCEPTED'];
        }
        if ($raw === null || $raw === '') {
            $raw = $request->get('LEGACY_LOYALTY_SPEND_ACCEPTED');
        }
        if ($raw === null || $raw === '') {
            $raw = $request->getPost('LEGACY_LOYALTY_SPEND');
        }
        if (($raw === null || $raw === '') && isset($orderPost['LEGACY_LOYALTY_SPEND'])) {
            $raw = $orderPost['LEGACY_LOYALTY_SPEND'];
        }
        if ($raw === null || $raw === '') {
            $raw = $request->getPost('legacy_loyalty_spend');
        }
        if ($raw === null || $raw === '') {
            $raw = $request->get('LEGACY_LOYALTY_SPEND');
        }
        if ($raw === null || $raw === '') {
            $raw = $request->get('legacy_loyalty_spend');
        }

        return max(0, (int)$raw);
    }

    private static function resolveSpendAmountFromOrderPostProperty(array $orderPost): int {
        if (!Loader::includeModule('sale')) {
            return 0;
        }

        $personTypeId = (int)($orderPost['PERSON_TYPE'] ?? 0);
        if ($personTypeId <= 0) {
            return 0;
        }

        $props = \CSaleOrderProps::GetList(
            ['SORT' => 'ASC'],
            ['PERSON_TYPE_ID' => $personTypeId, 'CODE' => 'LEGACY_LOYALTY_PAYMENT_BONUS'],
            false,
            false,
            ['ID']
        );
        if ($prop = $props->Fetch()) {
            $field = 'ORDER_PROP_' . (int)$prop['ID'];
            if (isset($orderPost[$field])) {
                return max(0, (int)$orderPost[$field]);
            }
        }

        return 0;
    }

    private static function extractOrder($event): ?Order {
        if ($event instanceof Order) {
            return $event;
        }

        if ($event instanceof \Bitrix\Main\Event) {
            $entity = $event->getParameter('ENTITY');
            if ($entity instanceof Order) {
                return $entity;
            }

            $order = $event->getParameter('ORDER');
            if ($order instanceof Order) {
                return $order;
            }
        }

        if (is_array($event)) {
            foreach ($event as $item) {
                if ($item instanceof Order) {
                    return $item;
                }
            }
            if (isset($event[0]) && (int)$event[0] > 0 && Loader::includeModule('sale')) {
                return Order::load((int)$event[0]);
            }
        }

        return null;
    }

    private static function safeRun(callable $callback, string $context): void {
        try {
            $callback();
        } catch (\Throwable $e) {
            if (function_exists('AddMessage2Log')) {
                AddMessage2Log($context . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 'legacy.loyalty');
            }
        }
    }

    private static function buildSpendApplyKey(Order $order, int $spendAmount): string {
        $orderId = (int)$order->getId();
        if ($orderId > 0) {
            return 'order:' . $orderId . ':spend:' . $spendAmount;
        }

        return 'obj:' . spl_object_hash($order) . ':spend:' . $spendAmount;
    }
}
