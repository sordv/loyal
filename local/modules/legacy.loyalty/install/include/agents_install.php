<?php

// регистрация всех агентов

$ts = mktime(3, 0, 0, (int)date('n'), (int)date('j'), (int)date('Y'));
if ($ts <= time()) {
    $ts += 86400;
}
$nextExec = ConvertTimeStamp($ts, 'FULL');

// ежедневное обновление уровней
\CAgent::AddAgent(
    \Legacy\Loyalty\Service\LevelBulkSyncService::AGENT_DAILY,
    'legacy.loyalty',
    'N',
    86400,
    '',
    'Y',
    $nextExec,
    30
);

// ежедневная очистка бонусов с истекшим сроком действия
\CAgent::AddAgent(
    \Legacy\Loyalty\Service\BonusService::AGENT_CLEANUP_EXPIRED,
    'legacy.loyalty',
    'N',
    86400,
    '',
    'Y',
    $nextExec,
    30
);

// ежедневная рассылка предупреждений об истечении бонусов
\CAgent::AddAgent(
    \Legacy\Loyalty\Service\BonusExpireMailService::AGENT_RUN,
    'legacy.loyalty',
    'N',
    86400,
    '',
    'Y',
    $nextExec,
    35
);