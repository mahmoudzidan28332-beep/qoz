<?php
require_once __DIR__ . '/../bootstrap.php';
$pdo = $GLOBALS['ADMIN_DB'];
$stmt = $pdo->query("SELECT id, name, tenant_id, is_default, is_active, theme_scope, theme_target FROM themes WHERE theme_target = 'tenant_store'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "THEMES (tenant_store):\n";
foreach ($rows as $r) {
    echo "ID: {$r['id']} | Name: {$r['name']} | Tenant: " . ($r['tenant_id'] ?? 'NULL') . " | Default: {$r['is_default']} | Active: {$r['is_active']} | Scope: {$r['theme_scope']}\n";
}