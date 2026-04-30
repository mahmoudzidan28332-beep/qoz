<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/jobs';
require_once $modelsPath . '/repositories/PdoJobInterviewsRepository.php';
require_once $modelsPath . '/validators/JobInterviewsValidator.php';
require_once $modelsPath . '/services/JobInterviewsService.php';
require_once $modelsPath . '/controllers/JobInterviewsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new PdoJobInterviewsRepository($pdo);
$validator  = new App\Models\Jobs\Validators\JobInterviewsValidator();
$service    = new JobInterviewsService($repo, $validator);
$controller = new JobInterviewsController($service);

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw    = file_get_contents('php://input');
    $data   = ($raw && $raw !== '') ? (json_decode($raw, true) ?? []) : [];

    // ── Common params ─────────────────────────────────────────────────────────
    $tenantId = isset($_GET['tenant_id']) && $_GET['tenant_id'] !== ''
        ? (int)$_GET['tenant_id']
        : null;

    $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])              : 1;
    $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit']))  : 25;
    $offset   = ($page - 1) * $limit;
    $orderBy  = (string)($_GET['order_by']  ?? 'interview_date');
    $orderDir = (string)($_GET['order_dir'] ?? 'ASC');

    // ── Filters ───────────────────────────────────────────────────────────────
    $filters = array_filter([
        'application_id' => $_GET['application_id'] ?? null,
        'job_id'         => $_GET['job_id']         ?? null,
        'interview_type' => $_GET['interview_type'] ?? null,
        'status'         => $_GET['status']         ?? null,
        'rating'         => $_GET['rating']         ?? null,
        'date_from'      => $_GET['date_from']      ?? null,
        'date_to'        => $_GET['date_to']        ?? null,
        'today'          => $_GET['today']          ?? null,
        'upcoming'       => $_GET['upcoming']       ?? null,
        'search'         => $_GET['search']         ?? null,
    ], fn($v) => $v !== null && $v !== '');

    switch ($method) {

        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // Get interview types
            if (isset($_GET['interview_types'])) {
                ResponseFormatter::success($controller->getInterviewTypes());

            // Get statuses
            } elseif (isset($_GET['statuses'])) {
                ResponseFormatter::success($controller->getStatuses());

            // Get statistics
            } elseif (isset($_GET['statistics'])) {
                $statsFilters = [];
                if (isset($_GET['job_id'])) {
                    $statsFilters['job_id'] = $_GET['job_id'];
                }
                ResponseFormatter::success($controller->getStatistics($statsFilters));

            // Get interviews by application
            } elseif (isset($_GET['application_id'], $_GET['by_application'])) {
                ResponseFormatter::success(
                    $controller->getByApplication((int)$_GET['application_id'])
                );

            // Get single interview
            } elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
                ResponseFormatter::success(
                    $controller->get((int)$_GET['id'])
                );

            // List all interviews ← THE FIXED CALL
            } else {
                $result = $controller->list(
                    $tenantId,   // ✅ argument #1
                    $limit,      // ✅ argument #2
                    $offset,     // ✅ argument #3
                    $filters,    // ✅ argument #4
                    $orderBy,    // ✅ argument #5
                    $orderDir    // ✅ argument #6
                );
                $total = $result['total'];
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $total,
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                        'from'        => $total > 0 ? $offset + 1                : 0,
                        'to'          => $total > 0 ? min($offset + $limit, $total) : 0,
                    ],
                ]);
            }
            break;

        case 'POST':
            if (isset($data['schedule']) && $data['schedule'] === true) {
                unset($data['schedule']);
                $newId = $controller->schedule($data);
                ResponseFormatter::success(['id' => $newId], 'Interview scheduled successfully', 201);
            } else {
                $newId = $controller->create($data);
                ResponseFormatter::success(['id' => $newId], 'Interview created successfully', 201);
            }
            break;

        case 'PUT':
            $updatedId = $controller->update($data);
            ResponseFormatter::success(['id' => $updatedId], 'Interview updated successfully');
            break;

        case 'PATCH':
            $id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $action = (string)($_GET['action'] ?? '');

            if ($id && isset($data['status'])) {
                $updated = $controller->updateStatus($id, $data['status']);
                ResponseFormatter::success(['updated' => $updated], 'Status updated successfully');

            } elseif ($id && $action === 'feedback') {
                if (empty($data['feedback'])) {
                    ResponseFormatter::error('feedback is required', 400);
                    exit;
                }
                $rating = isset($data['rating']) ? (int)$data['rating'] : null;
                $added  = $controller->addFeedback($id, $data['feedback'], $rating);
                ResponseFormatter::success(['added' => $added], 'Feedback added successfully');

            } elseif ($id && $action === 'reschedule') {
                if (empty($data['new_date'])) {
                    ResponseFormatter::error('new_date is required', 400);
                    exit;
                }
                $newDuration  = isset($data['new_duration']) ? (int)$data['new_duration'] : null;
                $rescheduled  = $controller->reschedule($id, $data['new_date'], $newDuration);
                ResponseFormatter::success(['rescheduled' => $rescheduled], 'Interview rescheduled successfully');

            } elseif ($id && $action === 'confirm') {
                ResponseFormatter::success(['confirmed' => $controller->confirm($id)], 'Interview confirmed');

            } elseif ($id && $action === 'complete') {
                ResponseFormatter::success(['completed' => $controller->complete($id)], 'Interview marked as completed');

            } elseif ($id && $action === 'cancel') {
                ResponseFormatter::success(['cancelled' => $controller->cancel($id)], 'Interview cancelled');

            } elseif ($id && $action === 'no_show') {
                ResponseFormatter::success(['marked' => $controller->markNoShow($id)], 'Marked as no show');

            } else {
                ResponseFormatter::error('Invalid PATCH request', 400);
            }
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing interview ID for deletion', 400);
                exit;
            }
            $deleted = $controller->delete((int)$data['id']);
            ResponseFormatter::success(['deleted' => $deleted], 'Interview deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'job_interviews.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'job_interviews.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (\RuntimeException $e) {
    safe_log('critical', 'job_interviews.fatal', [
        'error'  => $e->getMessage(),
        'trace'  => $e->getTraceAsString(),
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}