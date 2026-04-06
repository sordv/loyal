<?php

namespace Legacy\Loyalty\EventHandler;

use Bitrix\Main\Agent;
use Legacy\Loyalty\Service\BonusService;

class BonusHandler {
    private const CLEANUP_AGENT_NAME = 'legacy.loyalty:cleanupExpiredBonuses';

    public static function registerAgents() {
        self::unregisterAgents();

        Agent::addAgent(
            '\Legacy\Loyalty\Service\BonusService::cleanupExpiredBonuses();',
            'legacy.loyalty',
            'N',
            '86400',
            '',
            'Y',
            '00:00:00',
            0,
            'Europe/Moscow'
        );
    }

    public static function unregisterAgents() {
        Agent::removeAgent(
            '\Legacy\Loyalty\Service\BonusService::cleanupExpiredBonuses();',
            'legacy.loyalty'
        );
    }
}