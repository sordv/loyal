<?php

namespace Legacy\Loyalty\EventHandler;

use Legacy\Loyalty\Service\BonusService;

class BonusHandler {
    public static function registerAgents(): void {
        self::unregisterAgents();

        $nextMidnight = mktime(0, 0, 0, (int)date('n'), (int)date('j'), (int)date('Y'));
        if ($nextMidnight <= time()) {
            $nextMidnight += 86400;
        }
        $nextExec = ConvertTimeStamp($nextMidnight, 'FULL');

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