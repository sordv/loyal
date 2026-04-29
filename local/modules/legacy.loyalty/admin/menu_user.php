<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;
use Legacy\Loyalty\Service\BonusService;
use Legacy\Loyalty\Service\LevelService;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

Loc::loadMessages($_SERVER["DOCUMENT_ROOT"]."/local/modules/legacy.loyalty/admin/menu_user.php");

$APPLICATION->SetTitle(Loc::getMessage("LEGACY_LOYALTY_MENU_USER"));

if (!Loader::includeModule('legacy.loyalty')) {
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
    echo Loc::getMessage("LEGACY_LOYALTY_MODULE_NOT_INSTALLED");
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    return;
}

$connection = Application::getConnection();
$request = Application::getInstance()->getContext()->getRequest();

// ======================
// ACTIONS
// ======================
if ($request->isPost() && check_bitrix_sessid()) {
    $userId = (int)$request->getPost("user_id");

    if ($request->getPost("action") === "bonus") {
        $amount = (int)$request->getPost("amount");
        $type = $request->getPost("type");

        if ($type === "add") {
            BonusService::addBonusByAdmin($userId, $amount);
        } else {
            BonusService::spendBonusByAdmin($userId, $amount);
        }
    }

    if ($request->getPost("action") === "level") {
        $levelId = (int)$request->getPost("level_id");
        LevelService::setLevelByAdmin($userId, $levelId);
    }
}

// ======================
// ДАННЫЕ
// ======================
$usersResult = UserTable::getList([
    'select' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL'],
    'order' => ['ID' => 'ASC'],
]);

$levels = LevelService::getAllLevels();

// ======================
// UI
// ======================
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

\Bitrix\Main\UI\Extension::load(['ui.dialogs.messagebox', 'ui.buttons', 'ui.forms']);

