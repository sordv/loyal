/**
 * BX.calendar для условия «Дата регистрации» в core_condtree (поле value в строке с маркером prefix).
 * Страница level_rule_edit подключает логику inline; этот файл — для других админ-страниц (event и т.д.):
 * window.legacyLoyaltyCondTree = { regDateMarker: 'Дата регистрации', regDatePlaceholder: '...' };
 * LegacyLoyaltyCondTreeCalendar.init('EventConditions');
 */
(function () {
    'use strict';

    function getMarker(options) {
        if (options && options.regDateMarker) {
            return String(options.regDateMarker);
        }
        if (window.legacyLoyaltyCondTree && window.legacyLoyaltyCondTree.regDateMarker) {
            return String(window.legacyLoyaltyCondTree.regDateMarker);
        }
        return 'Дата регистрации';
    }

    function ensureCalendarApi(done) {
        if (typeof BX !== 'undefined' && typeof BX.calendar === 'function') {
            done();
            return;
        }
        if (BX.Runtime && typeof BX.Runtime.loadExtension === 'function') {
            BX.Runtime.loadExtension('calendar').then(done).catch(done);
            return;
        }
        if (BX.loadExt) {
            BX.loadExt('calendar').then(done).catch(done);
            return;
        }
        setTimeout(function () { ensureCalendarApi(done); }, 50);
    }

    function findRegDateValueInputs(root, marker) {
        var out = [];
        if (!root || !marker) {
            return out;
        }
        var list = root.querySelectorAll('input[type="text"], input:not([type])');
        for (var i = 0; i < list.length; i++) {
            var inp = list[i];
            var name = inp.name || '';
            if (name.indexOf('value') === -1) {
                continue;
            }
            var scope = inp.closest('.sale-cond-control-cont')
                || inp.closest('.sale-cond-tree-view-control-wrap')
                || inp.closest('.sale-cond-tree-view-item')
                || inp.closest('tr')
                || inp.parentElement;
            if (!scope || scope === root) {
                continue;
            }
            var blob = scope.textContent || '';
            if (blob.indexOf(marker) === -1) {
                continue;
            }
            out.push(inp);
        }
        return out;
    }

    function bindOne(inp) {
        if (!inp || inp.__legacyLoyaltyCalBound) {
            return;
        }
        inp.__legacyLoyaltyCalBound = true;
        inp.setAttribute('readonly', 'readonly');
        inp.setAttribute('autocomplete', 'off');
        inp.style.cursor = 'pointer';
        if (inp.classList) {
            inp.classList.add('leglol-condtree-regdate');
        }
        BX.bind(inp, 'click', function (e) {
            e.preventDefault();
            ensureCalendarApi(function () {
                if (typeof BX.calendar !== 'function') {
                    return;
                }
                BX.calendar({
                    node: inp,
                    field: inp,
                    bTime: false,
                    callback_after: function () {
                        if (typeof BX.fireEvent === 'function') {
                            BX.fireEvent(inp, 'change');
                        }
                    }
                });
            });
        });
    }

    function bindAll(rootEl, marker) {
        var inputs = findRegDateValueInputs(rootEl, marker);
        for (var j = 0; j < inputs.length; j++) {
            bindOne(inputs[j]);
        }
    }

    window.LegacyLoyaltyCondTreeCalendar = window.LegacyLoyaltyCondTreeCalendar || {};
    window.LegacyLoyaltyCondTreeCalendar.init = function (containerId, options) {
        var marker = getMarker(options);
        ensureCalendarApi(function () {
            var el = BX(containerId);
            if (!el) {
                return;
            }
            bindAll(el, marker);
            if (!el.__legacyLoyaltyCalObs && typeof MutationObserver !== 'undefined') {
                var obs = new MutationObserver(function () {
                    bindAll(el, marker);
                });
                obs.observe(el, { childList: true, subtree: true });
                el.__legacyLoyaltyCalObs = obs;
            }
            setTimeout(function () { bindAll(el, marker); }, 0);
            setTimeout(function () { bindAll(el, marker); }, 250);
            setTimeout(function () { bindAll(el, marker); }, 700);
        });
    };
})();
