<?php

$moduleId = isset($this) && is_object($this) && property_exists($this, 'MODULE_ID')
    ? (string)$this->MODULE_ID
    : 'legacy.loyalty';

UnRegisterModuleDependences(
    'main',
    'OnAfterUserRegister',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\UserLevelHandler',
    'onAfterUserRegister'
);
UnRegisterModuleDependences(
    'main',
    'OnAfterUserAdd',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\UserLevelHandler',
    'onAfterUserAdd'
);

UnRegisterModuleDependences(
    'sale',
    'OnSaleOrderBeforeSaved',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\OrderBonusHandler',
    'onSaleOrderBeforeSaved'
);
UnRegisterModuleDependences(
    'sale',
    'OnSaleOrderSaved',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\OrderBonusHandler',
    'onSaleOrderSaved'
);
UnRegisterModuleDependences(
    'sale',
    'OnSaleOrderPaid',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\OrderBonusHandler',
    'onSaleOrderPaid'
);
UnRegisterModuleDependences(
    'sale',
    'OnSaleStatusOrderChange',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\OrderBonusHandler',
    'onSaleStatusOrderChange'
);
UnRegisterModuleDependences(
    'sale',
    'OnSaleComponentOrderCreated',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\OrderBonusHandler',
    'onSaleComponentOrderCreated'
);
UnRegisterModuleDependences(
    'sale',
    'OnSaleComponentOrderResultPrepared',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\OrderBonusHandler',
    'onSaleComponentOrderResultPrepared'
);

UnRegisterModuleDependences(
    'sale',
    'OnSaleOrderBeforeSaved',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\LevelDiscountHandler',
    'onSaleOrderBeforeSaved'
);
UnRegisterModuleDependences(
    'sale',
    'OnSaleComponentOrderCreated',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\LevelDiscountHandler',
    'onSaleComponentOrderCreated'
);
UnRegisterModuleDependences(
    'sale',
    'OnSaleComponentOrderResultPrepared',
    $moduleId,
    '\Legacy\Loyalty\EventHandler\LevelDiscountHandler',
    'onSaleComponentOrderResultPrepared'
);

