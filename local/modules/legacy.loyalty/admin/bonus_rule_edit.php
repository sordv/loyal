<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Legacy\Loyalty\RuleBuilder\BonusRuleTable;
use Legacy\Loyalty\Conditions as LoyaltyConditions;
use Legacy\Loyalty\Conditions\Order as OrderConditions;
use Legacy\Loyalty\Conditions\Product as ProductConditions;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
Loc::loadMessages($_SERVER["DOCUMENT_ROOT"]."/local/modules/legacy.loyalty/admin/bonus_rule_edit.php");

if (!Loader::includeModule('legacy.loyalty')) {
    die(Loc::getMessage("LEGACY_LOYALTY_MODULE_NOT_INSTALLED"));
}

$request = Application::getInstance()->getContext()->getRequest();
$ID = (int)$request->get("ID");
$arRule = $ID > 0 ? BonusRuleTable::getById($ID)->fetch() : [];

if (empty($arRule)) {
    $arRule['TYPE'] = $request->get("TYPE") ?: 'add';
    $arRule['ACTIVE'] = 'Y';
    $arRule['SORT'] = 100;
    $arRule['NAME'] = '';
    $arRule['AMOUNT_TYPE'] = 'percent';
    $arRule['AMOUNT'] = 0;
    $arRule['CONDITIONS_ORDER'] = [];
    $arRule['CONDITIONS_PRODUCT'] = [];
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
    $parseErrorOrder = null;
    $parseErrorProduct = null;

    $rawOrder = $request->getPost('ruleOrderCond');
    $rawProduct = $request->getPost('ruleProductCond');

    $conditionsToSaveOrder = is_array($rawOrder)
        ? LoyaltyConditions::saveConditions($rawOrder)
        : ($arRule['CONDITIONS_ORDER'] ?? []);
    $conditionsToSaveProduct = is_array($rawProduct)
        ? LoyaltyConditions::saveConditions($rawProduct)
        : ($arRule['CONDITIONS_PRODUCT'] ?? []);

    $arFields = [
        "ACTIVE" => $request->getPost("ACTIVE") === "Y" ? "Y" : "N",
        "SORT" => (int)$request->getPost("SORT"),
        "TYPE" => $request->getPost("TYPE"),
        "NAME" => trim($request->getPost("NAME")),
        "AMOUNT_TYPE" => $request->getPost("AMOUNT_TYPE"),
        "AMOUNT" => (int)$request->getPost("AMOUNT"),
        "CONDITIONS_ORDER" => $conditionsToSaveOrder,
        "CONDITIONS_PRODUCT" => $conditionsToSaveProduct,
    ];

    // Валидация обязательных полей
    $nameIsEmpty = empty($arFields["NAME"]);
    if ($nameIsEmpty && $ID > 0) {
        $arFields["NAME"] = (string)$ID;
    }

    $res = $ID > 0
        ? BonusRuleTable::update($ID, $arFields)
        : BonusRuleTable::add($arFields);

    if ($res->isSuccess()) {
        if ($nameIsEmpty && !$ID) {
            $newId = $res->getId();
            BonusRuleTable::update($newId, ['NAME' => (string)$newId]);
            $ID = $newId;
        } elseif (!$ID) {
            $ID = $res->getId();
        }

        $redirectParams = "&lang=" . LANG;
        if ($parseErrorOrder || $parseErrorProduct) {
            $redirectParams .= "&cond_error=1";
        }

        LocalRedirect($request->getPost("apply")
            ? "bonus_rule_edit.php?ID=" . $ID . $redirectParams
            : "program_bonus.php?lang=" . LANG . ($parseErrorOrder || $parseErrorProduct ? "&cond_error=1" : "")
        );
    } else {
        $message = [
            "MESSAGE" => implode("<br>", $res->getErrorMessages()),
            "TYPE" => "ERROR"
        ];
    }
}

$APPLICATION->SetTitle($ID > 0 ? Loc::getMessage("LEGACY_LOYALTY_EDIT_RULE") : Loc::getMessage("LEGACY_LOYALTY_ADD_RULE"));
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if ($message) CAdminMessage::ShowMessage($message);

