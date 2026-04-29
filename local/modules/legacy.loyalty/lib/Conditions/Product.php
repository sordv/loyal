<?php
namespace Legacy\Loyalty\Conditions;

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;

class Product {
    public static function mainParams(string $mode = ''): array|string {
        $params = [
            'parentContainer' => 'ProductConditions',
            'form' => '',
            'formName' => 'form_edit',
            'sepID' => '__',
            'prefix' => 'ruleProductCond',
            'messTree' => [
                'SELECT_CONTROL' => 'Выберите условие',
                'ADD_CONTROL' => 'Добавить условие',
                'DELETE_CONTROL' => 'Удалить',
            ],
        ];

        return $mode === 'json' ? Json::encode($params) : $params;
    }

    public static function baseConditions(string $mode = ''): array|string {
        $params = [
            'id' => '0',
            'controlId' => 'CondGroup',
            'values' => [
                'All' => 'AND',
                'True' => 'True',
            ],
            'children' => [],
        ];

        return $mode === 'json' ? Json::encode($params) : $params;
    }

    public static function controls(string $mode = ''): array|string {
        $iblocks = self::getIblocks();
        $sections = self::getCatalogSections();

        $params = [];

        $params[] = [
            'controlId' => 'CondGroup',
            'group' => true,
            'label' => '',
            'defaultText' => '',
            'showIn' => [],
            'visual' => [
                'controls' => ['All', 'True'],
                'values' => [
                    ['All' => 'AND', 'True' => 'True'],
                    ['All' => 'OR', 'True' => 'True'],
                    ['All' => 'AND', 'True' => 'False'],
                    ['All' => 'OR', 'True' => 'False'],
                ],
                'logic' => [
                    ['style' => 'condition-logic-and', 'message' => 'Все условия'],
                    ['style' => 'condition-logic-or', 'message' => 'Любое из условий'],
                ],
            ],
            'control' => [
                [
                    'id' => 'All',
                    'name' => 'All',
                    'type' => 'select',
                    'values' => [
                        'AND' => 'Все условия',
                        'OR' => 'Любое из условий',
                    ],
                    'defaultText' => 'Все условия',
                    'defaultValue' => 'AND',
                ],
                [
                    'id' => 'True',
                    'name' => 'True',
                    'type' => 'select',
                    'values' => [
                        'True' => 'Выполняется',
                        'False' => 'Не выполняется',
                    ],
                    'defaultText' => 'Выполняется',
                    'defaultValue' => 'True',
                ],
            ],
            'mess' => [
                'ADD_CONTROL' => 'Добавить условие',
                'SELECT_CONTROL' => 'Выберите условие',
            ],
        ];

        // РАЗДЕЛ: ОСНОВНЫЕ ПАРАМЕТРЫ
        $params[] = [
            'controlgroup' => 'main_params',
            'group' => true,
            'label' => 'Основные параметры',
            'showIn' => ['CondGroup'],
            'children' => [
                // ИНФОБЛОК
                [
                    'controlId' => 'iblock',
                    'group' => false,
                    'label' => 'Инфоблок',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Инфоблок'],
                        [
                            'id' => 'logic',
                            'name' => 'logic',
                            'type' => 'select',
                            'values' => [
                                'Equal' => 'равен',
                                'Not' => 'не равен',
                            ],
                            'defaultText' => 'равен',
                            'defaultValue' => 'Equal',
                        ],
                        [
                            'type' => 'select',
                            'multiple' => 'Y',
                            'values' => $iblocks,
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'first_option' => '...',
                            'defaultText' => '...',
                            'defaultValue' => '',
                        ],
                    ],
                ],
                // РАЗДЕЛ
                [
                    'controlId' => 'section',
                    'group' => false,
                    'label' => 'Раздел',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Раздел'],
                        [
                            'id' => 'logic',
                            'name' => 'logic',
                            'type' => 'select',
                            'values' => [
                                'Equal' => 'равен',
                                'Not' => 'не равен',
                            ],
                            'defaultText' => 'равен',
                            'defaultValue' => 'Equal',
                        ],
                        [
                            'type' => 'popupWindow',
                            'popup_url' => '/bitrix/admin/cat_section_search_dialog.php',
                            'popup_params' => ['lang' => LANGUAGE_ID],
                            'param_id' => 'n',
                            'show_value' => 'Y',
                            'id' => 'value',
                            'name' => 'value',
                        ],
                    ],
                ],
                // ТОВАР
                [
                    'controlId' => 'product',
                    'group' => false,
                    'label' => 'Товар',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Товар'],
                        [
                            'id' => 'logic',
                            'name' => 'logic',
                            'type' => 'select',
                            'values' => [
                                'Equal' => 'равен',
                                'Not' => 'не равен',
                            ],
                            'defaultText' => 'равен',
                            'defaultValue' => 'Equal',
                        ],
                        [
                            'type' => 'multiDialog',
                            'popup_url' => 'cat_product_search_dialog.php',
                            'popup_params' => ['lang' => LANGUAGE_ID, 'caller' => 'discount_rules', 'allow_select_parent' => 'Y'],
                            'param_id' => 'n',
                            'show_value' => 'Y',
                            'id' => 'value',
                            'name' => 'value',
                        ],
                    ],
                ],
                // ЦЕНА ТОВАРА
                [
                    'controlId' => 'productPrice',
                    'group' => false,
                    'label' => 'Цена товара',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Цена товара'],
                        [
                            'id' => 'logic',
                            'name' => 'logic',
                            'type' => 'select',
                            'values' => [
                                'Equal' => 'равно',
                                'Not' => 'не равно',
                                'Greater' => 'больше',
                                'Less' => 'меньше',
                                'GreaterEqual' => 'больше или равно',
                                'LessEqual' => 'меньше или равно',
                            ],
                            'defaultText' => 'равно',
                            'defaultValue' => 'Equal',
                        ],
                        [
                            'type' => 'input',
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'defaultValue' => '0',
                        ],
                    ],
                ],
                // НАЛИЧИЕ СКИДКИ
                [
                    'controlId' => 'hasDiscount',
                    'group' => false,
                    'label' => 'Наличие скидки',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Наличие скидки'],
                        [
                            'type' => 'select',
                            'values' => ['Y' => 'есть', 'N' => 'нет'],
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'defaultValue' => '',
                        ],
                    ],
                ],
            ]
        ];

        if (Loader::includeModule('catalog') && class_exists(\CCatalogCondCtrlIBlockProps::class)) {
            $propControls = \CCatalogCondCtrlIBlockProps::GetControlShow(['SHOW_IN_GROUPS' => ['CondGroup']]);
            if (is_array($propControls) && !empty($propControls)) {
                foreach ($propControls as $pc) {
                    $params[] = $pc;
                }
            }
        }

        return $mode === 'json' ? Json::encode($params) : $params;
    }

    private static function getIblocks(): array {
        $out = [];
        if (!Loader::includeModule('iblock')) {
            return $out;
        }
        $res = \CIBlock::GetList(['SORT' => 'ASC', 'ID' => 'ASC'], ['ACTIVE' => 'Y'], false);
        while ($ib = $res->Fetch()) {
            $out[(string)$ib['ID']] = '['.$ib['ID'].'] '.$ib['NAME'];
        }
        return $out;
    }

    private static function getCatalogSections(): array {
        $out = [];
        if (!Loader::includeModule('iblock')) {
            return $out;
        }
        $res = \CIBlockSection::GetList(
            ['SORT' => 'ASC'],
            ['ACTIVE' => 'Y', 'DEPTH_LEVEL' => '1'],
            false,
            ['ID', 'NAME', 'IBLOCK_ID']
        );
        while ($sec = $res->Fetch()) {
            $out[(string)$sec['ID']] = '[' . $sec['IBLOCK_ID'] . '] ' . $sec['NAME'];
        }
        return $out;
    }
}