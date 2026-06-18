<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Legacy\Loyalty\Tables\EventRuleTable;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
Loc::loadMessages($_SERVER["DOCUMENT_ROOT"]."/local/modules/legacy.loyalty/admin/event_rule_edit.php");

if (!Loader::includeModule('legacy.loyalty')) {
    die(Loc::getMessage("LEGACY_LOYALTY_MODULE_NOT_INSTALLED") ?: 'Модуль legacy.loyalty не установлен');
}

$request = Application::getInstance()->getContext()->getRequest();
$ID = (int)$request->get("ID");
$arRule = $ID > 0 ? EventRuleTable::getById($ID)->fetch() : [];

if (empty($arRule)) {
    $arRule = [
        'ACTIVE' => 'Y',
        'SORT' => 100,
        'NAME' => '',
        'TYPE' => 'date',
        'VALUE' => '',
        'FIRE_MODE' => 'Once',
        'FIRE_DAYS' => 0,
        'CONDITIONS' => [],
        'PRIVILEGES' => ['bonus' => 0],
    ];
}

$message = null;

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
if (!isset($arRule['PRIVILEGES']['bonus'])) {
    $arRule['PRIVILEGES']['bonus'] = 0;
}

if ($request->isPost() && check_bitrix_sessid()) {
    $ruleType = (string)$request->getPost('TYPE');
    if ($ruleType !== 'day') {
        $ruleType = 'date';
    }

    $fireMode = (string)$request->getPost('FIRE_MODE');
    if ($fireMode !== 'Days' && $fireMode !== 'Every') {
        $fireMode = 'Once';
    }
    $fireDays = 0;
    if ($fireMode === 'Days') {
        $fireDays = max(1, (int)$request->getPost('FIRE_DAYS'));
    }

    $conditions = [];
    if ($ruleType === 'date') {
        $rawDate = trim((string)$request->getPost('COND_DATE'));
        if (!preg_match('/^(\d{1,2})\.(\d{1,2})$/', $rawDate, $m)) {
            $message = [
                "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_EVENT_ERR_DATE") ?: "Некорректная дата.",
                "TYPE" => "ERROR",
                "DETAILS" => Loc::getMessage("LEGACY_LOYALTY_EVENT_ERR_DATE_DETAILS") ?: "Введите дату в формате ДД.ММ, например 01.01",
            ];
        } else {
            $d = (int)$m[1];
            $mo = (int)$m[2];
            if (!checkdate($mo, $d, 2000)) {
                $message = [
                    "MESSAGE" => Loc::getMessage("LEGACY_LOYALTY_EVENT_ERR_DATE") ?: "Некорректная дата.",
                    "TYPE" => "ERROR",
                    "DETAILS" => Loc::getMessage("LEGACY_LOYALTY_EVENT_ERR_DATE_DETAILS") ?: "Введите дату в формате ДД.ММ, например 01.01",
                ];
            } else {
            $conditions = [
                'kind' => 'date',
                // Глобальная дата (срабатывает ежегодно): сохраняем только месяц-день.
                'md' => sprintf('%02d-%02d', $mo, $d),
            ];
            }
        }
    } else {
        $dayType = (string)$request->getPost('COND_DAY');
        if ($dayType !== 'birthday' && $dayType !== 'registration') {
            $dayType = 'birthday';
        }
        $conditions = [
            'kind' => 'day',
            'type' => $dayType,
        ];
    }

    if ($message === null) {
        $name = trim((string)$request->getPost('NAME'));
        $sort = (int)($arRule['SORT'] ?? 100);
        $bonus = max(0, (int)$request->getPost('PRIV_BONUS'));

        $nameIsEmpty = ($name === '');
        if ($nameIsEmpty && $ID > 0) {
            $name = (string)$ID;
        }

        $fields = [
            "ACTIVE" => $request->getPost("ACTIVE") === "Y" ? "Y" : "N",
            "SORT" => $sort > 0 ? $sort : 100,
            "NAME" => $name,
            "TYPE" => $ruleType,
            "VALUE" => '',
            "FIRE_MODE" => $fireMode,
            "FIRE_DAYS" => $fireDays,
            "CONDITIONS" => $conditions,
            "PRIVILEGES" => ['bonus' => $bonus],
        ];

        $res = $ID > 0 ? EventRuleTable::update($ID, $fields) : EventRuleTable::add($fields);
        if ($res->isSuccess()) {
            if ($nameIsEmpty && $ID <= 0) {
                $newId = (int)$res->getId();
                EventRuleTable::update($newId, ['NAME' => (string)$newId]);
                $ID = $newId;
            } elseif ($ID <= 0) {
                $ID = (int)$res->getId();
            }

            $baseUrl = $request->getPost("apply")
                ? "event_rule_edit.php?ID=" . $ID
                : "program_event.php";
            $glue = (strpos($baseUrl, '?') !== false) ? '&' : '?';
            LocalRedirect($baseUrl . $glue . "lang=" . LANG);
        } else {
            $message = [
                "MESSAGE" => implode("<br>", $res->getErrorMessages()),
                "TYPE" => "ERROR"
            ];
        }
    }

    // обновим локально для отрисовки формы после ошибки
    $arRule = array_merge($arRule, [
        'ACTIVE' => $request->getPost("ACTIVE") === "Y" ? "Y" : "N",
        'SORT' => (int)($arRule['SORT'] ?? 100),
        'NAME' => (string)$request->getPost('NAME'),
        'TYPE' => $ruleType,
        'VALUE' => '',
        'FIRE_MODE' => $fireMode,
        'FIRE_DAYS' => $fireDays,
        'CONDITIONS' => $conditions ?: ($arRule['CONDITIONS'] ?? []),
        'PRIVILEGES' => ['bonus' => max(0, (int)$request->getPost('PRIV_BONUS'))],
    ]);
}

