<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Legacy\Loyalty\Tables\EventRuleTable;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
Loc::loadMessages($_SERVER["DOCUMENT_ROOT"] . "/local/modules/legacy.loyalty/admin/program_event.php");

$APPLICATION->SetTitle(Loc::getMessage("LEGACY_LOYALTY_TYPE_EVENT"));

if (!Loader::includeModule('legacy.loyalty')) {
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
    echo Loc::getMessage("LEGACY_LOYALTY_MODULE_NOT_INSTALLED");
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    return;
}

$request = Application::getInstance()->getContext()->getRequest();
$message = null;

if ($request->get('action') === 'delete' && check_bitrix_sessid()) {
    $ruleId = (int)$request->get('rule_id');
    if ($ruleId > 0) {
        $rule = EventRuleTable::getById($ruleId)->fetch();
        if ($rule) {
            EventRuleTable::delete($ruleId);
            LocalRedirect($APPLICATION->GetCurPageParam() . '&deleted=Y');
        }
    }
}

if ($request->get('deleted') === 'Y') {
    $message = ["TYPE" => "OK", "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_DELETED")];
}

if ($request->isPost() && check_bitrix_sessid() && $request->getPost('save_settings')) {
    LocalRedirect('menu_program.php?lang=' . LANG);
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$eventRules = [];
try {
    $eventRules = EventRuleTable::getList([
        'order' => ['SORT' => 'ASC', 'ID' => 'DESC'],
    ])->fetchAll();
} catch (\Throwable $e) {
    $message = ["TYPE" => "ERROR", "MESSAGE" => (Loc::getMessage("LEGACY_LOYALTY_RULES_LOAD_ERROR")) . $e->getMessage()];
}

if ($message) {
    CAdminMessage::ShowMessage($message);
}

$aTabs = [
    ["DIV" => "rules", "TAB" => Loc::getMessage("LEGACY_LOYALTY_TAB_RULE"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_TAB_RULE")],
    ["DIV" => "settings", "TAB" => Loc::getMessage("LEGACY_LOYALTY_TAB_SETTINGS"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_TAB_SETTINGS")],
];

$tabControl = new CAdminTabControl("tabControl", $aTabs);
?>

<form method="post">
    <?= bitrix_sessid_post() ?>
    <?php
    $tabControl->Begin();
    $tabControl->BeginNextTab();
    ?>

    <div class="leglol-margin20">
        <a href="event_rule_edit.php?lang=<?=LANG?>" class="adm-btn adm-btn-green">
            <?=Loc::getMessage("LEGACY_LOYALTY_ADD_NEW_RULE")?>
        </a>
    </div>

    <?php if (empty($eventRules)): ?>
        <div class="leglol-rules"><?=Loc::getMessage("LEGACY_LOYALTY_NO_RULES")?></div>
    <?php else: ?>
        <?php foreach ($eventRules as $rule): ?>
            <?php
            $isActive = ($rule['ACTIVE'] ?? '') === 'Y';
            $statusEmoji = $isActive ? '✅' : '❌';
            $bonus = 0;
            $priv = $rule['PRIVILEGES'] ?? [];
            if (is_string($priv) && $priv !== '' && CheckSerializedData($priv)) {
                $priv = unserialize($priv, ['allowed_classes' => false]);
            }
            if (is_array($priv)) {
                $bonus = (int)($priv['bonus'] ?? 0);
            }

            $fireMode = (string)($rule['FIRE_MODE'] ?? 'Once');
            if ($fireMode !== 'Days' && $fireMode !== 'Every') {
                $fireMode = 'Once';
            }
            $fireDays = (int)($rule['FIRE_DAYS'] ?? 0);
            $fireText = '';
            if ($fireMode === 'Once') {
                $fireText = 'только один раз';
            } elseif ($fireMode === 'Days') {
                $fireDays = max(1, $fireDays);
                $fireText = 'один раз в ' . $fireDays . ' дней';
            } else {
                $fireText = 'каждый раз';
            }
            $summary = (int)$bonus . ' ' . (Loc::getMessage("LEGACY_LOYALTY_EVENT_REWARD_BONUS") ?: 'бонусов') . ', ' . $fireText;
            $deleteUrl = $APPLICATION->GetCurPageParam();
            $deleteUrl .= (strpos($deleteUrl, '?') !== false ? '&' : '?');
            $deleteUrl .= 'action=delete&rule_id=' . (int)$rule['ID'] . '&' . bitrix_sessid_get();
            ?>
            <div class="leglol-rule-card">
                <div class="leglol-rule-header">
                    <div>
                        <span title="<?= $isActive ? 'ACTIVE' : 'INACTIVE' ?>"><?= $statusEmoji ?></span>
                        <span class="leglol-rule-left">
                            <span class="leglol-meta">
                                <?= Loc::getMessage("LEGACY_LOYALTY_VIEW_PRIORITY") ?: 'Приоритет' ?>
                                <b><?= (int)($rule['SORT'] ?? 100) ?></b>
                                <span class="leglol-sep">|</span>
                                <?= htmlspecialcharsbx($summary) ?>
                            </span>
                        </span>
                    </div>
                    <div class="leglol-rule-actions">
                        <a href="event_rule_edit.php?ID=<?= (int)$rule['ID'] ?>&lang=<?= LANG ?>" class="adm-btn adm-btn-save">
                            <?= Loc::getMessage("LEGACY_LOYALTY_EDIT_RULE_BTN") ?>
                        </a>
                        <a href="<?= htmlspecialcharsbx($deleteUrl) ?>" onclick="return confirm('<?= Loc::getMessage("LEGACY_LOYALTY_CONFIRM_DELETE_RULE") ?>')" class="adm-btn adm-btn-danger">
                            <?= Loc::getMessage("LEGACY_LOYALTY_DELETE_RULE_BTN") ?>
                        </a>
                    </div>
                </div>
                <div class="leglol-amount-block">
                    <strong class="leglol-14px"><?= htmlspecialcharsbx((string)($rule['NAME'] ?? '')) ?> [<?= (int)$rule['ID'] ?>]</strong>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    $tabControl->EndTab();
    $tabControl->BeginNextTab();
    ?>

    <div class="leglol-settings-actions">
        <p>Тут будут базовые настройки</p>
        <input type="hidden" name="save_settings" value="Y">
        <input type="submit" name="save" value="<?= htmlspecialcharsbx(Loc::getMessage('LEGACY_LOYALTY_BTN_SAVE_SETTINGS')) ?>" class="adm-btn-save">
    </div>

    <?php
    $tabControl->EndTab();
    $tabControl->End();
    ?>
    <div class="leglol-program-footer-nav">
        <a class="adm-btn" href="menu_program.php?lang=<?= LANG ?>"><?= htmlspecialcharsbx(Loc::getMessage('LEGACY_LOYALTY_BTN_BACK')) ?></a>
    </div>
</form>

<style>
    .leglol-margin20 { margin-bottom: 20px; }
    .leglol-rules {
        padding: 20px;
        background: #f9f9f9;
        border: 1px dashed #ccc;
        border-radius: 4px;
        color: #666;
    }

    .leglol-settings-actions {
        padding-top: 16px;
    }

    .leglol-program-footer-nav {
        margin-top: 16px;
        padding-top: 12px;
        border-top: 1px solid #e0e0e0;
    }

    .leglol-rule-card {
        margin-bottom: 12px;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 12px;
    }

    .leglol-rule-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 12px;
        color: #666;
    }

    .leglol-rule-left { margin-left: 8px; display:inline-flex; flex-direction:column; gap:2px; }

    .leglol-rule-actions { display: flex; gap: 8px; }

    .leglol-14px { font-size: 14px; }

    .leglol-summary { color:#444; font-size:12px; }
    .leglol-meta { color:#444; font-size:12px; }
    .leglol-sep { color:#aaa; margin:0 6px; }

</style>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");