<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Web\Json;
use Legacy\Loyalty\RuleBuilder\BonusRuleTable;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
Loc::loadMessages($_SERVER["DOCUMENT_ROOT"]."/local/modules/legacy.loyalty/admin/bonus_rule_edit.php");

if (!Loader::includeModule('legacy.loyalty') || !Loader::includeModule('sale')) {
    die(Loc::getMessage("LEGACY_LOYALTY_MODULE_NOT_INSTALLED"));
}

\Bitrix\Main\UI\Extension::load('sale.discount.condition');
\Bitrix\Main\UI\Extension::load('ui.forms');

$request = Application::getInstance()->getContext()->getRequest();
$ID = (int)$request->get("ID");
$arRule = $ID > 0 ? BonusRuleTable::getById($ID)->fetch() : [];

// Предзаполнение при создании
if (empty($arRule)) {
    $arRule['TYPE'] = $request->get("TYPE") ?: 'add';
    $arRule['ACTIVE'] = 'Y';
    $arRule['SORT'] = 100;
    $arRule['APPLY_TYPE'] = 'product';
    $arRule['AMOUNT_TYPE'] = 'percent';
    $arRule['AMOUNT'] = 0;
    $arRule['CONDITIONS'] = [];
}

$message = null;
if ($request->isPost() && check_bitrix_sessid()) {
    $arFields = [
            "ACTIVE" => $request->getPost("ACTIVE") === "Y" ? "Y" : "N",
            "SORT" => (int)$request->getPost("SORT"),
            "TYPE" => $request->getPost("TYPE"),
            "APPLY_TYPE" => $request->getPost("APPLY_TYPE"),
            "AMOUNT_TYPE" => $request->getPost("AMOUNT_TYPE"),
            "AMOUNT" => (int)$request->getPost("AMOUNT"),
            "CONDITIONS" => Json::decode($request->getPost("CONDITIONS") ?: '[]'),
    ];

    if ($ID > 0) {
        $res = BonusRuleTable::update($ID, $arFields);
    } else {
        $res = BonusRuleTable::add($arFields);
        if ($res->isSuccess()) $ID = $res->getId();
    }

    if ($res->isSuccess()) {
        if ($request->getPost("apply")) {
            LocalRedirect("bonus_rule_edit.php?ID=" . $ID . "&lang=" . LANG);
        } else {
            LocalRedirect("program_bonus.php?lang=" . LANG);
        }
    } else {
        $message = new CAdminMessage([
                "MESSAGE" => implode("<br>", $res->getErrorMessages()),
                "TYPE" => "ERROR",
                "DETAILS" => $res->getErrorMessages()
        ]);
    }
}

$APPLICATION->SetTitle($ID > 0 ? Loc::getMessage("LEGACY_LOYALTY_EDIT_RULE") : Loc::getMessage("LEGACY_LOYALTY_ADD_RULE"));
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if ($message) CAdminMessage::ShowMessage($message);

$aTabs = [
        ["DIV" => "edit_rule", "TAB" => Loc::getMessage("LEGACY_LOYALTY_RULE_TAB"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_RULE_TAB")],
];
$tabControl = new CAdminTabControl("tabControl", $aTabs);
?>

<form method="POST" action="<?= $APPLICATION->GetCurPageParam() ?>" name="form_edit">
    <?=bitrix_sessid_post()?>
    <input type="hidden" name="ID" value="<?= $ID ?>">
    <?php
    $tabControl->Begin();
    $tabControl->BeginNextTab();
    ?>

    <tr>
        <td width="40%"><?= Loc::getMessage("LEGACY_LOYALTY_ACTIVE") ?></td>
        <td width="60%"><input type="checkbox" name="ACTIVE" value="Y" <?= ($arRule['ACTIVE'] === 'Y' ? 'checked' : '') ?>></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_SORT") ?></td>
        <td><input type="number" name="SORT" value="<?= (int)$arRule['SORT'] ?>" class="leglol-numeric-input"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_RULE_TYPE") ?></td>
        <td>
            <select name="TYPE">
                <option value="add" <?= ($arRule['TYPE'] === 'add' ? 'selected' : '') ?>>
                    <?= Loc::getMessage("LEGACY_LOYALTY_TYPE_ADD") ?>
                </option>
                <option value="spend" <?= ($arRule['TYPE'] === 'spend' ? 'selected' : '') ?>>
                    <?= Loc::getMessage("LEGACY_LOYALTY_TYPE_SPEND") ?>
                </option>
            </select>
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_SCOPE") ?></td>
        <td>
            <select name="APPLY_TYPE">
                <option value="product" <?= ($arRule['APPLY_TYPE'] === 'product' ? 'selected' : '') ?>>
                    <?= Loc::getMessage("LEGACY_LOYALTY_SCOPE_PRODUCT") ?>
                </option>
                <option value="order" <?= ($arRule['APPLY_TYPE'] === 'order' ? 'selected' : '') ?>>
                    <?= Loc::getMessage("LEGACY_LOYALTY_SCOPE_ORDER") ?>
                </option>
            </select>
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_AMOUNT_TYPE") ?></td>
        <td>
            <select name="AMOUNT_TYPE">
                <option value="percent" <?= ($arRule['AMOUNT_TYPE'] === 'percent' ? 'selected' : '') ?>>
                    <?= Loc::getMessage("LEGACY_LOYALTY_AMOUNT_TYPE_PERCENT") ?>
                </option>
                <option value="fixed" <?= ($arRule['AMOUNT_TYPE'] === 'fixed' ? 'selected' : '') ?>>
                    <?= Loc::getMessage("LEGACY_LOYALTY_AMOUNT_TYPE_FIXED") ?>
                </option>
            </select>
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_AMOUNT") ?></td>
        <td><input type="number" name="AMOUNT" value="<?= (int)$arRule['AMOUNT'] ?>" class="leglol-numeric-input"></td>
    </tr>

        <!-- условия - выводим как обычную строку таблицы, без BeginCustomField -->
    <tr class="heading">
        <td colspan="2"><?= Loc::getMessage("LEGACY_LOYALTY_CONDITIONS") ?></td>
    </tr>
    <tr>
        <td colspan="2">
            <div id="condition-tree-container" class="leglol-cond"></div>
            <input type="hidden" name="CONDITIONS" id="input-conditions" value="<?= htmlspecialcharsbx(Json::encode($arRule['CONDITIONS'] ?? [])) ?>">
        </td>
    </tr>

    <script>
        BX.ready(function () {
            const treeValue = <?= Json::encode($arRule['CONDITIONS'] ?? []) ?>;
            if (typeof BX.Sale.Discount.Condition.Control !== 'undefined') {
                new BX.Sale.Discount.Condition.Control({
                    containerId: 'condition-tree-container',
                    hiddenInputId: 'input-conditions',
                    value: treeValue,
                    scope: 'BASKET',
                    moduleId: 'legacy.loyalty'
                });
            } else {
                console.warn('BX.Sale.Discount.Condition.Control not loaded');
            }
        });
    </script>

    <?php
    $tabControl->Buttons([
        "btnSave" => true,
        "btnApply" => true,
        "btnCancel" => true,
        "back_url" => "program_bonus.php?lang=" . LANG
    ]);
    $tabControl->End();
    ?>
</form>

<style>
    .leglol-numeric-input {
        width: 120px;
    }

    .leglol-cond {
        background: #f9f9f9;
        padding: 15px;
        border: 1px solid #e5e5e5;
        border-radius: 4px;
        min-height: 100px;
    }
</style>
<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");