<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Legacy\Loyalty\RuleBuilder\LevelRuleTable;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
Loc::loadMessages($_SERVER["DOCUMENT_ROOT"]."/local/modules/legacy.loyalty/admin/level_rule_edit.php");

if (!Loader::includeModule('legacy.loyalty') || !Loader::includeModule('sale')) {
    die(Loc::getMessage("LEGACY_LOYALTY_MODULE_NOT_INSTALLED"));
}

$request = Application::getInstance()->getContext()->getRequest();
$ID = (int)$request->get("ID");
$arRule = $ID > 0 ? LevelRuleTable::getById($ID)->fetch() : [];

if (empty($arRule)) {
    $arRule['ACTIVE'] = 'Y';
    $arRule['SORT'] = 100;
    $arRule['NAME'] = '';
    $arRule['PERIOD'] = 0;
    $arRule['CONDITIONS'] = [];
    $arRule['PRIVILEGES'] = [];
}

$message = null;

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
                'FILTER' => ['USER'],
            ]
        ]
    );

    if ($boolCondParse) {
        $parsedConditions = $obCondParse->Parse();

        if (is_array($parsedConditions)) {
            $conditionsToSave = $parsedConditions;
        } else {
            $parseError = Loc::getMessage("LEGACY_LOYALTY_LEVEL_COND_PARSE_ERROR");
            $conditionsToSave = $arRule['CONDITIONS'] ?? [];
        }
    } else {
        $parseError = Loc::getMessage("LEGACY_LOYALTY_LEVEL_COND_INIT_ERROR");
        $conditionsToSave = $arRule['CONDITIONS'] ?? [];
    }

    $arFields = [
        "ACTIVE" => $request->getPost("ACTIVE") === "Y" ? "Y" : "N",
        "SORT" => (int)$request->getPost("SORT"),
        "NAME" => trim($request->getPost("NAME")),
        "PERIOD" => (int)$request->getPost("PERIOD"),
        "CONDITIONS" => $conditionsToSave,
        "PRIVILEGES" => [],
    ];

    // Валидация обязательных полей
    if (empty($arFields['NAME'])) {
        $message = new CAdminMessage([
            "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_LEVEL_NAME_REQUIRED"),
            "TYPE" => "ERROR"
        ]);
    } else {
        $res = $ID > 0
            ? LevelRuleTable::update($ID, $arFields)
            : LevelRuleTable::add($arFields);

        if ($res->isSuccess() && !$ID) {
            $ID = $res->getId();
        }

        if ($res->isSuccess()) {
            $redirectParams = "?lang=" . LANG;
            if ($parseError) {
                $redirectParams .= "&cond_error=1";
            }

            $baseUrl = $request->getPost("apply")
                ? "level_rule_edit.php?ID=" . $ID
                : "program_level.php";

            LocalRedirect($baseUrl . $redirectParams);
        } else {
            $message = new CAdminMessage(["MESSAGE" => implode("<br>", $res->getErrorMessages()), "TYPE" => "ERROR"]);
        }
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

if (!defined('BT_COND_MODE_DEFAULT')) define('BT_COND_MODE_DEFAULT', 0);
if (!defined('BT_COND_BUILD_USER')) define('BT_COND_BUILD_USER', 'user');

$arCondParams = [
    'FORM_NAME' => 'form_edit',
    'CONT_ID' => 'condition-tree',
    'JS_NAME' => 'JSLoyaltyLevelCond',
    'INIT_CONTROLS' => ['SITE_ID' => SITE_ID, 'FILTER' => ['USER']],
];

$obCond = new CSaleCondTree();
$boolCond = $obCond->Init(BT_COND_MODE_DEFAULT, BT_COND_BUILD_USER, $arCondParams);
?>

<form method="POST" action="<?= $APPLICATION->GetCurPageParam() ?>" name="form_edit" id="form_edit">
    <?=bitrix_sessid_post()?>
    <input type="hidden" name="ID" value="<?= $ID ?>">

    <?php $tabControl->Begin(); $tabControl->BeginNextTab(); ?>

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

    <!-- Название уровня -->
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_NAME") ?> <span style="color:#c00">*</span></td>
        <td>
            <input type="text" name="NAME" value="<?= htmlspecialcharsbx($arRule['NAME']) ?>" style="width:100%;max-width:400px;">
        </td>
    </tr>

    <!-- Период оценки -->
    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_PERIOD") ?></td>
        <td>
            <input type="number" name="PERIOD" value="<?= (int)$arRule['PERIOD'] ?>" class="leglol-numeric-input" min="0">
            <br><small style="color:#666"><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_PERIOD_HINT") ?></small>
        </td>
    </tr>

    <!-- Заголовок условий -->
    <tr class="heading">
        <td colspan="2"><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_CONDITIONS") ?></td>
    </tr>

    <!-- Конструктор условий -->
    <tr>
        <td colspan="2">
            <?php if ($boolCond): ?>
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
                <div style="color:#c00;background:#ffebee;padding:12px;border-radius:4px;">
                    ❌ <?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_COND_INIT_ERROR") ?>
                </div>
            <?php endif; ?>
        </td>
    </tr>

    <!-- Заглушка для наград (по ТЗ пока пусто) -->
    <tr class="heading">
        <td colspan="2"><?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_PRIVILEGES") ?></td>
    </tr>
    <tr>
        <td colspan="2">
            <div style="color:#666;background:#f9f9f9;padding:12px;border-radius:4px;">
                <?= Loc::getMessage("LEGACY_LOYALTY_LEVEL_PRIVILEGES_PLACEHOLDER") ?>
            </div>
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