<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$this->addExternalCss($templateFolder . '/style.css');
$bonusName = htmlspecialcharsbx($arResult['BONUS_NAME']);
$containerId = 'legacy-loyalty-cart-bonus-' . mt_rand(100000, 999999);
$propIdsJson = htmlspecialcharsbx(\Bitrix\Main\Web\Json::encode($arResult['PAYMENT_BONUS_PROP_IDS'] ?? []));
?>

<div class="legacy-loyalty-cart-bonus" id="<?= htmlspecialcharsbx($containerId) ?>" data-prop-ids="<?= $propIdsJson ?>">
    <div class="legacy-loyalty-cart-bonus__header">
        <div class="legacy-loyalty-cart-bonus__title"><?= $arResult['BONUS_ENABLED'] ? 'Бонусы по корзине' : 'Уровень пользователя' ?></div>
    </div>

    <?php if ($arResult['LEVEL_ENABLED']): ?>
        <div class="legacy-loyalty-cart-bonus__section legacy-loyalty-cart-bonus__level">
            <div class="legacy-loyalty-cart-bonus__section-title">Уровень</div>
            <?php if (!empty($arResult['LEVEL'])): ?>
                <div class="legacy-loyalty-cart-bonus__summary">
                    <span><?= htmlspecialcharsbx($arResult['LEVEL']['NAME'] ?: ('Уровень #' . (int)$arResult['LEVEL']['ID'])) ?></span>
                    <strong>#<?= (int)$arResult['LEVEL']['ID'] ?></strong>
                </div>
            <?php else: ?>
                <div class="legacy-loyalty-cart-bonus__empty">Без уровня</div>
            <?php endif; ?>
            <?php $privileges = $arResult['LEVEL_PRIVILEGES'] ?? []; ?>
            <?php if (!empty($privileges)): ?>
                <div class="legacy-loyalty-cart-bonus__privileges">
                    <?php if ((float)$privileges['cartDiscountPercent'] > 0): ?><div>Скидка на корзину: <strong><?= (float)$privileges['cartDiscountPercent'] ?>%</strong></div><?php endif; ?>
                    <?php if ((float)$privileges['deliveryDiscountPercent'] > 0): ?><div>Скидка на доставку: <strong><?= (float)$privileges['deliveryDiscountPercent'] ?>%</strong></div><?php endif; ?>
                    <?php if ($arResult['BONUS_ENABLED'] && (float)$privileges['addBonusMultiplier'] !== 1.0): ?><div>Коэффициент начисления: <strong>x<?= (float)$privileges['addBonusMultiplier'] ?></strong></div><?php endif; ?>
                    <?php if ($arResult['BONUS_ENABLED'] && (float)$privileges['spendBonusMultiplier'] !== 1.0): ?><div>Коэффициент списания: <strong>x<?= (float)$privileges['spendBonusMultiplier'] ?></strong></div><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($arResult['BONUS_ENABLED'] && $arResult['SHOW_ORDER']): ?>
        <div class="legacy-loyalty-cart-bonus__summary">
            <span>За этот заказ будет начислено</span>
            <strong><?= (int)$arResult['ADD']['amount'] ?> <?= $bonusName ?></strong>
        </div>
    <?php endif; ?>

    <?php if ($arResult['BONUS_ENABLED'] && $arResult['SHOW_SPEND']): ?>
        <form class="legacy-loyalty-cart-bonus__spend" method="get" onsubmit="return false;">
            <input type="hidden" name="LEGACY_LOYALTY_SPEND" value="<?= (int)$arResult['REQUESTED_SPEND'] ?>" data-role="legacy-loyalty-spend-hidden">
            <input type="hidden" name="LEGACY_LOYALTY_SPEND_ACCEPTED" value="<?= (int)$arResult['ACCEPTED_SPEND'] ?>" data-role="legacy-loyalty-spend-accepted-hidden">
            <div class="legacy-loyalty-cart-bonus__spend-main">
                <label for="legacy_loyalty_spend">Списать бонусы</label>
                <input id="legacy_loyalty_spend" type="number" min="0" max="<?= (int)$arResult['SPEND']['amount'] ?>" name="legacy_loyalty_spend" value="<?= (int)$arResult['REQUESTED_SPEND'] ?>" data-role="legacy-loyalty-spend-input">
                <button type="button" data-role="legacy-loyalty-spend-apply">Списать</button>
            </div>
            <div class="legacy-loyalty-cart-bonus__hint">
                Доступно к списанию: <?= (int)$arResult['SPEND']['amount'] ?> <?= $bonusName ?>.
                Баланс: <?= (int)$arResult['SPEND']['balance']['available'] ?> <?= $bonusName ?>.
                <?php if ((int)$arResult['REQUESTED_SPEND'] > 0): ?>
                    Будет принято: <?= (int)$arResult['ACCEPTED_SPEND'] ?> <?= $bonusName ?>.
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
(function () {
    function initLegacyLoyaltyCartBonus(id) {
        var root = document.getElementById(id);
        if (!root || root.getAttribute('data-initialized') === 'Y') return;
        root.setAttribute('data-initialized', 'Y');

        var spendInput = root.querySelector('[data-role="legacy-loyalty-spend-input"]');
        var spendHidden = root.querySelector('[data-role="legacy-loyalty-spend-hidden"]');
        var spendAcceptedHidden = root.querySelector('[data-role="legacy-loyalty-spend-accepted-hidden"]');
        var spendApply = root.querySelector('[data-role="legacy-loyalty-spend-apply"]');
        var propIds = {};
        try {
            propIds = JSON.parse(root.getAttribute('data-prop-ids') || '{}');
        } catch (e) {
            propIds = {};
        }

        function getOrderForm() {
            if (window.BX && BX.Sale && BX.Sale.OrderAjaxComponent && BX.Sale.OrderAjaxComponent.form) {
                return BX.Sale.OrderAjaxComponent.form;
            }
            var soaForm = document.getElementById('bx-soa-order-form');
            if (soaForm) return soaForm;
            var forms = document.querySelectorAll('form');
            for (var i = 0; i < forms.length; i++) {
                var action = forms[i].getAttribute('action') || '';
                if (action.indexOf('sale.order.ajax') !== -1
                    || forms[i].querySelector('input[name="ORDER_CONFIRM_BUTTON"]')
                    || forms[i].querySelector('button[name="ORDER_CONFIRM_BUTTON"]')) {
                    return forms[i];
                }
            }
            return null;
        }

        function syncSpendToOrderForm() {
            if (!spendInput || !spendHidden || !spendAcceptedHidden) return;
            var value = parseInt(spendInput.value || '0', 10);
            if (isNaN(value) || value < 0) value = 0;
            var maxValue = parseInt(spendInput.getAttribute('max') || '0', 10);
            if (!isNaN(maxValue) && maxValue > 0 && value > maxValue) {
                value = maxValue;
                spendInput.value = String(value);
            }
            spendHidden.value = String(value);
            spendAcceptedHidden.value = String(value);

            var orderForm = getOrderForm();
            if (!orderForm) return;

            function upsertRaw(rawName, val) {
                var f = orderForm.querySelector('input[name="' + rawName + '"]');
                if (!f) {
                    f = document.createElement('input');
                    f.type = 'hidden';
                    f.name = rawName;
                    f.id = rawName;
                    orderForm.appendChild(f);
                }
                f.value = val;
            }

            function upsert(name, val) {
                upsertRaw(name, val);
                upsertRaw('order[' + name + ']', val);
            }

            upsert('LEGACY_LOYALTY_SPEND', String(value));
            upsert('LEGACY_LOYALTY_SPEND_ACCEPTED', String(value));

            var applied = {};
            for (var personTypeId in propIds) {
                if (!Object.prototype.hasOwnProperty.call(propIds, personTypeId)) continue;
                var propId = propIds[personTypeId];
                if (!propId || applied[propId]) continue;
                applied[propId] = true;
                upsert('ORDER_PROP_' + propId, String(value));
            }
        }

        function requestOrderRecalc() {
            if (window.BX && BX.Sale && BX.Sale.OrderAjaxComponent && typeof BX.Sale.OrderAjaxComponent.sendRequest === 'function') {
                BX.Sale.OrderAjaxComponent.sendRequest();
            }
        }

        if (spendInput) {
            spendInput.addEventListener('input', syncSpendToOrderForm);
            spendInput.addEventListener('change', function () {
                syncSpendToOrderForm();
                requestOrderRecalc();
            });
        }

        if (spendApply) {
            spendApply.addEventListener('click', function () {
                syncSpendToOrderForm();
                requestOrderRecalc();
            });
        }

        if (window.BX && BX.Sale && BX.Sale.OrderAjaxComponent && typeof BX.Sale.OrderAjaxComponent.sendRequest === 'function') {
            var originalSendRequest = BX.Sale.OrderAjaxComponent.sendRequest;
            if (!BX.Sale.OrderAjaxComponent.__legacyLoyaltyWrapped) {
                BX.Sale.OrderAjaxComponent.sendRequest = function () {
                    syncSpendToOrderForm();
                    return originalSendRequest.apply(this, arguments);
                };
                BX.Sale.OrderAjaxComponent.__legacyLoyaltyWrapped = true;
            }
        }

        syncSpendToOrderForm();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initLegacyLoyaltyCartBonus('<?= CUtil::JSEscape($containerId) ?>'); });
    } else {
        initLegacyLoyaltyCartBonus('<?= CUtil::JSEscape($containerId) ?>');
    }
})();
</script>
