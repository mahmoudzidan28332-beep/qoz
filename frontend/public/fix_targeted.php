<?php
require __DIR__ . '/../includes/public_context.php';
header('Content-Type: text/plain');

$tables = ['order_status_history', 'payments', 'product_stock_movements', 'delivery_providers'];

foreach($tables as $t) {
    echo "Fixing table: $t\n";
    try {
        // Relocate ID 0
        $max = $pdo->query("SELECT MAX(id) FROM `$t`")->fetchColumn() ?: 0;
        $pdo->exec("UPDATE `$t` SET id = " . ($max + 1) . " WHERE id = 0");
        
        // Add auto_increment
        // Need to know current type. Defaulting to BIGINT if unknown, but better to check.
        $res = $pdo->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
        $type = 'BIGINT';
        foreach($res as $col) if($col['Field'] === 'id') $type = $col['Type'];
        
        $pdo->exec("ALTER TABLE `$t` MODIFY `id` $type AUTO_INCREMENT");
        echo "  DONE.\n";
    } catch(Exception $e) {
        echo "  FAILED: " . $e->getMessage() . "\n";
    }
}
?>
