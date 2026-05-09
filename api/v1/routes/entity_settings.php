<?php
declare(strict_types=1);

 $baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

 $modelsPath = API_VERSION_PATH . '/models/entities';
require_once $modelsPath . '/repositories/PdoEntitySettingsRepository.php';
require_once $modelsPath . '/services/EntitySettingsService.php';
require_once $modelsPath . '/controllers/EntitySettingsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

 $pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

 $repo = new PdoEntitySettingsRepository($pdo);
 $service = new EntitySettingsService($repo);
 $controller = new EntitySettingsController($service);



// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    // 🔒 SECURITY: Resolve tenant ID
    $tenantId = resolve_tenant_id();
    if (($tenantId === null || $tenantId <= 0) && is_platform_admin()) {
        $entityIdForScope = null;
        if (isset($_GET['entity_id']) && is_numeric($_GET['entity_id'])) {
            $entityIdForScope = (int)$_GET['entity_id'];
        } elseif (isset($data['entity_id']) && is_numeric($data['entity_id'])) {
            $entityIdForScope = (int)$data['entity_id'];
        }
        if ($entityIdForScope !== null && $entityIdForScope > 0) {
            $tenantId = $controller->getTenantIdByEntityId($entityIdForScope);
        }
    }
    if ($tenantId === null || $tenantId <= 0) {
        ResponseFormatter::error('A valid tenant context is required', 400);
        exit;
    }

    $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit   = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset  = ($page - 1) * $limit;
    $orderBy = $_GET['order_by'] ?? 'entity_id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    // Collect filters
    $filters = [];
    if (isset($_GET['entity_id']) && is_numeric($_GET['entity_id'])) {
        $entityId = (int)$_GET['entity_id'];
        // 🔒 SECURITY: Verify entity ownership if provided in filters
        verify_entity_ownership($pdo, $entityId, $tenantId);
        $filters['entity_id'] = $entityId;
    }
    
    $booleanFilters = ['auto_accept_orders', 'allow_cod', 'allow_online_booking', 
                       'booking_cancellation_allowed', 'allow_preorders', 'is_visible', 
                       'maintenance_mode', 'show_reviews', 'show_contact_info', 
                       'featured_in_app', 'allow_multiple_payment_methods'];
    
    foreach ($booleanFilters as $filter) {
        if (isset($_GET[$filter]) && in_array((int)$_GET[$filter], [0, 1], true)) {
            $filters[$filter] = (int)$_GET[$filter];
        }
    }
    
    $numericFilters = ['min_order_amount', 'preparation_time_minutes', 'booking_window_days',
                       'max_bookings_per_slot', 'max_daily_orders', 'delivery_radius_km', 
                       'free_delivery_min_order'];
    
    foreach ($numericFilters as $filter) {
        if (isset($_GET[$filter]) && is_numeric($_GET[$filter])) {
            $filters[$filter] = $_GET[$filter];
        }
    }
    
    if (isset($_GET['default_payment_method']) && is_string($_GET['default_payment_method'])) {
        $filters['default_payment_method'] = $_GET['default_payment_method'];
    }
    
    if (isset($_GET['store_name']) && !empty(trim($_GET['store_name']))) {
        $filters['store_name'] = trim($_GET['store_name']);
    }
    
    if (isset($_GET['status']) && in_array($_GET['status'], ['pending', 'approved', 'suspended', 'rejected'])) {
        $filters['status'] = $_GET['status'];
    }
    
    if (isset($_GET['additional_settings_search']) && !empty(trim($_GET['additional_settings_search']))) {
        $filters['additional_settings_search'] = trim($_GET['additional_settings_search']);
    }

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            if (isset($_GET['entity_id']) && is_numeric($_GET['entity_id']) && !isset($_GET['page'])) {
                $entityId = (int)$_GET['entity_id'];
                // 🔒 SECURITY: Verify entity ownership
                verify_entity_ownership($pdo, $entityId, $tenantId);
                
                $item = $controller->get($entityId, $tenantId);
                if ($item) {
                    ResponseFormatter::success($item);
                } else {
                    ResponseFormatter::success([
                        'entity_id' => $entityId,
                        'auto_accept_orders' => 0,
                        'allow_cod' => 0,
                        'min_order_amount' => 0,
                        'allow_online_booking' => 0,
                        'booking_window_days' => 0,
                        'max_bookings_per_slot' => 0,
                        'show_reviews' => 1,
                        'show_contact_info' => 1,
                        'featured_in_app' => 0,
                        'card_style_id' => null,
                    ]);
                }
            } else {
                // 🔒 SECURITY: In list view, ensure tenant filtering is enforced in the service/repo
                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
                $total = $result['meta']['total'];
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $total,
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                        'from'        => $total > 0 ? $offset + 1 : 0,
                        'to'          => $total > 0 ? min($offset + $limit, $total) : 0,
                        'filters'     => $filters
                    ]
                ]);
            }
            break;

        case 'POST':
            if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
                ResponseFormatter::error('Entity ID is required', 400);
                exit;
            }
            
            $entityId = (int)$data['entity_id'];
            // 🔒 SECURITY: Verify entity ownership
            verify_entity_ownership($pdo, $entityId, $tenantId);
            
            unset($data['entity_id']);
            $created = $controller->create($entityId, $tenantId, $data);
            ResponseFormatter::success(['id' => $entityId], 'Created successfully', 201);
            break;

        case 'PUT':
            if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
                ResponseFormatter::error('Entity ID is required', 400);
                exit;
            }
            
            $entityId = (int)$data['entity_id'];
            // 🔒 SECURITY: Verify entity ownership
            verify_entity_ownership($pdo, $entityId, $tenantId);
            
            unset($data['entity_id']);
            $updated = $controller->update($entityId, $tenantId, $data);
            ResponseFormatter::success(['id' => $entityId], 'Updated successfully');
            break;

        case 'DELETE':
            if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
                ResponseFormatter::error('Entity ID is required', 400);
                exit;
            }
            
            $entityId = (int)$data['entity_id'];
            // 🔒 SECURITY: Verify entity ownership
            verify_entity_ownership($pdo, $entityId, $tenantId);
            
            $deleted = $controller->delete($entityId, $tenantId);
            ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning','entity_settings.validation', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (ApplicationException|\RuntimeException $e) {
    safe_log('error','entity_settings.runtime', ['error'=>$e->getMessage()]);
    ResponseFormatter::error('Request could not be completed', 400);
} catch (\Throwable $e) {
    safe_log('critical','entity_settings.fatal', ['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    ResponseFormatter::error('Internal Server Error', 500);
}
