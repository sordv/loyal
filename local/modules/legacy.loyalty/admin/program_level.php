<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Legacy\Loyalty\RuleBuilder\LevelRuleTable;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
Loc::loadMessages($_SERVER["DOCUMENT_ROOT"]."/local/modules/legacy.loyalty/admin/program_level.php");

$APPLICATION->SetTitle(Loc::getMessage("LEGACY_LOYALTY_TYPE_LEVEL"));

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
    if ($ruleId > 0 && class_exists('Legacy\Loyalty\RuleBuilder\LevelRuleTable')) {
        $rule = \Legacy\Loyalty\RuleBuilder\LevelRuleTable::getById($ruleId)->fetch();
        if ($rule) {
            \Legacy\Loyalty\RuleBuilder\LevelRuleTable::delete($ruleId);
            LocalRedirect($APPLICATION->GetCurPageParam() . '&deleted=Y');
        }
    }
}

if ($request->get('deleted') === 'Y') {
    $message = ["TYPE" => "OK", "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_DELETED")];
}

$levelRules = [];
if (class_exists('Legacy\Loyalty\RuleBuilder\LevelRuleTable')) {
    try {
        $levelRules = \Legacy\Loyalty\RuleBuilder\LevelRuleTable::getList([
            'order' => [
                'SORT' => 'ASC',
                'ID' => 'DESC'], //todo надо аналогично бонусам
        ])->fetchAll();
    } catch (\Exception $e) {
        $message = ["TYPE" => "ERROR", "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_RULES_LOAD_ERROR") . $e->getMessage()];
    }
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if ($message) {
    CAdminMessage::ShowMessage($message);
}

$aTabs = [
    ["DIV" => "rules", "TAB" => Loc::getMessage("LEGACY_LOYALTY_TAB_RULE"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_TAB_RULE")],
    ["DIV" => "settings", "TAB" => Loc::getMessage("LEGACY_LOYALTY_TAB_SETTINGS"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_TAB_SETTINGS")],
];

$tabControl = new CAdminTabControl("tabControl", $aTabs);

function renderLevelRuleCard($rule, $APPLICATION) {
    $isActive = ($rule['ACTIVE'] ?? '') === 'Y';
    $statusKey = $isActive
            ? 'LEGACY_LOYALTY_VIEW_ACTIVE'
            : 'LEGACY_LOYALTY_VIEW_INACTIVE';
    $statusTitle = Loc::getMessage($statusKey);
    $statusEmoji = $isActive ? '✅' : '❌';
?>

<div class="leglol-rule-card">
    <div class="leglol-rule-header">
        <div>
            <span title="<?= $statusTitle ?>"><?= $statusEmoji ?></span>
            <span class="leglol-rule-left"><?= Loc::getMessage("LEGACY_LOYALTY_VIEW_PRIORITY") ?><b><?= (int)$rule['SORT'] ?></b></span>
            <?php if ((int)$rule['PERIOD'] > 0): ?>
                <span class="leglol-rule-left"><?= Loc::getMessage("LEGACY_LOYALTY_VIEW_PERIOD") ?><b><?= (int)$rule['PERIOD'] ?> дн.</b></span>
            <?php endif; ?>
        </div>
        <div class="leglol-rule-actions">
            <a href="level_rule_edit.php?ID=<?= $rule['ID'] ?>&lang=<?= LANG ?>"
                class="adm-btn adm-btn-save">
                <?= Loc::getMessage("LEGACY_LOYALTY_EDIT_RULE_BTN") ?>
            </a>
            <?php
            $deleteUrl = $APPLICATION->GetCurPageParam();
            $deleteUrl .= (strpos($deleteUrl, '?') !== false ? '&' : '?');
            $deleteUrl .= 'action=delete&rule_id=' . (int)$rule['ID'] . '&' . bitrix_sessid_get();
            ?>
            <a href="<?= $deleteUrl ?>"
                onclick="return confirm('<?= Loc::getMessage("LEGACY_LOYALTY_CONFIRM_DELETE_RULE") ?>')"
                class="adm-btn adm-btn-danger">
                <?= Loc::getMessage("LEGACY_LOYALTY_DELETE_RULE_BTN") ?>
            </a>
        </div>
    </div>
    <div class="leglol-amount-block">
        <strong class="leglol-14px"><?= htmlspecialcharsbx($rule['NAME']) ?> [<?= (int)$rule['ID'] ?>]</strong>
    </div>
</div>
<?php
}
?>

<form method="post">
    <?=bitrix_sessid_post()?>
    <?php
    $tabControl->Begin();
    $tabControl->BeginNextTab();
    ?>

    <div class="leglol-margin20">
        <a href="level_rule_edit.php?lang=<?=LANG?>" class="adm-btn adm-btn-green">
            <?=Loc::getMessage("LEGACY_LOYALTY_ADD_NEW_RULE")?>
        </a>
    </div>

    <?php if (empty($levelRules)): ?>
        <div class="leglol-rules"><?=Loc::getMessage("LEGACY_LOYALTY_NO_RULES")?></div>
    <?php else:
        foreach ($levelRules as $rule):
            renderLevelRuleCard($rule, $APPLICATION);
        endforeach;
    endif; ?>

    <?php
    $tabControl->EndTab();
    $tabControl->BeginNextTab();
    ?>

    <!-- Заглушка для настроек программы уровней -->
    <tr>
        <td colspan="2">
            <div style="color:#666;background:#f9f9f9;padding:12px;border-radius:4px;">
                <?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_SETTINGS_PLACEHOLDER") ?>
            </div>
        </td>
    </tr>

    <?php
    $tabControl->EndTab();

    $tabControl->Buttons([
        "btnSave" => true,
        "btnApply" => true,
        "btnCancel" => true,
        "back_url" => "menu_program.php"
    ]);
    $tabControl->End();
    ?>
</form>

<style>
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

    .leglol-rule-left {
        margin-left: 8px;
    }

    .leglol-rule-actions {
        display: flex;
        gap: 8px;
    }

    .leglol-amount-block {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .leglol-14px {
        font-size: 14px;
    }

    .leglol-margin20 {
        margin-bottom: 20px;
    }

    .leglol-rules {
        padding: 20px;
        background: #f9f9f9;
        border: 1px dashed #ccc;
        border-radius: 4px;
        color: #666;
    }
</style>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");