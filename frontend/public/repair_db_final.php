<?php
require __DIR__ . '/../includes/public_context.php';

header('Content-Type: text/plain');

$tables = ['orders', 'order_items', 'order_status_history', 'payments', 'product_stock_movements', 'delivery_providers'];

foreach ($tables as $t) {
    echo "Checking table: $t\n";
    try {
        // Get structure
        $res = $pdo->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($res as $col) {
            if ($col['Field'] === 'id') {
                echo "  ID Column: " . json_encode($col) . "\n";
                if (strpos(strtolower($col['Extra']), 'auto_increment') === false) {
                    echo "  FIXING: Adding AUTO_INCREMENT to $t\n";
                    
                    // Check for id=0
                    $zero = $pdo->query("SELECT COUNT(*) FROM `$t` WHERE id = 0")->fetchColumn();
                    if ($zero > 0) {
                        echo "  FOUND duplicate/zero ID. Relocating...\n";
                        $max = $pdo->query("SELECT MAX(id) FROM `$t`")->fetchColumn();
                        $newId = $max + 1;
                        $pdo->exec("UPDATE `$t` SET id = $newId WHERE id = 0");
                    }
                    
                    // Alter table
                    $type = $col['Type'];
                    $pdo->exec("ALTER TABLE `$t` MODIFY `id` $type AUTO_INCREMENT");
                    echo "  SUCCESS: Altered $t\n";
                }
            }
        }
    } catch (\RuntimeException $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
    echo "---------------------------\n";
}
?>
