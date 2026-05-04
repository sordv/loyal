<?php

namespace Legacy\Loyalty\Tables;

use Bitrix\Main\Entity;
use Bitrix\Main\Type;

class ProgramTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'b_legacy_loyalty_program';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new Entity\StringField('TYPE', [
                'required' => true,
            ]),
            new Entity\StringField('NAME', [
                'required' => true,
            ]),
            new Entity\BooleanField('ACTIVE', [
                'values' => ['N', 'Y'],
                'default_value' => 'Y'
            ]),
        ];
    }
}