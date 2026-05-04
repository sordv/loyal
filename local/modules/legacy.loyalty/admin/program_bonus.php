<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Legacy\Loyalty\Service\ProgramService;

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
    Option::set("legacy.loyalty", "bonus_accrual_order_status", (string)$request->getPost("bonus_accrual_order_status"));
    Option::set("legacy.loyalty", "bonus_accrual_on_paid", $request->getPost("bonus_accrual_on_paid") === 'Y' ? 'Y' : 'N');
    Option::set("legacy.loyalty", "mail_bonus_order", $request->getPost("mail_bonus_order") === 'Y' ? 'Y' : 'N');
    Option::set("legacy.loyalty", "mail_bonus_admin", $request->getPost("mail_bonus_admin") === 'Y' ? 'Y' : 'N');
    Option::set("legacy.loyalty", "mail_bonus_expire", $request->getPost("mail_bonus_expire") === 'Y' ? 'Y' : 'N');
    Option::set("legacy.loyalty", "mail_bonus_expire_days", trim((string)$request->getPost("mail_bonus_expire_days")));
    $message = ["TYPE" => "OK", "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_SAVED")];
}

$bonusName = ProgramService::getBonusDisplayName();
$bonusLifetime = Option::get("legacy.loyalty", "bonus_lifetime", "365");
$bonusDelay = Option::get("legacy.loyalty", "bonus_delay", "1");
$bonusAccrualOrderStatus = Option::get("legacy.loyalty", "bonus_accrual_order_status", "F");
$bonusAccrualOnPaid = Option::get("legacy.loyalty", "bonus_accrual_on_paid", "Y");
$mailBonusOrder = Option::get("legacy.loyalty", "mail_bonus_order", "N");
$mailBonusAdmin = Option::get("legacy.loyalty", "mail_bonus_admin", "N");
$mailBonusExpire = Option::get("legacy.loyalty", "mail_bonus_expire", "N");
$mailBonusExpireDays = Option::get("legacy.loyalty", "mail_bonus_expire_days", "7,3,1");

$orderStatuses = [
    '' => Loc::getMessage("LEGACY_LOYALTY_BONUS_ACCRUAL_ORDER_STATUS_EMPTY"),
];
if (Loader::includeModule('sale')) {
    $dbStatuses = \Bitrix\Sale\Internals\StatusLangTable::getList([
        'order' => ['STATUS.SORT' => 'ASC'],
        'filter' => ['STATUS.TYPE' => 'O', 'LID' => LANGUAGE_ID],
        'select' => ['STATUS_ID', 'NAME'],
    ]);
    while ($status = $dbStatuses->fetch()) {
        $orderStatuses[(string)$status['STATUS_ID']] = '[' . $status['STATUS_ID'] . '] ' . $status['NAME'];
    }
}

