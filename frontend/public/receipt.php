<?php
declare(strict_types=1);

/**
 * /frontend/public/receipt.php
 * Public receipt viewer for POS orders.
 * Accessible via QR code or direct link without login.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$orderNumber = $_GET['order'] ?? '';
if (empty($orderNumber)) {
    die('Order number is required');
}

$pdo = pub_get_pdo();
$order = null;
$items = [];

if ($pdo) {
    try {
        // Fetch order basic info
        $stOrder = $pdo->prepare(
            "SELECT o.*, e.name AS entity_name, e.address AS entity_address, e.phone AS entity_phone, e.vat_number
             FROM orders o
             LEFT JOIN entities e ON o.entity_id = e.id
             WHERE o.order_number = ?
             LIMIT 1"
        );
        $stOrder->execute([$orderNumber]);
        $order = $stOrder->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Fetch order items
            $stItems = $pdo->prepare(
                "SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC"
            );
            $stItems->execute([$order['id']]);
            $items = $stItems->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        error_log('[receipt.php] ' . $e->getMessage());
    }
}

if (!$order) {
    die('Order not found');
}

$lang = $_SESSION['lang'] ?? 'ar';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

// Helper for currency formatting
function fmt($val, $cur = 'SAR') {
    return number_format((float)$val, 2) . ' ' . $cur;
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= htmlspecialchars($orderNumber) ?></title>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; background: #f4f7f9; margin: 0; padding: 20px; display: flex; justify-content: center; }
        .receipt-card { background: #fff; width: 100%; max-width: 400px; padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .receipt-header { text-align: center; margin-bottom: 20px; }
        .receipt-title { font-size: 20px; font-weight: 800; color: #1e293b; margin-bottom: 4px; text-transform: uppercase; }
        .store-name { font-size: 16px; font-weight: 600; color: #334155; }
        .meta { font-size: 13px; color: #64748b; margin-top: 4px; }
        .divider { border: none; border-top: 1px dashed #e2e8f0; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { text-align: start; color: #64748b; font-weight: 500; padding-bottom: 8px; }
        td { padding: 8px 0; vertical-align: top; }
        .col-qty { text-align: center; width: 40px; }
        .col-total { text-align: end; font-weight: 700; width: 90px; }
        .summary-row { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 6px; }
        .grand-total { font-size: 18px; font-weight: 800; color: #1e293b; margin-top: 10px; padding-top: 10px; border-top: 1.5px solid #f1f5f9; }
        .footer { text-align: center; margin-top: 30px; font-size: 13px; color: #94a3b8; }
        .print-btn { display: block; width: 100%; background: #3b82f6; color: #fff; text-align: center; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 700; margin-top: 20px; border: none; cursor: pointer; }
        @media print { .print-btn { display: none; } body { padding: 0; background: #fff; } .receipt-card { box-shadow: none; width: 100%; max-width: 100%; border-radius: 0; } }
    </style>
</head>
<body>
    <div class="receipt-card">
        <div class="receipt-header">
            <div class="receipt-title"><?= ($lang === 'ar' ? 'فاتورة ضريبية' : 'TAX INVOICE') ?></div>
            <div class="store-name"><?= htmlspecialchars($order['entity_name'] ?: 'Store') ?></div>
            <?php if ($order['vat_number']): ?><div class="meta">VAT: <?= htmlspecialchars($order['vat_number']) ?></div><?php endif; ?>
            <div class="meta"><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></div>
            <div class="meta">Order: #<?= htmlspecialchars($orderNumber) ?></div>
        </div>

        <hr class="divider">

        <table>
            <thead>
                <tr>
                    <th><?= ($lang === 'ar' ? 'الصنف' : 'ITEM') ?></th>
                    <th class="col-qty"><?= ($lang === 'ar' ? 'الكمية' : 'QTY') ?></th>
                    <th class="col-total"><?= ($lang === 'ar' ? 'الإجمالي' : 'TOTAL') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <div style="font-weight:600"><?= htmlspecialchars($item['product_name']) ?></div>
                        <div style="font-size:11px;color:#64748b"><?= fmt($item['unit_price'], $item['currency_code']) ?></div>
                    </td>
                    <td class="col-qty"><?= (int)$item['quantity'] ?></td>
                    <td class="col-total"><?= fmt($item['total'], $item['currency_code']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <hr class="divider">

        <div class="summary-row">
            <span><?= ($lang === 'ar' ? 'المجموع (غير شامل الضريبة)' : 'Subtotal (Excl. Tax)') ?></span>
            <span><?= fmt($order['subtotal'], $order['currency_code']) ?></span>
        </div>
        <?php if ((float)$order['tax_amount'] > 0): ?>
        <div class="summary-row">
            <span><?= ($lang === 'ar' ? 'الضريبة (15%)' : 'VAT (15%)') ?></span>
            <span><?= fmt($order['tax_amount'], $order['currency_code']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ((float)$order['discount_amount'] > 0): ?>
        <div class="summary-row" style="color:#ef4444">
            <span><?= ($lang === 'ar' ? 'الخصم' : 'Discount') ?></span>
            <span>-<?= fmt($order['discount_amount'], $order['currency_code']) ?></span>
        </div>
        <?php endif; ?>

        <div class="summary-row grand-total">
            <span><?= ($lang === 'ar' ? 'إجمالي المبلغ' : 'TOTAL AMOUNT') ?></span>
            <span><?= fmt($order['grand_total'], $order['currency_code']) ?></span>
        </div>

        <div class="footer">
            <div style="font-weight:700;color:#334155;margin-bottom:4px"><?= ($lang === 'ar' ? 'شكراً لزيارتكم' : 'THANK YOU!') ?></div>
            <div><?= ($lang === 'ar' ? 'نقدر تعاملكم معنا' : 'We appreciate your business') ?></div>
        </div>

        <button class="print-btn" onclick="window.print()">🖨 <?= ($lang === 'ar' ? 'طباعة' : 'Print') ?></button>
    </div>
</body>
</html>