$APPLICATION->AddHeadString('<script>
BX.message({
    BONUS: "'.Loc::getMessage("LEGACY_LOYALTY_USER_POPUP_BONUS").'",
    LEVEL: "'.Loc::getMessage("LEGACY_LOYALTY_USER_POPUP_LEVEL").'",
    EVENT: "'.Loc::getMessage("LEGACY_LOYALTY_USER_POPUP_EVENT").'",
    OK: "'.Loc::getMessage("LEGACY_LOYALTY_USER_POPUP_OK").'",
    INVALID: "'.Loc::getMessage("LEGACY_LOYALTY_USER_POPUP_INVALID").'",
    ADD: "'.Loc::getMessage("LEGACY_LOYALTY_USER_POPUP_ADD").'",
    SPEND: "'.Loc::getMessage("LEGACY_LOYALTY_USER_POPUP_SPEND").'",
    AMOUNT: "'.htmlspecialcharsbx(Loc::getMessage("LEGACY_LOYALTY_USER_POPUP_AMOUNT")).'"
});
</script>', true);

$sTableID = "tbl_loyalty_users";
$oSort = new CAdminSorting($sTableID, "ID", "ASC");
$lAdmin = new CAdminList($sTableID, $oSort);
$lAdmin->AddHeaders([
        ["id" => "ID", "content" => Loc::getMessage("LEGACY_LOYALTY_USER_TABLE_ID"), "default" => true],
        ["id" => "NAME", "content" => Loc::getMessage("LEGACY_LOYALTY_USER_TABLE_NAME"), "default" => true],
        ["id" => "EMAIL", "content" => Loc::getMessage("LEGACY_LOYALTY_USER_TABLE_EMAIL"), "default" => true],
        ["id" => "BONUS", "content" => Loc::getMessage("LEGACY_LOYALTY_USER_TABLE_BONUS"), "default" => true],
        ["id" => "LEVEL", "content" => Loc::getMessage("LEGACY_LOYALTY_USER_TABLE_LEVEL"), "default" => true],
        ["id" => "EVENT", "content" => Loc::getMessage("LEGACY_LOYALTY_USER_TABLE_EVENT"), "default" => true],
]);

while ($user = $usersResult->fetch()) {
    $userId = (int)$user['ID'];

    $balance = BonusService::getBalance($userId);
    $available = $balance['available'];
    $pending = $balance['pending'];

    $currentLevel = LevelService::getLevel($userId);
    $levelId = $currentLevel ? $currentLevel['ID'] : 0;
    $levelName = $currentLevel ? htmlspecialcharsbx($currentLevel['NAME']) : '-';

    $row = &$lAdmin->AddRow($userId, $user);

    $row->AddField("ID", $userId);
    $row->AddField("NAME", htmlspecialcharsbx($user['NAME']." ".$user['LAST_NAME']));
    $row->AddField("EMAIL", htmlspecialcharsbx($user['EMAIL']));

    $bonusDisplay = $available;
    if ($pending > 0) {
        $bonusDisplay .= "<span> (+{$pending})</span>";
    }

    $row->AddField("BONUS", $bonusDisplay .
            ' <button type="button" class="leglol-open-popup" onclick="openBonusPopup('.$userId.')" title="'.Loc::getMessage("LEGACY_LOYALTY_USER_TABLE_EDIT").'">✏️</button>'
    );
    $row->AddField("LEVEL", $levelName .
            ' <button type="button" class="leglol-open-popup" onclick="openLevelPopup('.$userId.', '.$levelId.')" title="'.Loc::getMessage("LEGACY_LOYALTY_USER_TABLE_EDIT").'">✏️</button>'
    );
    $row->AddField("EVENT",
            '<button type="button" class="leglol-open-popup" onclick="openRewardPopup('.$userId.')" title="'.Loc::getMessage("LEGACY_LOYALTY_USER_TABLE_AWARD").'">🎁</button>'
    );
}

$lAdmin->AddAdminContextMenu([]);
$lAdmin->DisplayList();
?>

<script>
function openBonusPopup(userId) {
    BX.UI.Dialogs.MessageBox.show({
        title: BX.message('BONUS'),
        message: `
            <form id="bonusForm">
                <input type="hidden" name="action" value="bonus">
                <input type="hidden" name="user_id" value="${userId}">
                <input type="hidden" name="type" id="bonusTypeInput" value="add">
                <input type="hidden" name="sessid" value="<?=bitrix_sessid()?>">

                <div>
                    <span id="bonusTypeToggle" class="leglol-bonus-popup-switch">
                        ${BX.message('ADD')}
                    </span>
                </div>
                <input type="number" name="amount" placeholder="${BX.message('AMOUNT')}" class="leglol-chooser" required min="1">
            </form>
        `,
        buttons: BX.UI.Dialogs.MessageBoxButtons.OK,
        okCaption: BX.message('OK'),
        onOk: function() {
            var form = document.getElementById('bonusForm');
            var amount = form.querySelector('[name="amount"]').value;
            if (!amount || amount <= 0) {
                alert(BX.message('INVALID'));
                return false;
            }
            BX.ajax({
                method: 'POST',
                url: window.location.href,
                data: BX.ajax.prepareData({
                    action: 'bonus',
                    user_id: userId,
                    type: document.getElementById('bonusTypeInput').value,
                    amount: amount,
                    sessid: '<?=bitrix_sessid()?>'
                }),
                onsuccess: function() {
                    location.reload();
                }
            });
            return false;
        }
    });

    setTimeout(function() {
        var toggle = document.getElementById('bonusTypeToggle');
        var input = document.getElementById('bonusTypeInput');
        if (toggle && input) {
            toggle.onclick = function() {
                if (input.value === 'add') {
                    input.value = 'spend';
                    toggle.textContent = BX.message('SPEND');
                    toggle.style.color = '#c00';
                } else {
                    input.value = 'add';
                    toggle.textContent = BX.message('ADD');
                    toggle.style.color = '#2f80ed';
                }
            };
        }
    }, 100);
}

function openLevelPopup(userId, currentLevelId) {
    var levels = <?=CUtil::PhpToJSObject(LevelService::getAllLevels())?>;
    var options = levels.map(function(lvl)
    {
        var selected = (lvl.ID == currentLevelId) ? ' selected' : '';
        return '<option value="' + lvl.ID + '"' + selected + '>' + lvl.NAME + '</option>';
    }).join('');

    BX.UI.Dialogs.MessageBox.show({
        title: BX.message('LEVEL'),
        message: `
            <form method="post">
                <input type="hidden" name="action" value="level">
                <input type="hidden" name="user_id" value="${userId}">
                <select name="level_id" class="leglol-chooser">
                    ${options}
                </select>
                <div><?=bitrix_sessid_post()?></div>
            </form>
        `,
        buttons: BX.UI.Dialogs.MessageBoxButtons.OK,
        okCaption: BX.message('OK'),
        onOk: function() {
            var select = document.querySelector('select[name="level_id"]');
            var levelId = select ? select.value : '';

            if (!levelId || levelId == '') {
                alert(BX.message('INVALID'));
                return false;
            }
            BX.ajax({
                method: 'POST',
                url: window.location.href,
                data: BX.ajax.prepareData({
                    action: 'level',
                    user_id: userId,
                    level_id: levelId,
                    sessid: '<?=bitrix_sessid()?>'
                }),
                onsuccess: function() {
                    location.reload();
                }
            });
            return false;
        }
    });
}

function openRewardPopup(userId) {
    BX.UI.Dialogs.MessageBox.show({
        title: BX.message('EVENT'),
        message: `
            <p>Пока не релализовано</p>
            <input type="hidden" name="sessid" value="<?=bitrix_sessid()?>">
        `,
        buttons: BX.UI.Dialogs.MessageBoxButtons.OK,
        okCaption: 'Ок'
    });
}

</script>

<style>
    .leglol-bonus-popup-switch {
        cursor: pointer;
        color: #2f80ed;
        text-decoration: underline;
    }

    .leglol-chooser{
        width: 100%;
    }

    .leglol-open-popup {
        cursor: pointer;
    }
</style>
<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");