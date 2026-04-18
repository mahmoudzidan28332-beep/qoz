<?php
declare(strict_types=1);
/**
 * frontend/public/cart.php
 * QOOQZ — Shopping Cart Page  (Production)
 *
 * - Always loads cart from DB for logged-in users.
 * - localStorage used ONLY as UX cache / guest fallback.
 * - entity_id passed explicitly on every API call.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = (int)($ctx['tenant_id'] ?? 1);
$entityId = (int)($ctx['entity_id'] ?? ($_SESSION['pub_active_entity'][$tenantId]['id'] ?? 0));

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('cart.title') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_TYPE']  = 'cart';

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container qz-cart-page">

    <!-- Breadcrumb -->
    <nav class="pub-breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span class="pub-breadcrumb-sep"><i class="bi bi-chevron-right"></i></span>
        <span><?= e(t('cart.title')) ?></span>
    </nav>

    <!-- Page header -->
    <header class="qz-cart-header">
        <h1><?= e(t('cart.title')) ?></h1>
        <span id="cartCountBadge" class="qz-cart-badge" hidden></span>
    </header>

    <!-- Notification bar (errors / warnings) -->
    <div id="cartNotice" class="qz-notice" hidden></div>

    <!-- Main body — JS renders into this -->
    <div id="pubCartBody">
        <div class="qz-loader">
            <span class="qz-spinner"></span>
            <?= e(t('common.loading')) ?>
        </div>
    </div>

</div>

<!-- ── Templates ───────────────────────────────────────────── -->

<!-- Two-column layout -->
<template id="tmplCartLayout">
    <div class="qz-cart-layout">

        <!-- Left: items list -->
        <section class="qz-cart-items" id="cartItemsList" aria-label="Cart items"></section>

        <!-- Right: order summary -->
        <aside class="qz-cart-summary">
            <div class="qz-summary-card qz-card-premium">
                <h2 class="qz-summary-title" style="display: flex; align-items: center; gap: 8px;">
                    <i class="bi bi-receipt"></i> <?= e(t('cart.order_summary')) ?>
                </h2>

                <div class="qz-summary-rows">
                    <div class="qz-summary-row">
                        <span><?= e(t('cart.subtotal')) ?></span>
                        <strong id="cartSubtotal">—</strong>
                    </div>
                    <div class="qz-summary-row qz-summary-shipping">
                        <span><?= e(t('cart.shipping')) ?></span>
                        <span class="qz-muted"><?= e(t('cart.calculated_at_checkout') ?: '—') ?></span>
                    </div>
                    <div class="qz-summary-row qz-summary-total">
                        <span><?= e(t('cart.total')) ?></span>
                        <strong id="cartGrandTotal">—</strong>
                    </div>
                </div>

                <a href="/frontend/public/checkout.php"
                   class="pub-btn pub-btn--primary qz-checkout-btn">
                    <?= e(t('cart.checkout')) ?>
                </a>

                <a href="/frontend/public/products.php"
                   class="pub-btn pub-btn--ghost qz-continue-btn">
                    ← <?= e(t('cart.continue_shopping')) ?>
                </a>
            </div>
        </aside>
    </div>
</template>

<!-- Empty cart -->
<template id="tmplCartEmpty">
    <div class="qz-empty" style="text-align:center; padding: 100px 20px;">
        <div class="qz-empty-icon qz-icon-empty">
            <i class="bi bi-cart-x"></i>
        </div>
        <p class="qz-empty-msg" style="font-weight: 500; font-size: 1.15rem; color: var(--pub-text);"><?= e(t('cart.empty')) ?></p>
        <a href="/frontend/public/products.php" class="pub-btn pub-btn--primary">
            <?= e(t('hero.browse_products')) ?>
        </a>
    </div>
</template>

<style>
/* ────────────────────────────────────────────────────
   Cart Page Styles
──────────────────────────────────────────────────── */

.qz-cart-page { padding-top: 28px; padding-bottom: 60px; }

/* Breadcrumb */
.qz-breadcrumb {
    font-size: .82rem;
    color: var(--pub-muted);
    margin-bottom: 20px;
    display: flex;
    gap: 6px;
    align-items: center;
}
.qz-breadcrumb a { color: var(--pub-muted); text-decoration: none; }
.qz-breadcrumb a:hover { color: var(--pub-primary); }

