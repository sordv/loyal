<?php
namespace Legacy\Loyalty;

use Bitrix\Main\Loader;

class Conditions {
    /**
     * Convert flat POST array (from core_condtree) into nested conditions tree.
     * Compatible with the structure Bitrix condition tree widgets expect.
     */
    public static function saveConditions(array $requestConditions): array
    {
        // core_condtree POST array keys look like: 0, 0__0, 0__1, 0__0__0, ...
        // If PHP receives them unordered, we may accidentally pick a non-root node as level 0.
        // Force stable "tree order" parsing.
        if (!empty($requestConditions)) {
            ksort($requestConditions, SORT_NATURAL);
        }

        $iblockIds = [];
        foreach ($requestConditions as $cond) {
            if (!empty($cond['controlId']) && strpos((string)$cond['controlId'], 'CondIBProp') !== false) {
                $parts = explode(':', (string)$cond['controlId']);
                if (!empty($parts[1])) {
                    $iblockIds[(int)$parts[1]] = (int)$parts[1];
                }
            }
        }

        $ibProps = [];
        if (!empty($iblockIds) && Loader::includeModule('iblock')) {
            foreach ($iblockIds as $iblockId) {
                $dbProps = \CIBlock::GetProperties($iblockId);
                while ($p = $dbProps->Fetch()) {
                    $ibProps[(int)$p['ID']] = $p;
                }
            }
        }

        $conditions = [];
        $levels = [0 => 0, 1 => 0, 2 => 0];
        $lastLevel = 0;
        $skipEmptyControls = ['registrationDate', 'bonusPayment', 'hasDiscount'];

        foreach ($requestConditions as $key => $cond) {
            $keyParts = explode('__', (string)$key);
            $level = count($keyParts) - 1;

            if ($level < $lastLevel) {
                foreach ($levels as $k => $v) {
                    if ($k > $level) {
                        $levels[$k] = 0;
                    }
                }
            }

            $id = $levels[$level];
            $block = [
                'id' => $id,
                'controlId' => (string)($cond['controlId'] ?? ''),
                'values' => [],
                'children' => [],
            ];

            foreach ($cond as $k => $v) {
                if ($k === 'controlId') {
                    continue;
                }

                if (is_array($v)) {
                    $v = array_values(array_unique(array_filter($v, static fn($x) => $x !== '' && $x !== null)));
                }

                if ($k === 'value') {
                    if (strpos($block['controlId'], 'CondIBProp') !== false) {
                        $parts = explode(':', $block['controlId']);
                        $propId = isset($parts[2]) ? (int)$parts[2] : 0;
                        if ($propId > 0 && isset($ibProps[$propId]) && ($ibProps[$propId]['PROPERTY_TYPE'] ?? null) === 'N') {
                            $v = (string)(float)str_replace(',', '.', (string)$v);
                        }
                    }

                    if (in_array($block['controlId'], ['orderSum', 'cartSum', 'productPrice', 'ordersSum'], true)) {
                        $v = (string)(float)str_replace(',', '.', (string)$v);
                    }

                    if (in_array($block['controlId'], ['registrationAge', 'ordersCount', 'everyNthOrder', 'onlyNthOrder', 'itemCount'], true)) {
                        $v = (string)(int)$v;
                    }

                    if ($block['controlId'] === 'registrationDate' && !empty($v) && is_string($v)) {
                        $timestamp = MakeTimeStamp($v, 'DD.MM.YYYY');
                        if ($timestamp !== false) {
                            $v = date('Y-m-d H:i:s', $timestamp);
                        }
                    }
                }

                $block['values'][$k] = $v;
            }

            if (isset($block['values']['value']) && is_array($block['values']['value']) && empty($block['values']['value']) && $level > 0) {
                if (!in_array($block['controlId'], $skipEmptyControls, true)) {
                    $lastLevel = $level;
                    $levels[$level] = $levels[$level] + 1;
                    continue;
                }
            }

            if ($level === 0) {
                // Root must be a group, otherwise UI "All/Any" and "True/False" controls disappear after reload
                // and values won't be stored. Enforce CondGroup as root.
                $conditions = [
                    'id' => $id,
                    'controlId' => 'CondGroup',
                    'values' => $block['values'],
                    'children' => [],
                ];
            } elseif ($level === 1) {
                $conditions['children'][$id] = $block;
            } elseif ($level === 2) {
                $parentIdx = $levels[$level - 1] - 1;
                if ($parentIdx >= 0 && isset($conditions['children'][$parentIdx])) {
                    $conditions['children'][$parentIdx]['children'][$id] = $block;
                }
            }

            $lastLevel = $level;
            $levels[$level] = $levels[$level] + 1;
        }

        if (empty($conditions)) {
            return [
                'id' => 0,
                'controlId' => 'CondGroup',
                'values' => ['All' => 'AND', 'True' => 'True'],
                'children' => [],
            ];
        }

        // Backward safety: ensure root has expected fields
        if (($conditions['controlId'] ?? null) !== 'CondGroup') {
            $conditions['controlId'] = 'CondGroup';
        }
        if (!isset($conditions['values']) || !is_array($conditions['values'])) {
            $conditions['values'] = ['All' => 'AND', 'True' => 'True'];
        } else {
            $conditions['values']['All'] = $conditions['values']['All'] ?? 'AND';
            $conditions['values']['True'] = $conditions['values']['True'] ?? 'True';
        }

        return $conditions;
    }
}