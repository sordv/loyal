<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if (!\CModule::IncludeModule('sale')) {
    return;
}

$groupName = Loc::getMessage('LEGACY_LOYALTY_ORDER_GROUP_NAME') ?: 'Loyalty';

$personTypes = \CSalePersonType::GetList(['SORT' => 'ASC'], []);
while ($personType = $personTypes->Fetch()) {
    $personTypeId = (int)$personType['ID'];
    if ($personTypeId <= 0) {
        continue;
    }

    $groupId = 0;
    $groups = \CSaleOrderPropsGroup::GetList(['SORT' => 'ASC'], ['PERSON_TYPE_ID' => $personTypeId], false, false, []);
    while ($group = $groups->Fetch()) {
        if ((string)$group['NAME'] === (string)$groupName) {
            $groupId = (int)$group['ID'];
            break;
        }
    }

    if ($groupId <= 0) {
        $groupId = (int)\CSaleOrderPropsGroup::Add([
            'PERSON_TYPE_ID' => $personTypeId,
            'NAME' => $groupName,
            'SORT' => 500,
        ]);
    }

    $exists = false;
    $props = \CSaleOrderProps::GetList(
        ['SORT' => 'ASC'],
        ['PERSON_TYPE_ID' => $personTypeId, 'CODE' => 'LEGACY_LOYALTY_PAYMENT_BONUS'],
        false,
        false,
        []
    );
    if ($props->Fetch()) {
        $exists = true;
    }

    if (!$exists) {
        $fields = [
            'PERSON_TYPE_ID' => $personTypeId,
            'NAME' => Loc::getMessage('LEGACY_LOYALTY_ORDER_PROP_PAYMENT_BONUS_NAME') ?: 'Списание бонусов',
            'TYPE' => 'TEXT',
            'REQUIED' => 'N',
            'DEFAULT_VALUE' => '0',
            'SORT' => 500,
            'CODE' => 'LEGACY_LOYALTY_PAYMENT_BONUS',
            'USER_PROPS' => 'N',
            'IS_LOCATION' => 'N',
            'IS_LOCATION4TAX' => 'N',
            'PROPS_GROUP_ID' => $groupId,
            'SIZE1' => 10,
            'SIZE2' => 0,
            'DESCRIPTION' => '',
            'IS_EMAIL' => 'N',
            'IS_PROFILE_NAME' => 'N',
            'IS_PAYER' => 'N',
            'UTIL' => 'Y',
        ];
        \CSaleOrderProps::Add($fields);
    }
}

