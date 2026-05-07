<?php

$moduleId = isset($this) && is_object($this) && property_exists($this, 'MODULE_ID')
    ? (string)$this->MODULE_ID
    : 'legacy.loyalty';

RegisterModuleDependences(
    'main',
    'OnAfterUserRegister',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\UserLevelHandler',
    'onAfterUserRegister'
);
RegisterModuleDependences(
    'main',
    'OnAfterUserAdd',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\UserLevelHandler',
    'onAfterUserAdd'
);

RegisterModuleDependences(
    'sale',
    'OnSaleOrderBeforeSaved',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\OrderBonusHandler',
    'onSaleOrderBeforeSaved'
);
RegisterModuleDependences(
    'sale',
    'OnSaleOrderSaved',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\OrderBonusHandler',
    'onSaleOrderSaved'
);
RegisterModuleDependences(
    'sale',
    'OnSaleOrderPaid',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\OrderBonusHandler',
    'onSaleOrderPaid'
);
RegisterModuleDependences(
    'sale',
    'OnSaleStatusOrderChange',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\OrderBonusHandler',
    'onSaleStatusOrderChange'
);
RegisterModuleDependences(
    'sale',
    'OnSaleComponentOrderCreated',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\OrderBonusHandler',
    'onSaleComponentOrderCreated'
);
RegisterModuleDependences(
    'sale',
    'OnSaleComponentOrderResultPrepared',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\OrderBonusHandler',
    'onSaleComponentOrderResultPrepared'
);

RegisterModuleDependences(
    'sale',
    'OnSaleOrderBeforeSaved',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\LevelDiscountHandler',
    'onSaleOrderBeforeSaved'
);
RegisterModuleDependences(
    'sale',
    'OnSaleComponentOrderCreated',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\LevelDiscountHandler',
    'onSaleComponentOrderCreated'
);
RegisterModuleDependences(
    'sale',
    'OnSaleComponentOrderResultPrepared',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\LevelDiscountHandler',
    'onSaleComponentOrderResultPrepared'
);

