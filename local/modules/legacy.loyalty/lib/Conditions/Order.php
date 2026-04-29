<?php
namespace Legacy\Loyalty\Conditions;

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;
use Legacy\Loyalty\Service\LevelService;

class Order {
    public static function mainParams(string $mode = ''): array|string {
        $params = [
            'parentContainer' => 'OrderConditions',
            'form' => '',
            'formName' => 'form_edit',
            'sepID' => '__',
            'prefix' => 'ruleOrderCond',
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
        $userGroups = self::getUserGroups();
        $levels = self::getUserLevels();
        $deliveries = self::getDeliveries();
        $personTypes = self::getPersonTypes();
        $paymentSystems = self::getPaymentSystems();
        $sites = self::getSites();

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
                // САЙТ
                [
                    'controlId' => 'site',
                    'group' => false,
                    'label' => 'Сайт',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Сайт'],
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
                            'values' => $sites,
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'first_option' => '...',
                            'defaultText' => '...',
                            'defaultValue' => '',
                        ],
                    ],
                ],
                // ГРУППА ПОЛЬЗОВАТЕЛЕЙ
                [
                    'controlId' => 'userGroups',
                    'group' => false,
                    'label' => 'Группа пользователей',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Группа пользователей'],
                        [
                            'id' => 'logic',
                            'name' => 'logic',
                            'type' => 'select',
                            'values' => [
                                'Equal' => 'в группе',
                                'Not' => 'не в группе',
                            ],
                            'defaultText' => 'в группе',
                            'defaultValue' => 'Equal',
                        ],
                        [
                            'type' => 'select',
                            'multiple' => 'Y',
                            'values' => $userGroups,
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'first_option' => '...',
                            'defaultText' => '...',
                            'defaultValue' => '',
                        ],
                    ],
                ],
                // СУММА ТОВАРОВ
                [
                    'controlId' => 'cartSum',
                    'group' => false,
                    'label' => 'Сумма товаров',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Сумма товаров'],
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
                // КОЛ-ВО ТОВАРОВ
                [
                    'controlId' => 'itemCount',
                    'group' => false,
                    'label' => 'Кол-во товаров',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Кол-во товаров'],
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
                            'defaultValue' => '1',
                        ],
                    ],
                ],
                // СУММА ЗАКАЗА
                [
                    'controlId' => 'orderSum',
                    'group' => false,
                    'label' => 'Сумма заказа',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Сумма заказа'],
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
                            'defaultText' => 'больше',
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
                // ДОСТАВКА
                [
                    'controlId' => 'delivery',
                    'group' => false,
                    'label' => 'Доставка',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Доставка'],
                        [
                            'id' => 'logic',
                            'name' => 'logic',
                            'type' => 'select',
                            'values' => [
                                'Equal' => 'равна',
                                'Not' => 'не равна',
                            ],
                            'defaultText' => 'равна',
                            'defaultValue' => 'Equal',
                        ],
                        [
                            'type' => 'select',
                            'multiple' => 'Y',
                            'values' => $deliveries,
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'first_option' => '...',
                            'defaultText' => '...',
                            'defaultValue' => '',
                        ],
                    ],
                ],
            ],
        ];

        // РАЗДЕЛ: ПРОГРАММА ЛОЯЛЬНОСТИ
        $params[] = [
            'controlgroup' => 'loyalty_program',
            'group' => true,
            'label' => 'Программа лояльности',
            'showIn' => ['CondGroup'],
            'children' => [
                // ОПЛАТА БОНУСАМИ
                [
                    'controlId' => 'bonusPayment',
                    'group' => false,
                    'label' => 'Оплата бонусами',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Оплата бонусами'],
                        [
                            'type' => 'select',
                            'values' => [
                                'Y' => 'Используется',
                                'N' => 'Не используется',
                            ],
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'defaultText' => 'Выберите...',
                            'defaultValue' => '',
                        ],
                    ],
                ],
                // УРОВЕНЬ ПОЛЬЗОВАТЕЛЯ
                [
                    'controlId' => 'userLevel',
                    'group' => false,
                    'label' => 'Уровень пользователя',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Уровень пользователя'],
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
                            'values' => $levels,
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'first_option' => '...',
                            'defaultText' => '...',
                            'defaultValue' => '',
                        ],
                    ],
                ],
            ]
        ];

        // РАЗДЕЛ: ДОПОЛНИТЕЛЬНЫЕ ПАРАМЕТРЫ
        $params[] = [
            'controlgroup' => 'additional_params',
            'group' => true,
            'label' => 'Дополнительные параметры',
            'showIn' => ['CondGroup'],
            'children' => [
                // КАЖДЫЙ N-Й ЗАКАЗ
                [
                    'controlId' => 'everyNthOrder',
                    'group' => false,
                    'label' => 'Каждый n-й заказ',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Каждый'],
                        [
                            'type' => 'input',
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'defaultValue' => '1',
                        ],
                        ['id' => 'suffix', 'type' => 'prefix', 'text' => '-й заказ'],
                    ],
                ],
                // ТОЛЬКО N-Й ЗАКАЗ
                [
                    'controlId' => 'onlyNthOrder',
                    'group' => false,
                    'label' => 'Только n-й заказ',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Только'],
                        [
                            'type' => 'input',
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'defaultValue' => '1',
                        ],
                        ['id' => 'suffix', 'type' => 'prefix', 'text' => '-й заказ'],
                    ],
                ],
                // ТИП ПЛАТЕЛЬЩИКА
                [
                    'controlId' => 'personTypes',
                    'group' => false,
                    'label' => 'Тип плательщика',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Тип плательщика'],
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
                            'values' => $personTypes,
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'first_option' => '...',
                            'defaultText' => '...',
                            'defaultValue' => '',
                        ],
                    ],
                ],
                // ПЛАТЕЖНАЯ СИСТЕМА
                [
                    'controlId' => 'paymentSystem',
                    'group' => false,
                    'label' => 'Платежная система',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Платежная система'],
                        [
                            'id' => 'logic',
                            'name' => 'logic',
                            'type' => 'select',
                            'values' => [
                                'Equal' => 'равна',
                                'Not' => 'не равна',
                            ],
                            'defaultText' => 'равна',
                            'defaultValue' => 'Equal',
                        ],
                        [
                            'type' => 'select',
                            'multiple' => 'Y',
                            'values' => $paymentSystems,
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'first_option' => '...',
                            'defaultText' => '...',
                            'defaultValue' => '',
                        ],
                    ],
                ],
            ]
        ];

        return $mode === 'json' ? Json::encode($params) : $params;
    }

    private static function getSites(): array {
        $out = [];
        $db = \CSite::GetList("sort", "asc", []);
        while ($site = $db->fetch()) {
            $out[$site['LID']] = htmlspecialcharsbx($site['NAME']) . '[' . $site['LID'] . ']';
        }
        return $out;
    }

    private static function getUserGroups(): array {
        $out = [];
        $db = \CGroup::GetList('c_sort', 'asc', ['ACTIVE' => 'Y']);
        while ($g = $db->Fetch()) {
            $out[(string)$g['ID']] = $g['NAME'];
        }
        return $out;
    }

    private static function getUserLevels(): array {
        $out = [];
        foreach (LevelService::getAllLevels() as $lvl) {
            $out[(string)$lvl['ID']] = $lvl['NAME'] !== '' ? $lvl['NAME'] : ('#'.$lvl['ID']);
        }
        return $out;
    }

    private static function getDeliveries(): array {
        $out = [];
        if (!Loader::includeModule('sale')) {
            return $out;
        }
        $res = \Bitrix\Sale\Delivery\Services\Table::getList([
            'filter' => ['=ACTIVE' => 'Y'],
            'select' => ['ID', 'NAME'],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);
        while ($row = $res->fetch()) {
            $out[(string)$row['ID']] = $row['NAME'];
        }
        return $out;
    }

    private static function getPersonTypes(): array {
        $out = [];
        if (!Loader::includeModule('sale')) {
            return $out;
        }
        $res = \Bitrix\Sale\Internals\PersonTypeTable::getList([
            'filter' => ['=ACTIVE' => 'Y'],
            'select' => ['ID', 'NAME'],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);
        while ($row = $res->fetch()) {
            $out[(string)$row['ID']] = $row['NAME'];
        }
        return $out;
    }

    private static function getPaymentSystems(): array {
        $out = [];
        if (!Loader::includeModule('sale')) {
            return $out;
        }
        $res = \Bitrix\Sale\Internals\PaySystemActionTable::getList([
            'filter' => ['=ACTIVE' => 'Y'],
            'select' => ['ID', 'NAME'],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);
        while ($row = $res->fetch()) {
            $out[(string)$row['ID']] = $row['NAME'];
        }
        return $out;
    }
}