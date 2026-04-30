<?php
// api/routes/attributes.php
declare(strict_types=1);
header('Content-Type: application/json');

$bootstrap = __DIR__ . '/../../bootstrap.php';
if (is_readable($bootstrap)) { require_once $bootstrap; }

try {
    // نستخدم الجدول الصحيح: vendor_attributes + vendor_attribute_translations
    $lang = isset($_GET['lang']) ? trim($_GET['lang']) : 'en';
    if (!preg_match('/^[a-z]{2}$/', $lang)) { $lang = 'en'; }
    $sql = "SELECT va.id, va.slug, vat.name AS display_name
            FROM vendor_attributes va
            LEFT JOIN vendor_attribute_translations vat 
                ON va.id = vat.attribute_id AND vat.language_code = ?
            ORDER BY va.id ASC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $lang);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = [];
    while($row = $res->fetch_assoc()){
        $data[] = [
            'id' => $row['id'],
            'key_name' => $row['slug'],
            'display_name' => $row['display_name'] ?: $row['slug']
        ];
    }

    echo json_encode(['success'=>true, 'data'=>$data]);

} catch (\RuntimeException $e){
    require_once __DIR__ . '/../../shared/core/ResponseFormatter.php';
    ResponseFormatter::serverError($e);
}


