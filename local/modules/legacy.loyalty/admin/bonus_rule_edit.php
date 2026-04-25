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

$request = Application::getInstance()->getContext()->getRequest();
$ID = (int)$request->get("ID");
$arRule = $ID > 0 ? BonusRuleTable::getById($ID)->fetch() : [];

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

// ошибка парсинга если с редиректа
if ($request->get('cond_error') === 1) {
    $message = new CAdminMessage([
        "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_CONDITIONS_PARSE_ERROR"),
        "TYPE" => "WARNING",
        "DETAILS" => Loc::getMessage("LEGACY_LOYALTY_CONDITIONS_PARSE_ERROR_DETAILS")
    ]);
}

if ($request->isPost() && check_bitrix_sessid()) {
    // устойчивый парсинг
    $conditionsToSave = [];
    $parseError = null;
    $obCondParse = new CSaleCondTree();
    $boolCondParse = $obCondParse->Init(
        BT_COND_MODE_PARSE,
        BT_COND_BUILD_SALE,
        [
            'INIT_CONTROLS' => [
                'SITE_ID' => $request->getPost('LID') ?: SITE_ID,
                'CURRENCY' => 'RUB'
            ]
        ]
    );

    if ($boolCondParse) {
        $parsedConditions = $obCondParse->Parse();

        if (is_array($parsedConditions)) {
            $conditionsToSave = $parsedConditions;
        } else {
            $parseError = Loc::getMessage("LEGACY_LOYALTY_CONDITIONS_PARSE_ERROR");
            $conditionsToSave = $arRule['CONDITIONS'] ?? [];
        }
    } else {
        $parseError = Loc::getMessage("LEGACY_LOYALTY_CONDITIONS_INIT_ERROR");
        $conditionsToSave = $arRule['CONDITIONS'] ?? [];
    }

    $arFields = [
        "ACTIVE" => $request->getPost("ACTIVE") === "Y" ? "Y" : "N",
        "SORT" => (int)$request->getPost("SORT"),
        "TYPE" => $request->getPost("TYPE"),
        "APPLY_TYPE" => $request->getPost("APPLY_TYPE"),
        "AMOUNT_TYPE" => $request->getPost("AMOUNT_TYPE"),
        "AMOUNT" => (int)$request->getPost("AMOUNT"),
        "CONDITIONS" => $conditionsToSave,
    ];

    $res = $ID > 0
        ? BonusRuleTable::update($ID, $arFields)
        : BonusRuleTable::add($arFields);

    if ($res->isSuccess() && !$ID) {
        $ID = $res->getId();
    }

    if ($res->isSuccess()) {
        $redirectParams = "&lang=" . LANG;
        if ($parseError) {
            $redirectParams .= "&cond_error=1";
        }

        LocalRedirect($request->getPost("apply")
            ? "bonus_rule_edit.php?ID=" . $ID . $redirectParams
            : "program_bonus.php?lang=" . LANG . ($parseError ? "&cond_error=1" : "")
        );
    } else {
        $message = new CAdminMessage(["MESSAGE" => implode("<br>", $res->getErrorMessages()), "TYPE" => "ERROR"]);
    }
}

$APPLICATION->SetTitle($ID > 0 ? Loc::getMessage("LEGACY_LOYALTY_EDIT_RULE") : Loc::getMessage("LEGACY_LOYALTY_ADD_RULE"));
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if ($message) CAdminMessage::ShowMessage($message);

