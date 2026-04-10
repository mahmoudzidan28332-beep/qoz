<?php
/**
 * Migration: Add zone_value column to delivery_zones
 *
 * Adds a TEXT column to store GeoJSON geometry (polygon coordinates,
 * radius center, etc.) for each delivery zone.
 *
 * Safe to run multiple times (idempotent).
 */

require_once __DIR__ . '/includes/admin_context.php';

if (!is_admin_logged_in()) {
    die('Access Denied. Please log in as admin.');
}

$pdo = $GLOBALS['ADMIN_DB'];

echo "<h1>Migration: delivery_zones.zone_value</h1>";

try {
    // Check whether the column already exists
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'delivery_zones'
           AND COLUMN_NAME  = 'zone_value'"
    );
    $stmt->execute();
    $exists = (int) $stmt->fetchColumn();

    if ($exists > 0) {
        echo "<p style='color:green'>&#10004; Column <code>zone_value</code> already exists — nothing to do.</p>";
    } else {
        echo "<p>Adding <code>zone_value TEXT NULL</code> to <code>delivery_zones</code>...</p>";
        $pdo->exec("ALTER TABLE delivery_zones ADD COLUMN zone_value TEXT NULL DEFAULT NULL");
        echo "<p style='color:green'>&#10004; Column <code>zone_value</code> added successfully.</p>";
    }

    echo "<h2>Done!</h2>";
    echo "<p><a href='/admin/fragments/delivery.php'>Go back to Delivery Zones</a></p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}