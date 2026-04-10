<?php
// admin/print_certificate.php
$id = $_GET['id'] ?? '';
$lang = $_GET['lang'] ?? 'ar';
header("Location: /api/print-certificate?id=" . urlencode($id) . "&lang=" . urlencode($lang));
exit;