$aTabs = [
        ["DIV" => "edit_rule", "TAB" => Loc::getMessage("LEGACY_LOYALTY_RULE_TAB"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_RULE_TAB")],
];
$tabControl = new CAdminTabControl("tabControl", $aTabs);

if (!is_array($arRule['CONDITIONS'])) {
    if (CheckSerializedData($arRule['CONDITIONS'])) {
        $arRule['CONDITIONS'] = unserialize($arRule['CONDITIONS'], ['allowed_classes' => false]);
    } else {
        $arRule['CONDITIONS'] = [];
    }
}

if (!defined('BT_COND_MODE_DEFAULT')) define('BT_COND_MODE_DEFAULT', 0);
if (!defined('BT_COND_BUILD_SALE')) define('BT_COND_BUILD_SALE', 'sale');

$arCondParams = [
    'FORM_NAME' => 'form_edit',
    'CONT_ID' => 'condition-tree',
    'JS_NAME' => 'JSLoyaltyCond',
    'INIT_CONTROLS' => ['SITE_ID' => SITE_ID, 'CURRENCY' => 'RUB'],
];

$obCond = new CSaleCondTree();
$boolCond = $obCond->Init(BT_COND_MODE_DEFAULT, BT_COND_BUILD_SALE, $arCondParams);
?>

<form method="POST" action="<?= $APPLICATION->GetCurPageParam() ?>" name="form_edit" id="form_edit">
    <?=bitrix_sessid_post()?>
    <input type="hidden" name="ID" value="<?= $ID ?>">

    <?php $tabControl->Begin(); $tabControl->BeginNextTab(); ?>

    <tr><td width="40%"><?= Loc::getMessage("LEGACY_LOYALTY_ACTIVE") ?></td><td width="60%"><input type="checkbox" name="ACTIVE" value="Y" <?= ($arRule['ACTIVE'] === 'Y' ? 'checked' : '') ?>></td></tr>
    <tr><td><?= Loc::getMessage("LEGACY_LOYALTY_SORT") ?></td><td><input type="number" name="SORT" value="<?= (int)$arRule['SORT'] ?>" class="leglol-numeric-input"></td></tr>
    <tr><td><?= Loc::getMessage("LEGACY_LOYALTY_RULE_TYPE") ?></td><td>
            <select name="TYPE"><option value="add" <?= ($arRule['TYPE'] === 'add' ? 'selected' : '') ?>><?= Loc::getMessage("LEGACY_LOYALTY_TYPE_ADD") ?></option><option value="spend" <?= ($arRule['TYPE'] === 'spend' ? 'selected' : '') ?>><?= Loc::getMessage("LEGACY_LOYALTY_TYPE_SPEND") ?></option></select>
        </td></tr>
    <tr><td><?= Loc::getMessage("LEGACY_LOYALTY_SCOPE") ?></td><td>
            <select name="APPLY_TYPE"><option value="product" <?= ($arRule['APPLY_TYPE'] === 'product' ? 'selected' : '') ?>><?= Loc::getMessage("LEGACY_LOYALTY_SCOPE_PRODUCT") ?></option><option value="order" <?= ($arRule['APPLY_TYPE'] === 'order' ? 'selected' : '') ?>><?= Loc::getMessage("LEGACY_LOYALTY_SCOPE_ORDER") ?></option></select>
        </td></tr>
    <tr><td><?= Loc::getMessage("LEGACY_LOYALTY_AMOUNT_TYPE") ?></td><td>
            <select name="AMOUNT_TYPE"><option value="percent" <?= ($arRule['AMOUNT_TYPE'] === 'percent' ? 'selected' : '') ?>><?= Loc::getMessage("LEGACY_LOYALTY_AMOUNT_TYPE_PERCENT") ?></option><option value="fixed" <?= ($arRule['AMOUNT_TYPE'] === 'fixed' ? 'selected' : '') ?>><?= Loc::getMessage("LEGACY_LOYALTY_AMOUNT_TYPE_FIXED") ?></option></select>
        </td></tr>
    <tr><td><?= Loc::getMessage("LEGACY_LOYALTY_AMOUNT") ?></td><td><input type="number" name="AMOUNT" value="<?= (int)$arRule['AMOUNT'] ?>" class="leglol-numeric-input"></td></tr>

    <tr class="heading"><td colspan="2"><?= Loc::getMessage("LEGACY_LOYALTY_CONDITIONS") ?></td></tr>
    <tr><td colspan="2">
            <?php if ($boolCond): ?>
                <!-- конструктор условий -->
                <div id="condition-tree" class="leglol-condition-builder">
                    <?php
                    ob_start();
                    $obCond->Show($arRule['CONDITIONS']);
                    $jsOutput = ob_get_clean();
                    $jsOutput = preg_replace("/('parentContainer'\s*:\s*)''/", "$1'condition-tree'", $jsOutput);
                    $jsOutput = preg_replace("/('form'\s*:\s*)''/", "$1'form_edit'", $jsOutput);
                    echo $jsOutput;
                    ?>
                </div>
            <?php else: ?>
                <div>❌ <?=Loc::getMessage("LEGACY_LOYALTY_CONDITIONS_INIT_ERROR")?> </div>
            <?php endif; ?>
        </td></tr>

    <?php
    $tabControl->Buttons([
        "btnSave" => true, "btnApply" => true, "btnCancel" => true,
        "back_url" => "program_bonus.php?lang=" . LANG
    ]);
    $tabControl->End();
    ?>
</form>

<style>
    .leglol-numeric-input {
        width: 120px;
    }

    .sale-cond-tree-view, .sale-cond-control-cont {
        margin:0 !important; margin-bottom:8px !important;
    }

    .leglol-condition-builder {
        position: relative;
        z-index: 1;
        min-height: 100px;
    }
</style>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");