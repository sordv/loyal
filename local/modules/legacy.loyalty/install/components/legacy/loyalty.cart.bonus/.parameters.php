<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'PARAMETERS' => [
        'SHOW_ITEM_BONUS' => [
            'PARENT' => 'BASE',
            'NAME' => 'Показывать бонусы по товарам',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'SHOW_ORDER_BONUS' => [
            'PARENT' => 'BASE',
            'NAME' => 'Показывать бонусы за заказ',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'SHOW_SPEND_BONUS' => [
            'PARENT' => 'BASE',
            'NAME' => 'Показывать списание бонусов',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'CACHE_TIME' => ['DEFAULT' => 0],
    ],
];
