<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

Loc::loadMessages($_SERVER["DOCUMENT_ROOT"]."/local/modules/legacy.loyalty/admin/program_bonus.php");

$APPLICATION->SetTitle(Loc::getMessage("LEGACY_LOYALTY_TYPE_BONUS"));

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
    if ($ruleId > 0 && class_exists('Legacy\Loyalty\RuleBuilder\BonusRuleTable')) {
        $rule = \Legacy\Loyalty\RuleBuilder\BonusRuleTable::getById($ruleId)->fetch();
        if ($rule) {
            \Legacy\Loyalty\RuleBuilder\BonusRuleTable::delete($ruleId);
            LocalRedirect($APPLICATION->GetCurPageParam() . '&deleted=Y');
        }
    }
}

if ($request->get('deleted') === 'Y') {
    $message = ["TYPE" => "OK", "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_DELETED")];
}

if ($request->isPost() && check_bitrix_sessid() && $request->getPost('save_settings')) {
    Option::set("legacy.loyalty", "bonus_name", $request->getPost("bonus_name"));
    Option::set("legacy.loyalty", "bonus_lifetime", $request->getPost("bonus_lifetime"));
    Option::set("legacy.loyalty", "bonus_delay", $request->getPost("bonus_delay"));
    $message = ["TYPE" => "OK", "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_SAVED")];
}

$bonusName = Option::get("legacy.loyalty", "bonus_name", "Бонусы");
$bonusLifetime = Option::get("legacy.loyalty", "bonus_lifetime", "30");
$bonusDelay = Option::get("legacy.loyalty", "bonus_delay", "0");