$aTabs = [
        ["DIV" => "edit_rule", "TAB" => Loc::getMessage("LEGACY_LOYALTY_RULE_TAB"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_RULE_TAB")],
];
$tabControl = new CAdminTabControl("tabControl", $aTabs);

foreach (['CONDITIONS_ORDER', 'CONDITIONS_PRODUCT'] as $field) {
    if (!is_array($arRule[$field])) {
        if (CheckSerializedData($arRule[$field])) {
            $arRule[$field] = unserialize($arRule[$field], ['allowed_classes' => false]);
        } else {
            $arRule[$field] = [];
        }
    }
}

?>

<form method="POST" action="<?= $APPLICATION->GetCurPageParam() ?>" name="form_edit" id="form_edit">
    <?=bitrix_sessid_post()?>
    <input type="hidden" name="ID" value="<?= $ID ?>">

    <?php
    $tabControl->Begin();
    $tabControl->BeginNextTab();
    ?>

    <!-- Активно -->
    <tr>
        <td width="40%"><?= Loc::getMessage("LEGACY_LOYALTY_RULE_ACTIVE") ?></td>
        <td width="60%">
            <input type="checkbox" name="ACTIVE" value="Y" <?= ($arRule['ACTIVE'] === 'Y' ? 'checked' : '') ?>>
        </td>
    </tr>

    <!-- Приоритет -->
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_RULE_SORT") ?></td>
        <td>
            <input type="number" name="SORT" value="<?= (int)$arRule['SORT'] ?>" class="leglol-numeric-input">
        </td>
    </tr>

    <!-- Название -->
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_RULE_NAME") ?> <span style="color:#c00">*</span></td>
        <td>
            <input type="text" name="NAME" value="<?= htmlspecialcharsbx($arRule['NAME']) ?>" style="width:100%;max-width:400px;">
        </td>
    </tr>

    <!-- Тип: начисление/списание -->
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_RULE_TYPE") ?></td>
        <td>
            <select name="TYPE">
                <option value="add" <?= ($arRule['TYPE'] === 'add' ? 'selected' : '') ?>>
                    <?= Loc::getMessage("LEGACY_LOYALTY_RULE_TYPE_ADD") ?>
                </option>
                <option value="spend" <?= ($arRule['TYPE'] === 'spend' ? 'selected' : '') ?>>
                    <?= Loc::getMessage("LEGACY_LOYALTY_RULE_TYPE_SPEND") ?>
                </option>
            </select>
        </td>
    </tr>

    <!-- Тип: процент/фикс -->
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_RULE_AMOUNT_TYPE") ?></td>
        <td>
            <select name="AMOUNT_TYPE">
                <option value="percent" <?= ($arRule['AMOUNT_TYPE'] === 'percent' ? 'selected' : '') ?>>
                    <?= Loc::getMessage("LEGACY_LOYALTY_RULE_AMOUNT_TYPE_PERCENT") ?>
                </option>
                <option value="fixed" <?= ($arRule['AMOUNT_TYPE'] === 'fixed' ? 'selected' : '') ?>>
                    <?= Loc::getMessage("LEGACY_LOYALTY_RULE_AMOUNT_TYPE_FIXED") ?>
                </option>
            </select>
        </td></tr>

    <!-- Значение -->
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_AMOUNT") ?></td>
        <td>
            <input type="number" name="AMOUNT" value="<?= (int)$arRule['AMOUNT'] ?>" class="leglol-numeric-input">
        </td>
    </tr>

    <!-- Конструктор условий - Заказ -->
    <tr class="heading">
        <td colspan="2"><?= Loc::getMessage("LEGACY_LOYALTY_CONDITIONS_ORDER") ?></td>
    </tr>
    <tr>
        <td colspan="2">
            <?php
            CJSCore::Init(['core_condtree', 'core_userselector', 'core_date']);
            $orderTree = !empty($arRule['CONDITIONS_ORDER'])
                ? \Bitrix\Main\Web\Json::encode($arRule['CONDITIONS_ORDER'])
                : OrderConditions::baseConditions('json');
            ?>
            <div id="OrderConditions" class="leglol-condition-builder"></div>
            <script>
                BX.ready(function () {
                    new BX.TreeConditions(
                        <?=OrderConditions::mainParams('json')?>,
                        <?=$orderTree?>,
                        <?=OrderConditions::controls('json')?>
                    );
                });
            </script>
        </td>
    </tr>

    <!-- Конструктор условий - Товары -->
    <tr class="heading">
        <td colspan="2"><?= Loc::getMessage("LEGACY_LOYALTY_CONDITIONS_PRODUCT") ?></td>
    </tr>
    <tr>
        <td colspan="2">
            <?php
            $productTree = !empty($arRule['CONDITIONS_PRODUCT'])
                ? \Bitrix\Main\Web\Json::encode($arRule['CONDITIONS_PRODUCT'])
                : ProductConditions::baseConditions('json');
            ?>
            <div id="ProductConditions" class="leglol-condition-builder"></div>
            <script>
                BX.ready(function () {
                    new BX.TreeConditions(
                        <?=ProductConditions::mainParams('json')?>,
                        <?=$productTree?>,
                        <?=ProductConditions::controls('json')?>
                    );
                });
            </script>
        </td>
    </tr>

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

    .leglol-conditions-separator {
        margin: 20px 0;
        border-top: 1px dashed #ccc;
        padding-top: 20px;
    }
</style>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");