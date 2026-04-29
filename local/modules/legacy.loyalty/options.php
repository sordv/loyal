<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;

Loc::loadMessages(__FILE__);

$moduleId = 'legacy.loyalty';

if (!$USER->IsAdmin()) {
    return;
}

$request = Context::getCurrent()->getRequest();

$options = [
    'integration_show_cart_bonus' => [
        'label' => Loc::getMessage('LEGACY_LOYALTY_OPT_INTEGRATION_SHOW_CART_BONUS'),
        'default' => 'Y',
    ],
    'integration_show_cart_item_bonus' => [
        'label' => Loc::getMessage('LEGACY_LOYALTY_OPT_SHOW_CART_ITEM_BONUS'),
        'default' => 'Y',
    ],
    'integration_show_cart_order_bonus' => [
        'label' => Loc::getMessage('LEGACY_LOYALTY_OPT_SHOW_CART_ORDER_BONUS'),
        'default' => 'Y',
    ],
    'integration_show_cart_spend_bonus' => [
        'label' => Loc::getMessage('LEGACY_LOYALTY_OPT_SHOW_CART_SPEND_BONUS'),
        'default' => 'Y',
    ],
    'bonus_name' => [
        'label' => Loc::getMessage('LEGACY_LOYALTY_OPT_BONUS_NAME'),
        'default' => Loc::getMessage('LEGACY_LOYALTY_OPT_BONUS_NAME_DEFAULT'),
        'type' => 'text',
    ],
];

if ($request->isPost() && check_bitrix_sessid()) {
    foreach ($options as $name => $option) {
        if (($option['type'] ?? 'checkbox') === 'checkbox') {
            Option::set($moduleId, $name, $request->getPost($name) === 'Y' ? 'Y' : 'N');
        } else {
            Option::set($moduleId, $name, trim((string)$request->getPost($name)));
        }
    }

    CAdminMessage::ShowMessage([
        'MESSAGE' => Loc::getMessage('LEGACY_LOYALTY_OPT_SAVED'),
        'TYPE' => 'OK',
    ]);
}

$tabControl = new CAdminTabControl('legacy_loyalty_options', [
    [
        'DIV' => 'integration',
        'TAB' => Loc::getMessage('LEGACY_LOYALTY_OPT_TAB_INTEGRATION'),
        'TITLE' => Loc::getMessage('LEGACY_LOYALTY_OPT_TAB_INTEGRATION_TITLE'),
    ],
]);
?>

<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <?php
    $tabControl->Begin();
    $tabControl->BeginNextTab();
    ?>

    <tr class="heading">
        <td colspan="2"><?= Loc::getMessage('LEGACY_LOYALTY_OPT_CART_HEADING') ?></td>
    </tr>

    <?php foreach ($options as $name => $option): ?>
        <?php $type = $option['type'] ?? 'checkbox'; ?>
        <tr>
            <td width="40%"><?= htmlspecialcharsbx($option['label']) ?></td>
            <td width="60%">
                <?php if ($type === 'checkbox'): ?>
                    <input
                        type="checkbox"
                        name="<?= htmlspecialcharsbx($name) ?>"
                        value="Y"
                        <?= Option::get($moduleId, $name, $option['default']) === 'Y' ? 'checked' : '' ?>
                    >
                <?php else: ?>
                    <input
                        type="text"
                        name="<?= htmlspecialcharsbx($name) ?>"
                        value="<?= htmlspecialcharsbx(Option::get($moduleId, $name, $option['default'])) ?>"
                        size="40"
                    >
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>

    <?php
    $tabControl->Buttons();
    ?>
    <input type="submit" name="save" value="<?= Loc::getMessage('LEGACY_LOYALTY_OPT_SAVE') ?>" class="adm-btn-save">
    <?php
    $tabControl->End();
    ?>
</form>