$addRules = [];
$spendRules = [];
if (class_exists('Legacy\Loyalty\RuleBuilder\BonusRuleTable')) {
    try {
        $addRules = \Legacy\Loyalty\RuleBuilder\BonusRuleTable::getList([
            'filter' => ['TYPE' => 'add'],
            'order' => ['SORT' => 'DESC']
        ])->fetchAll();
        $spendRules = \Legacy\Loyalty\RuleBuilder\BonusRuleTable::getList([
            'filter' => ['TYPE' => 'spend'],
            'order' => ['SORT' => 'DESC']
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
    ["DIV" => "rules_add", "TAB" => Loc::getMessage("LEGACY_LOYALTY_TAB_ADD"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_TAB_ADD")],
    ["DIV" => "rules_spend", "TAB" => Loc::getMessage("LEGACY_LOYALTY_TAB_SPEND"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_TAB_SPEND")],
    ["DIV" => "settings", "TAB" => Loc::getMessage("LEGACY_LOYALTY_TAB_SETTINGS"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_TAB_SETTINGS")],
];

$tabControl = new CAdminTabControl("tabControl", $aTabs);

function renderRuleCard($rule, $type, $APPLICATION) {
    $isActive = ($rule['ACTIVE'] ?? '') === 'Y';
    $statusKey = $isActive
            ? 'LEGACY_LOYALTY_VIEW_ACTIVE'
            : 'LEGACY_LOYALTY_VIEW_INACTIVE';
    $statusTitle = Loc::getMessage($statusKey);
    $statusEmoji = $isActive ? '✅' : '❌';

    $prefixKey = $type === 'add'
            ? 'LEGACY_LOYALTY_VIEW_ADD'
            : 'LEGACY_LOYALTY_VIEW_SPEND';
    $prefix = Loc::getMessage($prefixKey);

    $scopeKey = ($rule['APPLY_TYPE'] ?? '') === 'product'
            ? 'LEGACY_LOYALTY_VIEW_SCOPE_PRODUCT'
            : 'LEGACY_LOYALTY_VIEW_SCOPE_ORDER';
    $scope = Loc::getMessage($scopeKey);

    $amountTypeKey = ($rule['AMOUNT_TYPE'] ?? '') === 'percent'
            ? 'LEGACY_LOYALTY_VIEW_AMOUNT_TYPE_PERCENT'
            : 'LEGACY_LOYALTY_VIEW_AMOUNT_TYPE_FIXED';
    $amountType = Loc::getMessage($amountTypeKey);

    $conditionsInfo = Loc::getMessage("LEGACY_LOYALTY_VIEW_CONDITIONS")
            . (is_array($rule['CONDITIONS'] ?? null) ? count($rule['CONDITIONS']) : 0);
?>

<div style="margin-bottom: 12px; background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; padding: 12px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px; font-size:12px; color:#666;">
        <div>
            <span title="<?= $statusTitle ?>"><?= $statusEmoji ?></span>
            <span style="margin-left: 8px;"><?= Loc::getMessage("LEGACY_LOYALTY_VIEW_PRIORITY") ?><b><?= (int)$rule['SORT'] ?></b></span>
            <span style="margin-left: 8px;"><?= Loc::getMessage("LEGACY_LOYALTY_VIEW_SCOPE") ?><b><?= $scope ?></b></span>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="bonus_rule_edit.php?ID=<?= $rule['ID'] ?>&lang=<?= LANG ?>"
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
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 8px;">
        <strong style="font-size: 14px;">
            <?= $prefix ?> <span><?= (int)$rule['AMOUNT'] ?></span> <?= $amountType ?>
        </strong>
        <span style="font-size:12px; color:#888; background:#f5f5f5; padding:4px 8px; border-radius:3px;">
            <?= $conditionsInfo ?>
        </span>
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

    <div style="margin-bottom: 20px;">
        <a href="bonus_rule_edit.php?TYPE=add&lang=<?=LANG?>" class="adm-btn adm-btn-green">
            <?=Loc::getMessage("LEGACY_LOYALTY_ADD_NEW_RULE")?>
        </a>
    </div>

    <?php if (empty($addRules)): ?>
        <div style="padding: 20px; background: #f9f9f9; border: 1px dashed #ccc; border-radius: 4px; color: #666;">
            <?=Loc::getMessage("LEGACY_LOYALTY_NO_RULES")?>
        </div>
    <?php else:
        foreach ($addRules as $rule):
            renderRuleCard($rule, 'add', $APPLICATION);
        endforeach;
    endif; ?>

    <?php
    $tabControl->EndTab();
    $tabControl->BeginNextTab();
    ?>

    <div style="margin-bottom: 20px;">
        <a href="bonus_rule_edit.php?TYPE=spend&lang=<?=LANG?>" class="adm-btn adm-btn-green">
            <?=Loc::getMessage("LEGACY_LOYALTY_ADD_NEW_RULE")?>
        </a>
    </div>

    <?php if (empty($spendRules)): ?>
        <div style="padding: 20px; background: #f9f9f9; border: 1px dashed #ccc; border-radius: 4px; color: #666;">
            <?=Loc::getMessage("LEGACY_LOYALTY_NO_RULES")?>
        </div>
    <?php else:
        foreach ($spendRules as $rule):
            renderRuleCard($rule, 'spend', $APPLICATION);
        endforeach;
    endif; ?>

    <?php
    $tabControl->EndTab();
    $tabControl->BeginNextTab();
    ?>

    <tr>
        <td><?=Loc::getMessage("LEGACY_LOYALTY_BONUS_NAME")?></td>
        <td><input type="text" name="bonus_name" value="<?=htmlspecialcharsbx($bonusName)?>"></td>
    </tr>
    <tr>
        <td><?=Loc::getMessage("LEGACY_LOYALTY_BONUS_LIFE")?></td>
        <td><input type="number" name="bonus_lifetime" value="<?=htmlspecialcharsbx($bonusLifetime)?>"></td>
    </tr>
    <tr>
        <td><?=Loc::getMessage("LEGACY_LOYALTY_BONUS_DELAY")?></td>
        <td><input type="number" name="bonus_delay" value="<?=htmlspecialcharsbx($bonusDelay)?>"></td>
    </tr>

    <?php
    $tabControl->EndTab();

    $tabControl->Buttons([
        "btnSave" => true,
        "btnApply" => true,
        "btnCancel" => true,
        "back_url" => "menu_program.php"
    ]);
    echo '<input type="hidden" name="save_settings" value="Y">';
    $tabControl->End();
    ?>
</form>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");