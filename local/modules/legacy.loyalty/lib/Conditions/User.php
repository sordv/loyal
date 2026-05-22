<?php
namespace Legacy\Loyalty\Conditions;

use Bitrix\Main\Web\Json;

class User {
    public static function prepareConditionsForEditor($tree) {
        if (!is_array($tree)) {
            return $tree;
        }

        if (($tree['controlId'] ?? '') === 'registrationDate' && isset($tree['values']['value'])) {
            $v = $tree['values']['value'];
            if (is_string($v) && $v !== '') {
                $trimmed = trim($v);
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $trimmed)) {
                    $ts = strtotime($trimmed);
                    if ($ts !== false) {
                        $tree['values']['value'] = date('d.m.Y', $ts);
                    }
                }
            }
        }

        if (!empty($tree['children']) && is_array($tree['children'])) {
            foreach ($tree['children'] as $key => $child) {
                $tree['children'][$key] = self::prepareConditionsForEditor($child);
            }
        }

        return $tree;
    }

    public static function mainParams(string $mode = ''): array|string
    {
        $params = [
            'parentContainer' => 'UserConditions',
            'form' => '',
            'formName' => 'form_edit',
            'sepID' => '__',
            'prefix' => 'levelRuleCond',
            'messTree' => [
                'SELECT_CONTROL' => 'Выберите условие',
                'ADD_CONTROL' => 'Добавить условие',
                'DELETE_CONTROL' => 'Удалить',
            ],
        ];

        return $mode === 'json' ? Json::encode($params) : $params;
    }

    public static function baseConditions(string $mode = ''): array|string
    {
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

    public static function controls(string $mode = ''): array|string
    {
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
                        'True' => 'Выполняются',
                        'False' => 'Не выполняются',
                    ],
                    'defaultText' => 'Выполняются',
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
            'controlgroup' => '1',
            'group' => true,
            'label' => 'Основные параметры',
            'showIn' => ['CondGroup'],
            'children' => [
                // СУММА ЗАКАЗОВ
                [
                    'controlId' => 'ordersSum',
                    'group' => false,
                    'label' => 'Сумма заказов',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Сумма заказов'],
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
                // КОЛИЧЕСТВО ЗАКАЗОВ
                [
                    'controlId' => 'ordersCount',
                    'group' => false,
                    'label' => 'Количество заказов',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Количество заказов'],
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
                // СУММА ЗАКАЗОВ ЗА ПЕРИОД
                [
                    'controlId' => 'ordersSumPeriod',
                    'group' => false,
                    'label' => 'Сумма заказов за период',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Сумма заказов'],
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
                            'defaultText' => 'больше или равно',
                            'defaultValue' => 'GreaterEqual',
                        ],
                        [
                            'type' => 'input',
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'defaultValue' => '0',
                        ],
                        ['id' => 'period_prefix', 'type' => 'prefix', 'text' => 'за последние'],
                        [
                            'type' => 'input',
                            'id' => 'period',
                            'name' => 'period',
                            'show_value' => 'Y',
                            'defaultValue' => '30',
                        ],
                        ['id' => 'period_suffix', 'type' => 'prefix', 'text' => 'дней'],
                    ],
                ],
                // КОЛИЧЕСТВО ЗАКАЗОВ ЗА ПЕРИОД
                [
                    'controlId' => 'ordersCountPeriod',
                    'group' => false,
                    'label' => 'Количество заказов за период',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Количество заказов'],
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
                            'defaultText' => 'больше или равно',
                            'defaultValue' => 'GreaterEqual',
                        ],
                        [
                            'type' => 'input',
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'defaultValue' => '0',
                        ],
                        ['id' => 'period_prefix', 'type' => 'prefix', 'text' => 'за последние'],
                        [
                            'type' => 'input',
                            'id' => 'period',
                            'name' => 'period',
                            'show_value' => 'Y',
                            'defaultValue' => '30',
                        ],
                        ['id' => 'period_suffix', 'type' => 'prefix', 'text' => 'дней'],
                    ],
                ],
                // СУММА ЗАКАЗОВ ЗА ПРОШЛЫЙ МЕСЯЦ
                [
                    'controlId' => 'ordersSumPrevMonth',
                    'group' => false,
                    'label' => 'Сумма заказов за прошлый месяц',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Сумма заказов за прошлый месяц'],
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
                // КОЛИЧЕСТВО ЗАКАЗОВ ЗА ПРОШЛЫЙ МЕСЯЦ
                [
                    'controlId' => 'ordersCountPrevMonth',
                    'group' => false,
                    'label' => 'Количество заказов за прошлый месяц',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Количество заказов за прошлый месяц'],
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
                // ВОЗРАСТ АККАУНТА
                [
                    'controlId' => 'registrationAge',
                    'group' => false,
                    'label' => 'Возраст аккаунта',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Возраст аккаунта'],
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
                        ['id' => 'suffix', 'type' => 'prefix', 'text' => 'дней'],
                    ],
                ],
                // ДАТА РЕГИСТРАЦИИ
                [
                    'controlId' => 'registrationDate',
                    'group' => false,
                    'label' => 'Дата регистрации',
                    'showIn' => ['CondGroup'],
                    'control' => [
                        ['id' => 'prefix', 'type' => 'prefix', 'text' => 'Дата регистрации'],
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
                            'defaultText' => 'ДД.ММ.ГГГГ (например 31.12.2025)',
                            'placeholder' => 'ДД.ММ.ГГГГ (например 31.12.2025)',
                            'defaultValue' => '',
                        ],
                    ],
                ],
            ],
        ];

        return $mode === 'json' ? Json::encode($params) : $params;
    }
}