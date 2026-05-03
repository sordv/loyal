<?php

namespace Legacy\Loyalty\EventHandler;

use Legacy\Loyalty\Service\BonusService;
use Legacy\Loyalty\Service\LevelBulkSyncService;

class BonusHandler {
    public static function registerAgents(): void {
        self::unregisterAgents();

        $nextExec = LevelBulkSyncService::nextDailyAgentRunFull();

        \CAgent::AddAgent(
            BonusService::AGENT_CLEANUP_EXPIRED,
            'legacy.loyalty',
            'N',
            86400,
            '',
            'Y',
            $nextExec,
            30
        );
    }

    public static function unregisterAgents(): void {
        \CAgent::RemoveAgent(BonusService::AGENT_CLEANUP_EXPIRED, 'legacy.loyalty');
    }
}