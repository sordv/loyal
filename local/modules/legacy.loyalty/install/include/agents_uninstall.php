<?php

// удаление всех агентов
/*
\CAgent::RemoveAgent(\Legacy\Loyalty\Service\LevelBulkSyncService::AGENT_DAILY, 'legacy.loyalty');
\CAgent::RemoveAgent(\Legacy\Loyalty\Service\BonusService::AGENT_CLEANUP_EXPIRED, 'legacy.loyalty');
\CAgent::RemoveAgent(\Legacy\Loyalty\Service\BonusExpireMailService::AGENT_RUN, 'legacy.loyalty');
*/

\CAgent::RemoveAgent('\Legacy\Loyalty\Service\LevelBulkSyncService::runDailyAgent();', 'legacy.loyalty');
\CAgent::RemoveAgent('\Legacy\Loyalty\Service\BonusService::cleanupExpiredBonuses();', 'legacy.loyalty');
\CAgent::RemoveAgent('\Legacy\Loyalty\Service\BonusExpireMailService::runDailyAgent();', 'legacy.loyalty');
\CAgent::RemoveAgent('\Legacy\Loyalty\Service\EventRewardService::runDailyAgent();', 'legacy.loyalty');