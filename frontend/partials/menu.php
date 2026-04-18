<?php
/**
 * frontend/partials/menu.php
 * QOOQZ — Sidebar Navigation Menu (Production Version)
 */

$_ctx = $GLOBALS['PUB_CONTEXT'] ?? [];
$_user = $_ctx['user'] ?? [];
$_isLoggedIn = !empty($_user['id']);
$_appName = $GLOBALS['PUB_APP_NAME'] ?? 'QOOQZ';
$_basePath = rtrim($GLOBALS['PUB_BASE_PATH'] ?? '/frontend/public', '/');
$_authPath = '/frontend';

if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('t')) {
    function t(string $key, string|array $r = []): string {
        return is_string($r) ? $r : $key;
    }
}
?>

<aside class="pub-sidebar" id="pubSidebar" role="navigation" aria-label="<?= e(t('nav.main_navigation')) ?>">
    
    <!-- Sidebar Header -->
    <div class="pub-sidebar-header">
        <a href="<?= e($_basePath . '/index.php') ?>" class="pub-sidebar-logo">
            <span class="pub-sidebar-logo-icon"><i class="bi bi-globe"></i></span>
            <span class="pub-sidebar-title"><?= e($_appName) ?></span>
        </a>
        <button class="pub-sidebar-close" id="pubSidebarClose" aria-label="<?= e(t('nav.menu_close')) ?>"><i class="bi bi-x-lg"></i></button>
    </div>

    <!-- Navigation -->
    <nav class="pub-sidebar-nav">

        <a href="<?= e($_basePath . '/index.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-house-door"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.home')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/products.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-bag"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.products')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/categories.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-grid"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.categories')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/discounts.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-tags"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.offers')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/entities.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-buildings"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.entities')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/tenants.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-shop"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.tenants')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/auctions.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-hammer"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.auctions')) ?></span>
        </a>

        <div class="pub-sidebar-divider"></div>

        <!-- Cart, Wishlist, Compare -->
        <a href="<?= e($_basePath . '/cart.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-cart3"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.cart')) ?></span>
            <span id="pubCartCountSidebar" class="pub-sidebar-badge" style="display:none;"></span>
        </a>

        <a href="<?= e($_basePath . '/wishlist.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-heart"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.wishlist')) ?></span>
            <span id="pubWishlistCountSidebar" class="pub-sidebar-badge" style="display:none;"></span>
        </a>

        <a href="<?= e($_basePath . '/compare.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-arrow-left-right"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.compare')) ?></span>
        </a>

        <div class="pub-sidebar-divider"></div>

        <?php if ($_isLoggedIn): ?>
        <!-- Logged In User -->
        <a href="<?= e($_basePath . '/notifications.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-bell"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.notifications')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/orders.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-box-seam"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.orders')) ?></span>
        </a>

        <a href="<?= e($_authPath . '/profile.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-person-circle"></i></span>
            <span class="pub-sidebar-text"><?= e($_user['name'] ?? $_user['username'] ?? t('nav.account')) ?></span>
        </a>

        <a href="<?= e($_authPath . '/logout.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-box-arrow-right"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.logout')) ?></span>
        </a>

        <?php else: ?>
        <!-- Guest -->
        <a href="<?= e($_authPath . '/login.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-box-arrow-in-right"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.login')) ?></span>
        </a>

        <a href="<?= e($_authPath . '/login.php?tab=register') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon"><i class="bi bi-person-plus"></i></span>
            <span class="pub-sidebar-text"><?= e(t('nav.register')) ?></span>
        </a>
        <?php endif; ?>

    </nav>
</aside>