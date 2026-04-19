<?php
/**///htdocs/frontend/partials/dashboard.php
 * User Dashboard
 */
$UI   = $GLOBALS['PUBLIC_UI'] ?? [];
$user = $UI['user'] ?? [];

if (empty($user['id'])) {
    header('Location: /frontend/login.php');
    exit;
}
?>

<section class="dashboard">
    <h1><?= e(t('dashboard.title', 'Dashboard')) ?></h1>

    <div class="dashboard-cards grid grid-3">
        <div class="card">
            <h3><?= e(t('dashboard.profile', 'Profile')) ?></h3>
            <a href="/frontend/profile.php"><?= e(t('dashboard.manage', 'Manage')) ?></a>
        </div>

        <div class="card">
            <h3><?= e(t('dashboard.orders', 'My Orders')) ?></h3>
            <a href="/frontend/orders.php"><?= e(t('dashboard.view_orders', 'View Orders')) ?></a>
        </div>

        <div class="card">
            <h3><?= e(t('dashboard.settings', 'Settings')) ?></h3>
            <a href="/frontend/settings.php"><?= e(t('dashboard.edit', 'Edit')) ?></a>
        </div>
    </div>
</section>