/* Header */
.qz-cart-header {
    display: flex;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 28px;
}
.qz-cart-header h1 { font-size: 1.5rem; margin: 0; }
.qz-cart-badge {
    background: var(--pub-primary);
    color: #fff;
    border-radius: 20px;
    padding: 2px 10px;
    font-size: .78rem;
    font-weight: 700;
}

/* Notice bar */
.qz-notice {
    padding: 10px 16px;
    border-radius: var(--pub-radius);
    margin-bottom: 16px;
    font-size: .88rem;
    border: 1px solid transparent;
}
.qz-notice--error   { background: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
.qz-notice--warning { background: #fffbeb; color: #92400e; border-color: #fcd34d; }

/* Loader */
.qz-loader {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 60px 0;
    color: var(--pub-muted);
    font-size: .9rem;
}
.qz-spinner {
    width: 20px; height: 20px;
    border: 2px solid var(--pub-border);
    border-top-color: var(--pub-primary);
    border-radius: 50%;
    animation: qzSpin .7s linear infinite;
    display: inline-block;
}
@keyframes qzSpin { to { transform: rotate(360deg); } }

/* Layout grid */
.qz-cart-layout {
    display: grid;
    gap: 24px;
    align-items: start;
}
@media (min-width: 1024px) {
    .qz-cart-layout { grid-template-columns: 1fr 340px; }
}

/* Items list */
.qz-cart-items { display: grid; gap: 12px; }

/* Single item card */
.qz-cart-item {
    background: var(--pub-bg);
    border: 1px solid var(--pub-glass-border);
    border-radius: var(--pub-radius);
    padding: 16px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
    transition: all var(--pub-transition);
    box-shadow: var(--pub-shadow);
}
.qz-cart-item:hover { 
    transform: translateY(-2px);
    box-shadow: var(--pub-shadow-hover);
    border-color: var(--pub-primary);
}

/* Thumbnail */
.qz-item-img {
    width: 80px; height: 80px;
    border-radius: var(--pub-radius-sm);
    overflow: hidden;
    background: var(--pub-surface);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--pub-muted);
    font-size: 1.6rem;
}
.qz-item-img img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
}

