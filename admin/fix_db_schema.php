<?php
/**
 * Fix Database Schema for Encryption
 * 
 * Increase column size for encrypted fields:
 * - entity_bank_accounts.iban (VARBINARY 255)
 * - entity_bank_accounts.swift_code (VARBINARY 255)
 */

require_once __DIR__ . '/includes/admin_context.php';

// Auth check (Ensure only admin can run this)
if (!is_admin_logged_in()) {
    die('Access Denied. Please log in as admin.');
}

$pdo = $GLOBALS['ADMIN_DB'];

echo "<h1>Updating Database Schema...</h1>";

try {
    // Update IBAN column
    echo "<p>Updating <code>iban</code> column...</p>";
    $pdo->exec("ALTER TABLE entity_bank_accounts MODIFY COLUMN iban VARBINARY(255) NULL DEFAULT NULL");
    echo "<p style='color:green'>&#10004; IBAN column updated.</p>";

    // Update Swift Code column
    echo "<p>Updating <code>swift_code</code> column...</p>";
    $pdo->exec("ALTER TABLE entity_bank_accounts MODIFY COLUMN swift_code VARBINARY(255) NULL DEFAULT NULL");
    echo "<p style='color:green'>&#10004; Swift Code column updated.</p>";

    echo "<h2>Done! You can now verify the fix.</h2>";
    echo "<p><a href='/admin/fragments/entities_Payment.php'>Go back to Payment Settings</a></p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>Error updating schema: " . htmlspecialchars($e->getMessage()) . "</p>";
}
