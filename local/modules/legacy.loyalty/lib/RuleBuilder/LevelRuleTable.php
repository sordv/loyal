<?php
namespace Legacy\Loyalty\RuleBuilder;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\TextField;

class LevelRuleTable extends DataManager {

    public static function getTableName() {
        return 'b_legacy_loyalty_level_rule';
    }

    public static function getMap() {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new IntegerField('SORT', ['default' => 100]),
            new StringField('ACTIVE', ['default' => 'Y', 'values' => ['Y', 'N']]),
            new StringField('NAME', ['size' => 255]),
            new IntegerField('PERIOD', ['default' => null]),
            new TextField('CONDITIONS', ['serialized' => true]),
            new TextField('PRIVILEGES', ['serialized' => true]),
        ];
    }
}