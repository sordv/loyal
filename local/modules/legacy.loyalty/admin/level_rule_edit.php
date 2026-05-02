<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Legacy\Loyalty\RuleBuilder\LevelRuleTable;
use Legacy\Loyalty\Conditions as LoyaltyConditions;
use Legacy\Loyalty\Conditions\User as UserConditions;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
Loc::loadMessages($_SERVER["DOCUMENT_ROOT"]."/local/modules/legacy.loyalty/admin/level_rule_edit.php");

if (!Loader::includeModule('legacy.loyalty')) {
    die(Loc::getMessage("LEGACY_LOYALTY_MODULE_NOT_INSTALLED"));
}

$request = Application::getInstance()->getContext()->getRequest();
$ID = (int)$request->get("ID");
$arRule = $ID > 0 ? LevelRuleTable::getById($ID)->fetch() : [];

if (empty($arRule)) {
    $arRule['ACTIVE'] = 'Y';
    $arRule['SORT'] = 100;
    $arRule['NAME'] = '';
    $arRule['CONDITIONS'] = [];
    $arRule['PRIVILEGES'] = [];
}

$message = null;

if (!function_exists('normalizeLevelPrivileges')) {
    function normalizeLevelPrivileges($raw): array
    {
        $raw = is_array($raw) ? $raw : [];

        $percentFields = [
            'cartDiscountPercent',
            'deliveryDiscountPercent',
        ];
        $multiplierFields = [
            'addBonusMultiplier',
            'spendBonusMultiplier',
        ];

        $result = [];
        foreach ($percentFields as $field) {
            $value = (float)str_replace(',', '.', (string)($raw[$field] ?? 0));
            $result[$field] = max(0, min(100, $value));
        }

        foreach ($multiplierFields as $field) {
            $value = (float)str_replace(',', '.', (string)($raw[$field] ?? 1));
            $result[$field] = max(0, $value);
        }

        return $result;
    }
}

// ошибка парсинга если с редиректа
$condError = $request->get('cond_error');
if ($condError === '1' || (int)$condError === 1) {
    $message = new CAdminMessage([
        "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_LEVEL_COND_PARSE_ERROR"),
        "TYPE" => "WARNING",
        "DETAILS" => Loc::getMessage("LEGACY_LOYALTY_LEVEL_COND_PARSE_ERROR_DETAILS")
    ]);
}

if ($request->isPost() && check_bitrix_sessid()) {
    $conditionsToSave = [];
    $parseError = null;
    $raw = $request->getPost('levelRuleCond');
    $conditionsToSave = is_array($raw)
        ? LoyaltyConditions::saveConditions($raw)
        : ($arRule['CONDITIONS'] ?? []);

    $arFields = [
        "ACTIVE" => $request->getPost("ACTIVE") === "Y" ? "Y" : "N",
        "SORT" => (int)$request->getPost("SORT"),
        "NAME" => trim($request->getPost("NAME")),
        "CONDITIONS" => $conditionsToSave,
        "PRIVILEGES" => normalizeLevelPrivileges($request->getPost("PRIVILEGES")),
    ];

    // Валидация обязательных полей
    $nameIsEmpty = empty($arFields["NAME"]);
    if ($nameIsEmpty && $ID > 0) {
        $arFields["NAME"] = (string)$ID;
    }

    $res = $ID > 0
        ? LevelRuleTable::update($ID, $arFields)
        : LevelRuleTable::add($arFields);

    if ($res->isSuccess()) {
        if ($nameIsEmpty && !$ID) {
            $newId = $res->getId();
            LevelRuleTable::update($newId, ['NAME' => (string)$newId]);
            $ID = $newId;
        } elseif (!$ID) {
            $ID = $res->getId();
        }

        $redirectParams = "?lang=" . LANG;
        if ($parseError) {
            $redirectParams .= "&cond_error=1";
        }

        $baseUrl = $request->getPost("apply")
            ? "level_rule_edit.php?ID=" . $ID
            : "program_level.php";

        LocalRedirect($baseUrl . $redirectParams);
    } else {
        $message = [
                "MESSAGE" => implode("<br>", $res->getErrorMessages()),
                "TYPE" => "ERROR"
        ];
    }
}

$APPLICATION->SetTitle($ID > 0 ? Loc::getMessage("LEGACY_LOYALTY_LEVEL_EDIT_RULE") : Loc::getMessage("LEGACY_LOYALTY_LEVEL_ADD_RULE")
);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if ($message) CAdminMessage::ShowMessage($message);