$addRules = [];
$spendRules = [];
if (class_exists('Legacy\Loyalty\RuleBuilder\BonusRuleTable')) {
    try {
        $addRules = \Legacy\Loyalty\RuleBuilder\BonusRuleTable::getList([
            'filter' => ['TYPE' => 'add'],
            'order' => ['SORT' => 'ASC']
        ])->fetchAll();
        $spendRules = \Legacy\Loyalty\RuleBuilder\BonusRuleTable::getList([
            'filter' => ['TYPE' => 'spend'],
            'order' => ['SORT' => 'ASC']
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

function renderBonusRuleCard($rule, $type, $APPLICATION) {
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

    $amountTypeKey = ($rule['AMOUNT_TYPE'] ?? '') === 'percent'
            ? 'LEGACY_LOYALTY_VIEW_AMOUNT_TYPE_PERCENT'
            : 'LEGACY_LOYALTY_VIEW_AMOUNT_TYPE_FIXED';
    $amountType = Loc::getMessage($amountTypeKey);

    $amountValue = (int)$rule['AMOUNT'];
    $amountDisplay = $rule['AMOUNT_TYPE'] === 'percent'
        ? "{$amountValue}" . Loc::getMessage("LEGACY_LOYALTY_VIEW_AMOUNT_TYPE_PERCENT")
        : "{$amountValue}" . Loc::getMessage("LEGACY_LOYALTY_VIEW_AMOUNT_TYPE_FIXED");
?>

<div class="leglol-rule-card">
    <div class="leglol-rule-header">
        <div>
            <span title="<?= $statusTitle ?>"><?= $statusEmoji ?></span>
            <span class="leglol-rule-left"><?= Loc::getMessage("LEGACY_LOYALTY_VIEW_PRIORITY") ?><b><?= (int)$rule['SORT'] ?></b></span>
            <span class="leglol-rule-left leglol-amount-in-header">
                <?= $prefix ?>: <b><?= $amountDisplay ?></b>
            </span>
        </div>
        <div class="leglol-rule-actions">
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
    <div class="leglol-amount-block">
        <strong class="leglol-14px">
            <?= htmlspecialcharsbx($rule['NAME'] ?: '-') ?>
        </strong>
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
        <a href="bonus_rule_edit.php?TYPE=add&lang=<?=LANG?>" class="adm-btn adm-btn-green">
            <?=Loc::getMessage("LEGACY_LOYALTY_ADD_NEW_RULE")?>
        </a>
    </div>

    <?php if (empty($addRules)): ?>
        <div class="leglol-rules"><?=Loc::getMessage("LEGACY_LOYALTY_NO_RULES")?></div>
    <?php else:
        foreach ($addRules as $rule):
            renderBonusRuleCard($rule, 'add', $APPLICATION);
        endforeach;
    endif; ?>

    <?php
    $tabControl->EndTab();
    $tabControl->BeginNextTab();
    ?>

    <div class="leglol-margin20">
        <a href="bonus_rule_edit.php?TYPE=spend&lang=<?=LANG?>" class="adm-btn adm-btn-green">
            <?=Loc::getMessage("LEGACY_LOYALTY_ADD_NEW_RULE")?>
        </a>
    </div>

    <?php if (empty($spendRules)): ?>
        <div class="leglol-rules">
            <?=Loc::getMessage("LEGACY_LOYALTY_NO_RULES")?>
        </div>
    <?php else:
        foreach ($spendRules as $rule):
            renderBonusRuleCard($rule, 'spend', $APPLICATION);
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
    <tr class="heading">
        <td colspan="2"><?= Loc::getMessage("LEGACY_LOYALTY_BONUS_ACCRUAL_HEADING") ?></td>
    </tr>
    <tr>
        <td><?=Loc::getMessage("LEGACY_LOYALTY_BONUS_ACCRUAL_ORDER_STATUS")?></td>
        <td>
            <select name="bonus_accrual_order_status">
                <?php foreach ($orderStatuses as $statusId => $statusName): ?>
                    <option value="<?=htmlspecialcharsbx($statusId)?>" <?= $bonusAccrualOrderStatus === (string)$statusId ? 'selected' : '' ?>>
                        <?=htmlspecialcharsbx($statusName)?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <td><?=Loc::getMessage("LEGACY_LOYALTY_BONUS_ACCRUAL_ON_PAID")?></td>
        <td>
            <input type="checkbox" name="bonus_accrual_on_paid" value="Y" <?= $bonusAccrualOnPaid === 'Y' ? 'checked' : '' ?>>
        </td>
    </tr>

    <tr class="heading">
        <td colspan="2"><?= Loc::getMessage("LEGACY_LOYALTY_MAIL_HEADING") ?></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_MAIL_BONUS_ORDER") ?></td>
        <td>
            <label>
                <input type="checkbox" name="mail_bonus_order" value="Y" <?= $mailBonusOrder === 'Y' ? 'checked' : '' ?>>
            </label>
            <a href="/bitrix/admin/message_admin.php?lang=<?= LANGUAGE_ID ?>&set_filter=Y&amp;find_type_id=<?= urlencode('LEGACY_LOYALTY_BONUS_ORDER_ADD') ?>" target="_blank"><?= Loc::getMessage("LEGACY_LOYALTY_MAIL_EDIT_TEMPLATE") ?></a>
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_MAIL_BONUS_ADMIN") ?></td>
        <td>
            <label>
                <input type="checkbox" name="mail_bonus_admin" value="Y" <?= $mailBonusAdmin === 'Y' ? 'checked' : '' ?>>
            </label>
            <a href="/bitrix/admin/message_admin.php?lang=<?= LANGUAGE_ID ?>&set_filter=Y&amp;find_type_id=<?= urlencode('LEGACY_LOYALTY_BONUS_ADMIN_ADD') ?>" target="_blank"><?= Loc::getMessage("LEGACY_LOYALTY_MAIL_EDIT_TEMPLATE") ?></a>
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_MAIL_BONUS_EXPIRE") ?></td>
        <td>
            <label>
                <input type="checkbox" name="mail_bonus_expire" value="Y" <?= $mailBonusExpire === 'Y' ? 'checked' : '' ?>>
            </label>
            <a href="/bitrix/admin/message_admin.php?lang=<?= LANGUAGE_ID ?>&set_filter=Y&amp;find_type_id=<?= urlencode('LEGACY_LOYALTY_BONUS_EXPIRE_WARNING') ?>" target="_blank"><?= Loc::getMessage("LEGACY_LOYALTY_MAIL_EDIT_TEMPLATE") ?></a>
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_MAIL_BONUS_EXPIRE_DAYS") ?></td>
        <td>
            <input type="text" name="mail_bonus_expire_days" value="<?= htmlspecialcharsbx($mailBonusExpireDays) ?>" size="40">
            <div style="color:#666;font-size:12px;margin-top:4px;"><?= Loc::getMessage("LEGACY_LOYALTY_MAIL_BONUS_EXPIRE_DAYS_NOTE") ?></div>
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
    echo '<input type="hidden" name="save_settings" value="Y">';
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

    .leglol-amount-in-header {
        margin-left: 12px;
        padding-left: 12px;
        border-left: 1px solid #e0e0e0;
        color: #333;
        font-weight: 500;
    }

    .leglol-amount-in-header b {
        color: #0d6610;
    }
</style>
<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
