<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Legacy\Loyalty\Tables\LevelRuleTable;
use Legacy\Loyalty\Service\LevelBulkSyncService;
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
    $message = [
        "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_LEVEL_COND_PARSE_ERROR"),
        "TYPE" => "WARNING",
        "DETAILS" => Loc::getMessage("LEGACY_LOYALTY_LEVEL_COND_PARSE_ERROR_DETAILS"),
    ];
}

if ($request->isPost() && check_bitrix_sessid()) {
    $conditionsToSave = [];
    $parseError = null;
    $raw = $request->getPost('levelRuleCond');

    // Валидация: дата регистрации должна быть в формате ДД.ММ.ГГГГ (если заполнена).
    if (is_array($raw)) {
        foreach ($raw as $cond) {
            if (!is_array($cond)) {
                continue;
            }
            if (($cond['controlId'] ?? null) !== 'registrationDate') {
                continue;
            }
            $value = trim((string)($cond['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            $ts = MakeTimeStamp($value, 'DD.MM.YYYY');
            // строго: не даём сохранять "мусор" вроде 43.13.2026
            if ($ts === false) {
                $message = [
                    "MESSAGE" => "Некорректная дата в условии «Дата регистрации».",
                    "TYPE" => "ERROR",
                    "DETAILS" => "Введите дату в формате ДД.ММ.ГГГГ, например 31.12.2025.",
                ];
                // Прерываем сохранение
                $raw = null;
                break;
            }
        }
    }

    if (!is_array($raw)) {
        // invalid date or no conditions posted; don't proceed with saving
    } else {
    $conditionsToSave = is_array($raw) ? LoyaltyConditions::saveConditions($raw) : ($arRule['CONDITIONS'] ?? []);

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

        LevelBulkSyncService::syncAllRegisteredUsers();

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
}

$APPLICATION->SetTitle($ID > 0 ? Loc::getMessage("LEGACY_LOYALTY_LEVEL_EDIT_RULE") : Loc::getMessage("LEGACY_LOYALTY_LEVEL_ADD_RULE")
);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if ($message) {
    if ($message instanceof CAdminMessage) {
        $message->Show();
    } else {
        CAdminMessage::ShowMessage($message);
    }
}

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

if (!empty($arRule['CONDITIONS']) && is_array($arRule['CONDITIONS'])) {
    $arRule['CONDITIONS'] = UserConditions::prepareConditionsForEditor($arRule['CONDITIONS']);
}

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
            <input type="number" name="SORT" value="<?= (int)$arRule['SORT'] ?>" class="ll-numeric-input">
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
            CJSCore::Init(['core_condtree', 'core_userselector', 'core_date', 'popup', 'calendar']);
            $userTree = !empty($arRule['CONDITIONS'])
                ? \Bitrix\Main\Web\Json::encode($arRule['CONDITIONS'])
                : UserConditions::baseConditions('json');
            $regDateMarker = 'Дата регистрации';
            ?>
            <div id="UserConditions" class="ll-condition-builder"></div>
            <script>
                (function () {
                    BX.ready(function () {
                        new BX.TreeConditions(
                            <?=UserConditions::mainParams('json')?>,
                            <?=$userTree?>,
                            <?=UserConditions::controls('json')?>
                        );
                    });
                })();
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
            <input type="number" name="PRIVILEGES[cartDiscountPercent]" value="<?= htmlspecialcharsbx($arRule['PRIVILEGES']['cartDiscountPercent']) ?>" class="ll-numeric-input" min="0" max="100" step="0.01"> %
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_PRIV_DELIVERY_DISCOUNT") ?: 'Скидка на доставку %' ?></td>
        <td>
            <input type="number" name="PRIVILEGES[deliveryDiscountPercent]" value="<?= htmlspecialcharsbx($arRule['PRIVILEGES']['deliveryDiscountPercent']) ?>" class="ll-numeric-input" min="0" max="100" step="0.01"> %
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_PRIV_ADD_MULTIPLIER") ?: 'Повышенный коэффициент начисляемых бонусов' ?></td>
        <td>
            <input type="number" name="PRIVILEGES[addBonusMultiplier]" value="<?= htmlspecialcharsbx($arRule['PRIVILEGES']['addBonusMultiplier']) ?>" class="ll-numeric-input" min="0" step="0.01">
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_PRIV_SPEND_MULTIPLIER") ?: 'Повышенный коэффициент бонусов, разрешенных к списанию' ?></td>
        <td>
            <input type="number" name="PRIVILEGES[spendBonusMultiplier]" value="<?= htmlspecialcharsbx($arRule['PRIVILEGES']['spendBonusMultiplier']) ?>" class="ll-numeric-input" min="0" step="0.01">
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
    .ll-numeric-input {
        width: 120px;
    }

    .ll-condition-builder {
        position: relative;
        z-index: 10;
        min-height: 100px;
    }

    .sale-cond-tree-view, .sale-cond-control-cont {
        margin:0 !important; margin-bottom:8px !important;
    }
</style>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
