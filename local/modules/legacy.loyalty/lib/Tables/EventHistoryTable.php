<?php
namespace Legacy\Loyalty\Tables;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\DateField;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\Entity\IntegerField;

class EventHistoryTable extends DataManager {
    public static function getTableName() {
        return 'b_legacy_loyalty_event_history';
    }

    public static function getMap() {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new IntegerField('USER_ID'),
            new IntegerField('RULE_ID'),
            new DateField('EVENT_DATE'),
            new IntegerField('BONUS_AMOUNT', ['default' => 0]),
            new DatetimeField('CREATED_AT', ['default_value' => function () { return new \Bitrix\Main\Type\DateTime(); }]),
        ];
    }
}

