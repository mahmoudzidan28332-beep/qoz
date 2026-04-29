<?php
require_once __DIR__ . '/../includes/public_context.php';
$pdo = pub_get_pdo();

function debug_store_hours($entityId) {
    global $pdo;
    $res = ["entity_id" => $entityId];
    
    try {
        // 1. Get entity timezone
        $sql = "SELECT e.store_name, tz.timezone FROM entities e 
                LEFT JOIN timezones tz ON tz.id = e.timezone_id 
                WHERE e.id = ?";
        $e = $pdo->prepare($sql);
        $e->execute([$entityId]);
        $row = $e->fetch(PDO::FETCH_ASSOC);
        $res['entity'] = $row;
        
        // 2. Get hours
        $st = $pdo->prepare("SELECT * FROM entities_working_hours WHERE entity_id = ?");
        $st->execute([$entityId]);
        $res['hours'] = $st->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. Current state
        $tzStr = $row['timezone'] ?? 'Asia/Riyadh';
        $res['applied_timezone'] = $tzStr;
        $res['state'] = pub_entity_hours_state($res['hours'], $tzStr);
        
        // 4. Trace DateTime
        $tzObj = new DateTimeZone($tzStr);
        $now = new DateTime('now', $tzObj);
        $res['now'] = [
            'formatted' => $now->format('Y-m-d H:i:s P'),
            'dow' => (int)$now->format('w'),
            'mins' => (int)$now->format('H') * 60 + (int)$now->format('i')
        ];
    } catch (Exception $ex) {
        $res['error'] = $ex->getMessage();
    }
    
    return $res;
}

header('Content-Type: application/json');
echo json_encode(debug_store_hours($_GET['id'] ?? 1), JSON_PRETTY_PRINT);
