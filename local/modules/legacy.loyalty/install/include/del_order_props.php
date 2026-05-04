<?php

if (!\CModule::IncludeModule('sale')) {
    return;
}

$personTypes = \CSalePersonType::GetList(['SORT' => 'ASC'], []);
while ($personType = $personTypes->Fetch()) {
    $personTypeId = (int)$personType['ID'];
    if ($personTypeId <= 0) {
        continue;
    }

    $props = \CSaleOrderProps::GetList(
        ['SORT' => 'ASC'],
        ['PERSON_TYPE_ID' => $personTypeId, 'CODE' => 'LEGACY_LOYALTY_PAYMENT_BONUS'],
        false,
        false,
        ['ID']
    );
    while ($prop = $props->Fetch()) {
        \CSaleOrderProps::Delete((int)$prop['ID']);
    }
}