$aTabs = [
    ["DIV" => "edit_rule", "TAB" => Loc::getMessage("LEGACY_LOYALTY_LEVEL_RULE_TAB"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_LEVEL_RULE_TAB")],
];
$tabControl = new CAdminTabControl("tabControl", $aTabs);

if (!is_array($arRule['CONDITIONS'])) {
    if (CheckSerializedData($arRule['CONDITIONS'])) {
        $arRule['CONDITIONS'] = unserialize($arRule['CONDITIONS'], ['allowed_classes' => false]);
    } else {
        $arRule['CONDITIONS'] = [];
    }
}

if (!is_array($arRule['PRIVILEGES'])) {
    if (CheckSerializedData($arRule['PRIVILEGES'])) {
        $arRule['PRIVILEGES'] = unserialize($arRule['PRIVILEGES'], ['allowed_classes' => false]);
    } else {
        $arRule['PRIVILEGES'] = [];
    }
}
$arRule['PRIVILEGES'] = normalizeLevelPrivileges($arRule['PRIVILEGES']);

if (!defined('BT_COND_MODE_DEFAULT')) define('BT_COND_MODE_DEFAULT', 0);
if (!defined('BT_COND_BUILD_USER')) define('BT_COND_BUILD_USER', 'user');
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
        <td width="40%"><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_ACTIVE") ?></td>
        <td width="60%">
            <input type="checkbox" name="ACTIVE" value="Y" <?= ($arRule['ACTIVE'] === 'Y' ? 'checked' : '') ?>>
        </td>
    </tr>

    <!-- Приоритет -->
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_SORT") ?></td>
        <td>
            <input type="number" name="SORT" value="<?= (int)$arRule['SORT'] ?>" class="leglol-numeric-input">
        </td>
    </tr>

    <!-- Название -->
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_NAME") ?> <span style="color:#c00">*</span></td>
        <td>
            <input type="text" name="NAME" value="<?= htmlspecialcharsbx($arRule['NAME']) ?>" style="width:100%;max-width:400px;">
        </td>
    </tr>

    <!-- Заголовок условий -->
    <tr class="heading">
        <td colspan="2"><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_CONDITIONS") ?></td>
    </tr>

    <!-- Конструктор условий -->
    <tr>
        <td colspan="2">
            <?php
            CJSCore::Init(['core_condtree', 'core_userselector', 'core_date']);
            $userTree = !empty($arRule['CONDITIONS'])
                ? \Bitrix\Main\Web\Json::encode($arRule['CONDITIONS'])
                : UserConditions::baseConditions('json');
            ?>
            <div id="UserConditions" class="leglol-condition-builder"></div>
            <script>
                BX.ready(function () {
                    new BX.TreeConditions(
                        <?=UserConditions::mainParams('json')?>,
                        <?=$userTree?>,
                        <?=UserConditions::controls('json')?>
                    );
                });
            </script>
        </td>
    </tr>

    <!-- Заглушка для наград (по ТЗ пока пусто) -->
    <tr class="heading">
        <td colspan="2"><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_PRIVILEGES") ?></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_PRIV_CART_DISCOUNT") ?: 'Скидка на корзину %' ?></td>
        <td>
            <input type="number" name="PRIVILEGES[cartDiscountPercent]" value="<?= htmlspecialcharsbx($arRule['PRIVILEGES']['cartDiscountPercent']) ?>" class="leglol-numeric-input" min="0" max="100" step="0.01"> %
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_PRIV_DELIVERY_DISCOUNT") ?: 'Скидка на доставку %' ?></td>
        <td>
            <input type="number" name="PRIVILEGES[deliveryDiscountPercent]" value="<?= htmlspecialcharsbx($arRule['PRIVILEGES']['deliveryDiscountPercent']) ?>" class="leglol-numeric-input" min="0" max="100" step="0.01"> %
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_PRIV_ADD_MULTIPLIER") ?: 'Повышенный коэффициент начисляемых бонусов' ?></td>
        <td>
            <input type="number" name="PRIVILEGES[addBonusMultiplier]" value="<?= htmlspecialcharsbx($arRule['PRIVILEGES']['addBonusMultiplier']) ?>" class="leglol-numeric-input" min="0" step="0.01">
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_PRIV_SPEND_MULTIPLIER") ?: 'Повышенный коэффициент бонусов, разрешенных к списанию' ?></td>
        <td>
            <input type="number" name="PRIVILEGES[spendBonusMultiplier]" value="<?= htmlspecialcharsbx($arRule['PRIVILEGES']['spendBonusMultiplier']) ?>" class="leglol-numeric-input" min="0" step="0.01">
        </td>
    </tr>
    <?php
    $tabControl->Buttons([
        "btnSave" => true, "btnApply" => true, "btnCancel" => true,
        "back_url" => "program_level.php?lang=" . LANG
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
