<?php
namespace Legacy\Loyalty\Conditions;

use Bitrix\Main\Web\Json;

class User
{
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
                            'type' => 'calendar',
                            'id' => 'value',
                            'name' => 'value',
                            'show_value' => 'Y',
                            'defaultText' => 'Выберите дату',
                            'defaultValue' => '',
                        ],
                    ],
                ],
            ],
        ];

        return $mode === 'json' ? Json::encode($params) : $params;
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
        $out = ['0' => 'Без уровня [0]'];

        try {
            $connection = \Bitrix\Main\Application::getConnection();
            if (!$connection->isTableExists('b_legacy_loyalty_level_rule')) {
                return $out;
            }

            $res = $connection->query("
                SELECT ID, NAME
                FROM b_legacy_loyalty_level_rule
                WHERE ACTIVE = 'Y'
                ORDER BY SORT ASC, ID ASC
            ");

            while ($level = $res->fetch()) {
                $id = (string)(int)$level['ID'];
                $name = trim((string)($level['NAME'] ?? ''));
                $out[$id] = htmlspecialcharsbx($name !== '' ? $name : 'Уровень #' . $id) . ' [' . $id . ']';
            }
        } catch (\Throwable $exception) {
            return $out;
        }

        return $out;
    }
}