$APPLICATION->SetTitle($ID > 0
    ? (Loc::getMessage("LEGACY_LOYALTY_EVENT_EDIT_RULE") ?: 'Редактирование правила')
    : (Loc::getMessage("LEGACY_LOYALTY_EVENT_ADD_RULE") ?: 'Добавление правила')
);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if ($message) {
    CAdminMessage::ShowMessage($message);
}

$ruleType = (string)($arRule['TYPE'] ?? 'date');
if ($ruleType !== 'day') {
    $ruleType = 'date';
}
$cond = is_array($arRule['CONDITIONS'] ?? null) ? $arRule['CONDITIONS'] : [];

$condDate = '';
if ($ruleType === 'date') {
    $md = (string)($cond['md'] ?? '');
    if ($md !== '' && preg_match('/^\d{2}-\d{2}$/', $md)) {
        $ts = strtotime('2000-' . $md);
        if ($ts !== false) {
            $condDate = date('d.m', $ts);
        }
    }
}
$condDay = (string)($cond['type'] ?? 'birthday');
if ($condDay !== 'registration') {
    $condDay = 'birthday';
}

$fireMode = (string)($arRule['FIRE_MODE'] ?? 'Once');
if ($fireMode !== 'Days' && $fireMode !== 'Every') {
    $fireMode = 'Once';
}
$fireDays = (int)($arRule['FIRE_DAYS'] ?? 0);
if ($fireMode === 'Days') {
    $fireDays = max(1, $fireDays);
} else {
    $fireDays = 0;
}
?>

