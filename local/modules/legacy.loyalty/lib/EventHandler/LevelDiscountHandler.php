<?php

namespace Legacy\Loyalty\EventHandler;

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Legacy\Loyalty\Service\LevelService;
use Legacy\Loyalty\Service\ProgramService;

class LevelDiscountHandler {
    public static function onSaleOrderBeforeSaved($event): void {
        $order = $event->getParameter('ENTITY');
        if ($order instanceof Order) {
            self::applyLevelDiscounts($order);
        }
    }

    public static function onSaleComponentOrderCreated(Order $order, &$arUserResult, $request, &$arParams, &$arResult): void {
        self::applyLevelDiscounts($order);
    }

    public static function onSaleComponentOrderResultPrepared(Order $order, &$arUserResult, $request, &$arParams, &$arResult): void {
        self::applyLevelDiscounts($order);
    }

    public static function applyLevelDiscounts(Order $order): void {
        if (!Loader::includeModule('sale') || !ProgramService::isLevelEnabled()) {
            return;
        }

        $level = LevelService::getLevel((int)$order->getUserId());
        if (!is_array($level)) {
            return;
        }

        $privileges = LevelService::normalizePrivileges($level['PRIVILEGES'] ?? []);
        $cartDiscount = (float)$privileges['cartDiscountPercent'];
        $deliveryDiscount = (float)$privileges['deliveryDiscountPercent'];

        if ($cartDiscount <= 0 && $deliveryDiscount <= 0) {
            return;
        }

        if ($cartDiscount > 0) {
            self::applyBasketDiscount($order, $cartDiscount);
        }

        if ($deliveryDiscount > 0) {
            self::applyDeliveryDiscount($order, $deliveryDiscount);
        }
    }

    private static function applyBasketDiscount(Order $order, float $percent): void {
        $basket = $order->getBasket();
        if (!$basket) {
            return;
        }

        $ratio = max(0, min(100, $percent)) / 100;

        foreach ($basket as $basketItem) {
            if (method_exists($basketItem, 'canBuy') && !$basketItem->canBuy()) {
                continue;
            }

            $basePrice = (float)$basketItem->getField('BASE_PRICE');
            if ($basePrice <= 0) {
                $basePrice = (float)$basketItem->getPrice();
            }

            if ($basePrice <= 0) {
                continue;
            }

            $newPrice = max(0, round($basePrice * (1 - $ratio), 2));
            $discount = max(0, $basePrice - $newPrice);

            if (method_exists($basketItem, 'markFieldCustom')) {
                $basketItem->markFieldCustom('PRICE');
            } else {
                $basketItem->setField('CUSTOM_PRICE', 'Y');
            }

            $basketItem->setField('PRICE', $newPrice);
            $basketItem->setField('BASE_PRICE', $basePrice);
            $basketItem->setField('DISCOUNT_PRICE', $discount);
        }
    }

    private static function applyDeliveryDiscount(Order $order, float $percent): void {
        $ratio = max(0, min(100, $percent)) / 100;
        $shipmentCollection = $order->getShipmentCollection();
        if (!$shipmentCollection) {
            return;
        }

        foreach ($shipmentCollection as $shipment) {
            if ($shipment->isSystem()) {
                continue;
            }

            $basePrice = (float)$shipment->getField('BASE_PRICE_DELIVERY');
            if ($basePrice <= 0) {
                $basePrice = (float)$shipment->getField('PRICE_DELIVERY');
            }

            if ($basePrice <= 0) {
                continue;
            }

            $newPrice = max(0, round($basePrice * (1 - $ratio), 2));
            $shipment->setFields([
                'PRICE_DELIVERY' => $newPrice,
                'BASE_PRICE_DELIVERY' => $basePrice,
                'DISCOUNT_PRICE' => max(0, $basePrice - $newPrice),
                'CUSTOM_PRICE_DELIVERY' => 'Y',
            ]);
        }
    }
}