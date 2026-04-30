<?php
@set_time_limit(0);
require __DIR__ . '/../includes/public_context.php';

header('Content-Type: text/plain');

$tables = ['order_status_history', 'payments', 'product_stock_movements', 'delivery_providers'];

foreach ($tables as $t) {
    echo "Processing table: $t\n";
    try {
        // 1. Relocate any ID 0
        $stmt = $pdo->query("SELECT id FROM `$t` WHERE id = 0");
        if ($stmt && $stmt->fetch()) {
            echo "  Found ID 0 in $t. Relocating...\n";
            $maxId = (int)$pdo->query("SELECT MAX(id) FROM `$t`")->fetchColumn();
            $newId = ($maxId > 0 ? $maxId + 1 : 1);
            $pdo->exec("UPDATE `$t` SET id = $newId WHERE id = 0");
            echo "  Relocated ID 0 to $newId\n";
        }

        // 2. Ensure Auto Increment
        $res = $pdo->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
        $type = 'BIGINT';
        $isAI = false;
        foreach ($res as $col) {
            if ($col['Field'] === 'id') {
                $type = $col['Type'];
                if (strpos(strtolower($col['Extra']), 'auto_increment') !== false) {
                    $isAI = true;
                }
            }
        }

        if (!$isAI) {
            echo "  Adding AUTO_INCREMENT to $t (ID type: $type)\n";
            $pdo->exec("ALTER TABLE `$t` MODIFY `id` $type AUTO_INCREMENT");
            echo "  Added AUTO_INCREMENT to $t\n";
        } else {
            echo "  $t already has AUTO_INCREMENT\n";
        }
    } catch (\RuntimeException $e) {
        echo "  Error on $t: " . $e->getMessage() . "\n";
    }
    echo "---------------------------\n";
}

echo "EMERGENCY REPAIR COMPLETED.\n";
?>