/* Item info */
.qz-item-info { flex: 1; min-width: 0; }
.qz-item-name {
    font-size: .92rem;
    font-weight: 600;
    color: var(--pub-text);
    text-decoration: none;
    display: block;
    margin-bottom: 4px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.qz-item-name:hover { color: var(--pub-primary); }
.qz-item-sku { font-size: .76rem; color: var(--pub-muted); }
.qz-item-attrs { font-size: .78rem; color: var(--pub-muted); margin-top: 2px; }

/* Price */
.qz-price-group { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
.qz-price-sale { font-size: .9rem; font-weight: 700; color: var(--pub-danger, #dc2626); }
.qz-price-original { font-size: .8rem; color: var(--pub-muted); text-decoration: line-through; }
.qz-price-regular { font-size: .9rem; font-weight: 700; color: var(--pub-primary); }

/* Actions column */
.qz-item-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
    flex-shrink: 0;
}

/* Qty stepper */
.qz-qty {
    display: flex;
    align-items: center;
    gap: 2px;
    border: 1px solid var(--pub-border);
    border-radius: var(--pub-radius-sm);
    overflow: hidden;
}
.qz-qty-btn {
    width: 30px; height: 30px;
    background: var(--pub-surface);
    border: none;
    cursor: pointer;
    font-size: 1rem;
    color: var(--pub-text);
    transition: background .15s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.qz-qty-btn:hover { background: var(--pub-primary); color: #fff; }
.qz-qty-input {
    width: 44px; height: 30px;
    text-align: center;
    border: none;
    border-left: 1px solid var(--pub-border);
    border-right: 1px solid var(--pub-border);
    background: var(--pub-bg);
    color: var(--pub-text);
    font-size: .88rem;
    -moz-appearance: textfield;
}
.qz-qty-input::-webkit-outer-spin-button,
.qz-qty-input::-webkit-inner-spin-button { -webkit-appearance: none; }

/* Item subtotal */
.qz-item-subtotal {
    font-size: .85rem;
    font-weight: 700;
    color: var(--pub-text);
    white-space: nowrap;
}

/* Remove button */
.qz-remove-btn {
    background: none;
    border: none;
    color: var(--pub-muted);
    cursor: pointer;
    font-size: .8rem;
    padding: 4px 6px;
    border-radius: 4px;
    transition: color .15s, background .15s;
    display: flex;
    align-items: center;
    gap: 4px;
}
.qz-remove-btn:hover { color: var(--pub-danger, #dc2626); background: #fef2f2; }

/* Summary card */
.qz-summary-card {
    background: var(--pub-bg);
    padding: 24px;
    position: sticky;
    top: calc(var(--pub-header-h, 60px) + 20px);
}
.qz-summary-title { font-size: 1rem; font-weight: 700; margin: 0 0 16px; color: var(--pub-text); }
.qz-summary-rows { display: grid; gap: 0; }
.qz-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--pub-border);
    font-size: .9rem;
}
.qz-summary-row:last-child { border-bottom: none; }
.qz-summary-total { font-size: 1rem; font-weight: 800; }
.qz-muted { color: var(--pub-muted); }

.qz-checkout-btn {
    width: 100%;
    text-align: center;
    display: block;
    margin-top: 18px;
    font-size: .95rem;
    padding: 12px;
}
.qz-continue-btn {
    width: 100%;
    text-align: center;
    display: block;
    margin-top: 10px;
    font-size: .84rem;
}

/* Empty state */
.qz-empty { text-align: center; padding: 60px 0; }
.qz-empty-icon { font-size: 3rem; margin-bottom: 12px; }
.qz-empty-msg { font-size: 1.05rem; color: var(--pub-muted); margin-bottom: 20px; }

/* Disabled state during API calls */
.qz-cart-item.is-loading { opacity: .55; pointer-events: none; }
</style>

<script>
(function () {
    'use strict';

    /* ── Constants injected from PHP ────────────────── */
    var TENANT_ID   = <?= (int)$tenantId ?>;
    var ENTITY_ID   = <?= (int)$entityId ?>;   /* resolved server-side */
    var CURRENCY    = <?= json_encode('SAR') ?>;
    var LANG        = <?= json_encode($lang) ?>;
    var i18n = {
        remove_confirm : <?= json_encode(t('cart.remove_confirm') ?: 'Remove this item?') ?>,
        items          : <?= json_encode(t('cart.items') ?: 'items') ?>,
        error_generic  : <?= json_encode(t('common.error') ?: 'Something went wrong. Please try again.') ?>,
    };

    /* Active entity: prefer server-resolved, then pubGetActiveEntityId() helper */
    function activeEntityId() {
        if (ENTITY_ID > 0) return ENTITY_ID;
        if (typeof window.pubGetActiveEntityId === 'function') return window.pubGetActiveEntityId();
        return 0;
    }

    /* ── Utility ────────────────────────────────────── */
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function fmtMoney(n, cur) {
        return parseFloat(n || 0).toFixed(2) + ' ' + esc(cur || CURRENCY);
    }

    function apiUrl(path) {
        return '/api/public/cart/' + path
            + '?tenant_id=' + TENANT_ID
            + '&entity_id=' + activeEntityId()
            + '&_t=' + Date.now();
    }

    function showNotice(msg, type) {
        var el = document.getElementById('cartNotice');
        if (!el) return;
        el.textContent = msg;
        el.className = 'qz-notice qz-notice--' + (type || 'error');
        el.hidden = false;
        setTimeout(function() { el.hidden = true; }, 5000);
    }

    /* ── localStorage helpers (cache + guest fallback) ─ */
    function lsKey() {
        return 'pub_cart_t' + TENANT_ID + '_e' + activeEntityId();
    }
    function lsGet() {
        try { return JSON.parse(localStorage.getItem(lsKey()) || '[]'); }
        catch(e) { return []; }
    }
    function lsSet(items) {
        try { localStorage.setItem(lsKey(), JSON.stringify(items || [])); }
        catch(e) { /* quota */ }
    }
    function lsClear() { try { localStorage.removeItem(lsKey()); } catch(e) {} }

    /* ── State ──────────────────────────────────────── */
    var _items   = [];   /* [{id, name, price, sale_price, qty, sku, image, currency, _db_id}] */
    var _cur     = CURRENCY;

    /* ── Normalise a raw DB item into our display shape ─ */
    function normaliseDbItem(ci) {
        var up  = parseFloat(ci.unit_price) || 0;
        var sp  = ci.sale_price != null ? parseFloat(ci.sale_price) : null;
        return {
            _db_id     : parseInt(ci.id, 10) || 0,
            id         : parseInt(ci.product_id, 10) || 0,
            entity_id  : parseInt(ci.entity_id, 10)  || 0,
            name       : ci.product_name || '',
            sku        : ci.sku || '',
            qty        : Math.max(1, parseInt(ci.quantity, 10) || 1),
            unit_price : up,
            sale_price : (sp !== null && sp < up && sp > 0) ? sp : null,
            image      : ci.image_url || '',
            currency   : ci.currency_code || CURRENCY,
            attrs      : ci.selected_attributes || null,
        };
    }

    /* ── Render ─────────────────────────────────────── */
    function render(items) {
        var body  = document.getElementById('pubCartBody');
        var badge = document.getElementById('cartCountBadge');
        if (!body) return;

        if (!items || items.length === 0) {
            var tmpl = document.getElementById('tmplCartEmpty');
            body.innerHTML = '';
            body.appendChild(tmpl.content.cloneNode(true));
            if (badge) badge.hidden = true;
            return;
        }

        /* Build layout if not already there */
        if (!document.getElementById('cartItemsList')) {
            var lt = document.getElementById('tmplCartLayout');
            body.innerHTML = '';
            body.appendChild(lt.content.cloneNode(true));
        }

        var list = document.getElementById('cartItemsList');
        list.innerHTML = '';

        var totalAmt = 0, totalQty = 0;
        _cur = items[0].currency || CURRENCY;

        items.forEach(function(item, idx) {
            var effective = (item.sale_price !== null && item.sale_price < item.unit_price)
                          ? item.sale_price : item.unit_price;
            var subtotal  = effective * item.qty;
            totalAmt += subtotal;
            totalQty += item.qty;

            /* Price HTML */
            var priceHtml;
            if (item.sale_price !== null && item.sale_price < item.unit_price) {
                priceHtml = '<div class="qz-price-group">'
                    + '<span class="qz-price-sale">'   + fmtMoney(item.sale_price, _cur) + '</span>'
                    + '<span class="qz-price-original">' + fmtMoney(item.unit_price, _cur) + '</span>'
                    + '</div>';
            } else {
                priceHtml = '<div class="qz-price-group">'
                    + '<span class="qz-price-regular">' + fmtMoney(item.unit_price, _cur) + '</span>'
                    + '</div>';
            }

            /* Image */
            var imgHtml = item.image
                ? '<img src="' + esc(item.image) + '" alt="' + esc(item.name) + '" loading="lazy"'
                  + ' onerror="this.style.display=\'none\';this.parentNode.innerHTML=\'<i class=\\\'bi bi-image\\\'></i>\'">'
                : '<i class="bi bi-image"></i>';

            /* Attributes summary */
            var attrsHtml = '';
            if (item.attrs) {
                try {
                    var a = typeof item.attrs === 'string' ? JSON.parse(item.attrs) : item.attrs;
                    if (a && typeof a === 'object') {
                        var parts = Object.entries(a).map(function(kv) {
                            return esc(kv[0]) + ': ' + esc(kv[1]);
                        });
                        if (parts.length) attrsHtml = '<div class="qz-item-attrs">' + parts.join(' · ') + '</div>';
                    }
                } catch(e) {}
            }

            var div = document.createElement('div');
            div.className = 'qz-cart-item';
            div.dataset.idx   = idx;
            div.dataset.dbId  = item._db_id;
            div.dataset.pid   = item.id;
            div.innerHTML =
                '<div class="qz-item-img">' + imgHtml + '</div>'
                + '<div class="qz-item-info">'
                +   '<a href="/frontend/public/product.php?id=' + item.id + '" class="qz-item-name">' + esc(item.name) + '</a>'
                +   (item.sku ? '<div class="qz-item-sku">SKU: ' + esc(item.sku) + '</div>' : '')
                +   attrsHtml
                +   priceHtml
                + '</div>'
                + '<div class="qz-item-actions">'
                +   '<div class="qz-qty">'
                +     '<button class="qz-qty-btn" type="button" data-idx="' + idx + '" data-delta="-1" aria-label="Decrease">−</button>'
                +     '<input type="number" class="qz-qty-input" value="' + item.qty + '" min="1" max="999"'
                +           ' data-idx="' + idx + '" data-db-id="' + item._db_id + '" aria-label="Quantity">'
                +     '<button class="qz-qty-btn" type="button" data-idx="' + idx + '" data-delta="1" aria-label="Increase">+</button>'
                +   '</div>'
                +   '<div class="qz-item-subtotal">' + fmtMoney(subtotal, _cur) + '</div>'
                +   '<button class="qz-remove-btn" type="button" data-idx="' + idx + '" data-db-id="' + item._db_id + '">'
                +     '<i class="bi bi-trash"></i> <?= e(t("cart.remove") ?: "Remove") ?>'
                +   '</button>'
                + '</div>';

            list.appendChild(div);
        });

        /* Summary totals */
        var subEl   = document.getElementById('cartSubtotal');
        var grandEl = document.getElementById('cartGrandTotal');
        if (subEl)   subEl.textContent   = fmtMoney(totalAmt, _cur);
        if (grandEl) grandEl.textContent = fmtMoney(totalAmt, _cur);

        /* Badge */
        if (badge) {
            badge.textContent = totalQty + ' ' + i18n.items;
            badge.hidden = false;
        }

        /* Update global cart counter in header if function exists */
        if (typeof window.pubUpdateCartCount === 'function') {
            window.pubUpdateCartCount(totalQty);
        }
    }

    /* ── API helpers ────────────────────────────────── */
    function apiPost(path, payload, cb) {
        fetch(apiUrl(path), {
            method      : 'POST',
            credentials : 'include',
            headers     : { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body        : JSON.stringify(payload),
        })
        .then(function(r) { return r.json(); })
        .then(function(j) { cb && cb(null, j); })
        .catch(function(err) { cb && cb(err, null); });
    }

    /* ── Set item loading state ─────────────────────── */
    function setItemLoading(idx, loading) {
        var el = document.querySelector('.qz-cart-item[data-idx="' + idx + '"]');
        if (!el) return;
        el.classList.toggle('is-loading', !!loading);
    }

    /* ── Update qty (local + DB) ────────────────────── */
    function updateQty(idx, newQty) {
        if (!_items[idx]) return;
        newQty = Math.max(1, newQty);
        _items[idx].qty = newQty;

        /* Persist to localStorage immediately */
        lsSet(_items.map(function(i) {
            return { id: i.id, name: i.name, unit_price: i.unit_price, sale_price: i.sale_price,
                     qty: i.qty, sku: i.sku, image: i.image, currency: i.currency, entity_id: i.entity_id };
        }));

        render(_items);

        /* Sync to DB if we have a DB item id */
        var dbId = _items[idx] ? _items[idx]._db_id : 0;
        if (dbId) {
            setItemLoading(idx, true);
            apiPost('update', { item_id: dbId, qty: newQty }, function(err) {
                setItemLoading(idx, false);
                if (err) showNotice(i18n.error_generic, 'error');
            });
        }
    }

    /* ── Remove item (local + DB) ───────────────────── */
    function removeItem(idx) {
        if (!_items[idx]) return;
        if (!confirm(i18n.remove_confirm)) return;

        var dbId = _items[idx]._db_id;
        var pid  = _items[idx].id;
        _items.splice(idx, 1);

        /* Update localStorage */
        lsSet(_items.map(function(i) {
            return { id: i.id, name: i.name, unit_price: i.unit_price, sale_price: i.sale_price,
                     qty: i.qty, sku: i.sku, image: i.image, currency: i.currency, entity_id: i.entity_id };
        }));

        render(_items);

        if (dbId) {
            apiPost('remove', { item_id: dbId }, function(err) {
                if (err) showNotice(i18n.error_generic, 'error');
            });
        }
    }

    /* ── Event delegation ───────────────────────────── */
    document.addEventListener('click', function(e) {
        /* ± qty buttons */
        var btn = e.target.closest('.qz-qty-btn[data-delta]');
        if (btn) {
            var idx   = parseInt(btn.dataset.idx, 10);
            var delta = parseInt(btn.dataset.delta, 10);
            if (!_items[idx]) return;
            updateQty(idx, (_items[idx].qty || 1) + delta);
            return;
        }

        /* Remove button */
        var rm = e.target.closest('.qz-remove-btn[data-idx]');
        if (rm) {
            removeItem(parseInt(rm.dataset.idx, 10));
        }
    });

    document.addEventListener('change', function(e) {
        if (!e.target.classList.contains('qz-qty-input')) return;
        var idx = parseInt(e.target.dataset.idx, 10);
        updateQty(idx, parseInt(e.target.value, 10) || 1);
    });

    /* ── Bootstrap: load from DB, fall back to localStorage ── */
    function loadCart() {
        fetch('/api/public/cart'
            + '?tenant_id=' + TENANT_ID
            + '&entity_id=' + activeEntityId()
            + '&_t=' + Date.now(),
            { credentials: 'include', headers: { 'Accept': 'application/json' } }
        )
        .then(function(r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function(resp) {
            var body = document.getElementById('pubCartBody');

            /* API returned a cart record with items → use DB as truth */
            if (resp && resp.data && resp.data.cart) {
                var dbItems = resp.data.items || [];
                _items = dbItems.map(normaliseDbItem);

                /* Sync to localStorage as UX cache */
                lsSet(_items.map(function(i) {
                    return { id: i.id, name: i.name, unit_price: i.unit_price, sale_price: i.sale_price,
                             qty: i.qty, sku: i.sku, image: i.image, currency: i.currency, entity_id: i.entity_id };
                }));

            /* API confirmed no active cart → user either has nothing or is a guest */
            } else if (resp && resp.data !== undefined) {
                /* Check if we have guest/cached items in localStorage */
                var ls = lsGet();
                if (ls.length) {
                    /* Guest items: synthesise display objects (no _db_id) */
                    _items = ls.map(function(i) {
                        return {
                            _db_id     : 0,
                            id         : parseInt(i.id, 10) || 0,
                            entity_id  : parseInt(i.entity_id, 10) || 0,
                            name       : i.name || '',
                            sku        : i.sku || '',
                            qty        : Math.max(1, parseInt(i.qty, 10) || 1),
                            unit_price : parseFloat(i.unit_price || i.price) || 0,
                            sale_price : (i.sale_price && parseFloat(i.sale_price) > 0) ? parseFloat(i.sale_price) : null,
                            image      : i.image || '',
                            currency   : i.currency || i.currency_code || CURRENCY,
                            attrs      : null,
                        };
                    });
                } else {
                    _items = [];
                    lsClear();
                }

            /* API failed (auth error, 5xx, etc.) → fall back to localStorage */
            } else {
                _items = lsGet().map(function(i) {
                    return {
                        _db_id     : 0,
                        id         : parseInt(i.id, 10) || 0,
                        entity_id  : parseInt(i.entity_id, 10) || 0,
                        name       : i.name || '',
                        sku        : i.sku || '',
                        qty        : Math.max(1, parseInt(i.qty, 10) || 1),
                        unit_price : parseFloat(i.unit_price || i.price) || 0,
                        sale_price : (i.sale_price && parseFloat(i.sale_price) > 0) ? parseFloat(i.sale_price) : null,
                        image      : i.image || '',
                        currency   : i.currency || i.currency_code || CURRENCY,
                        attrs      : null,
                    };
                });
            }

            /* Hide loader & render */
            var loader = body.querySelector('.qz-loader');
            if (loader) loader.remove();
            render(_items);
        })
        .catch(function() {
            /* Network failure → localStorage fallback */
            _items = lsGet().map(function(i) {
                return {
                    _db_id: 0, id: parseInt(i.id,10)||0, entity_id: 0,
                    name: i.name||'', sku: i.sku||'',
                    qty: Math.max(1, parseInt(i.qty,10)||1),
                    unit_price: parseFloat(i.unit_price||i.price)||0,
                    sale_price: null, image: i.image||'',
                    currency: i.currency||CURRENCY, attrs: null,
                };
            });
            var body = document.getElementById('pubCartBody');
            var loader = body && body.querySelector('.qz-loader');
            if (loader) loader.remove();
            render(_items);
        });
    }

    /* ── Kick off ───────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadCart);
    } else {
        loadCart();
    }

}());
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>