<form method="POST" action="<?= $APPLICATION->GetCurPageParam() ?>" name="form_edit" id="form_edit">
    <?=bitrix_sessid_post()?>
    <input type="hidden" name="ID" value="<?= (int)$ID ?>">
    <input type="hidden" name="SORT" value="<?= (int)($arRule['SORT'] ?? 100) ?>">

    <?php
    $aTabs = [
        ["DIV" => "edit_rule", "TAB" => Loc::getMessage("LEGACY_LOYALTY_EVENT_RULE_TAB"), "TITLE" => Loc::getMessage("LEGACY_LOYALTY_EVENT_RULE_TAB")],
    ];
    $tabControl = new CAdminTabControl("tabControl", $aTabs);
    $tabControl->Begin();
    $tabControl->BeginNextTab();
    ?>

    <tr>
        <td width="40%"><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_ACTIVE") ?: 'Активно' ?></td>
        <td width="60%">
            <input type="checkbox" name="ACTIVE" value="Y" <?= (($arRule['ACTIVE'] ?? 'Y') === 'Y' ? 'checked' : '') ?>>
        </td>
    </tr>

    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_NAME") ?: 'Название' ?> <span style="color:#c00">*</span></td>
        <td>
            <input type="text" name="NAME" value="<?= htmlspecialcharsbx((string)($arRule['NAME'] ?? '')) ?>" style="width:100%;max-width:400px;">
        </td>
    </tr>

    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_FIRE_MODE") ?: 'Срабатывание' ?></td>
        <td>
            <select name="FIRE_MODE" id="leglol_event_fire_mode" onchange="leglolToggleEventFireUi()">
                <option value="Once" <?= $fireMode === 'Once' ? 'selected' : '' ?>><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_FIRE_ONCE") ?: 'Только один раз' ?></option>
                <option value="Days" <?= $fireMode === 'Days' ? 'selected' : '' ?>><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_FIRE_DAYS") ?: 'Один раз в N дней' ?></option>
                <option value="Every" <?= $fireMode === 'Every' ? 'selected' : '' ?>><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_FIRE_EVERY") ?: 'Каждый раз при срабатывании правила' ?></option>
            </select>
            <span id="leglol_fire_days_wrap" style="margin-left:12px;">
                N:
                <input type="number" name="FIRE_DAYS" id="leglol_fire_days" value="<?= (int)$fireDays ?>" min="1" style="width:90px;">
            </span>
        </td>
    </tr>

    <tr class="heading">
        <td colspan="2"><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_CONDITIONS") ?: 'Условия' ?></td>
    </tr>

    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_VALUE") ?: 'Тип условия' ?></td>
        <td>
            <select name="TYPE" id="leglol_event_type" onchange="leglolToggleEventConditionUi()">
                <option value="date" <?= $ruleType === 'date' ? 'selected' : '' ?>><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_VALUE_DATE") ?: 'Дата' ?></option>
                <option value="day" <?= $ruleType === 'day' ? 'selected' : '' ?>><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_VALUE_DAY") ?: 'День' ?></option>
            </select>
        </td>
    </tr>

    <tr id="leglol_row_cond_date">
        <td><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_COND_DATE") ?: 'Глобальная дата' ?></td>
        <td>
            <input type="text" name="COND_DATE" value="<?= htmlspecialcharsbx($condDate) ?>" placeholder="ДД.ММ" style="width:120px;">
        </td>
    </tr>

    <tr id="leglol_row_cond_day">
        <td><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_COND_DAY") ?: 'Личная дата' ?></td>
        <td>
            <select name="COND_DAY" style="min-width:220px;">
                <option value="birthday" <?= $condDay === 'birthday' ? 'selected' : '' ?>><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_DAY_BIRTHDAY") ?: 'День рождения' ?></option>
                <option value="registration" <?= $condDay === 'registration' ? 'selected' : '' ?>><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_DAY_REGISTRATION") ?: 'День регистрации' ?></option>
            </select>
        </td>
    </tr>

    <tr class="heading">
        <td colspan="2"><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_PRIVILEGES") ?: 'Награда' ?></td>
    </tr>

    <tr>
        <td><?= Loc::getMessage("LEGACY_LOYALTY_EVENT_PRIV_BONUS") ?: 'Бонусов к начислению' ?></td>
        <td>
            <input type="number" name="PRIV_BONUS" value="<?= (int)($arRule['PRIVILEGES']['bonus'] ?? 0) ?>" min="0" style="width:160px;">
        </td>
    </tr>

    <?php
    $tabControl->Buttons([
        "btnSave" => true, "btnApply" => true, "btnCancel" => true,
        "back_url" => "program_event.php?lang=" . LANG
    ]);
    $tabControl->End();
    ?>
</form>

<script>
    function leglolToggleEventConditionUi() {
        var v = document.getElementById('leglol_event_type');
        var ruleType = v ? v.value : 'date';
        var rowDate = document.getElementById('leglol_row_cond_date');
        var rowDay = document.getElementById('leglol_row_cond_day');
        if (rowDate) rowDate.style.display = (ruleType === 'date') ? '' : 'none';
        if (rowDay) rowDay.style.display = (ruleType === 'day') ? '' : 'none';
    }
    leglolToggleEventConditionUi();

    function leglolToggleEventFireUi() {
        var m = document.getElementById('leglol_event_fire_mode');
        var wrap = document.getElementById('leglol_fire_days_wrap');
        var input = document.getElementById('leglol_fire_days');
        var mode = m ? m.value : 'Once';
        var show = (mode === 'Days');
        if (wrap) wrap.style.display = show ? '' : 'none';
        if (input) input.disabled = !show;
    }
    leglolToggleEventFireUi();
</script>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");

