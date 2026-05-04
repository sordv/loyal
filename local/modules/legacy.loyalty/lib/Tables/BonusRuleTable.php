<?php
namespace Legacy\Loyalty\Tables;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\TextField;

class BonusRuleTable extends DataManager {
    public static function getTableName() {
        return 'b_legacy_loyalty_bonus_rule';
    }

    public static function getMap() {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new IntegerField('SORT', ['default' => 100]),
            new StringField('ACTIVE', ['default' => 'Y', 'values' => ['Y', 'N']]),
            new StringField('NAME', ['size' => 255]),
            new StringField('TYPE', ['values' => ['add', 'spend']]),
            new StringField('AMOUNT_TYPE', ['values' => ['percent', 'fixed']]),
            new IntegerField('AMOUNT', ['default' => 0]),
            new TextField('CONDITIONS_ORDER', ['serialized' => true]),
            new TextField('CONDITIONS_PRODUCT', ['serialized' => true]),
        ];
    }
}