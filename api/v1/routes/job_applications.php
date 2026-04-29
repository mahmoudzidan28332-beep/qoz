<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/jobs';
require_once $modelsPath . '/repositories/PdoJobApplicationsRepository.php';
require_once $modelsPath . '/validators/JobApplicationsValidator.php';
require_once $modelsPath . '/services/JobApplicationsService.php';
require_once $modelsPath . '/controllers/JobApplicationsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo      = new PdoJobApplicationsRepository($pdo);
$validator = new App\Models\Jobs\Validators\JobApplicationsValidator();
$service   = new JobApplicationsService($repo, $validator);
$controller = new JobApplicationsController($service);

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

    $lang     = (string)($_GET['lang']      ?? 'ar');
    $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])               : 1;
    $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit']))   : 25;
    $offset   = ($page - 1) * $limit;
    $orderBy  = (string)($_GET['order_by']  ?? 'id');
    $orderDir = (string)($_GET['order_dir'] ?? 'DESC');

    // ── Filters ───────────────────────────────────────────────────────────────
    $filters = array_filter([
        'job_id'         => $_GET['job_id']         ?? null,
        'user_id'        => $_GET['user_id']        ?? null,
        'status'         => $_GET['status']         ?? null,
        'rating'         => $_GET['rating']         ?? null,
        'search'         => $_GET['search']         ?? null,
        'salary_min'     => $_GET['salary_min']     ?? null,
        'salary_max'     => $_GET['salary_max']     ?? null,
        'experience_min' => $_GET['experience_min'] ?? null,
        'experience_max' => $_GET['experience_max'] ?? null,
        'rating_min'     => $_GET['rating_min']     ?? null,
        'date_from'      => $_GET['date_from']      ?? null,
        'date_to'        => $_GET['date_to']        ?? null,
        'reviewed'       => $_GET['reviewed']       ?? null,
    ], fn($v) => $v !== null && $v !== '');

    switch ($method) {

        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // Get applications by job
            if (isset($_GET['job_id'], $_GET['by_job'])) {
                ResponseFormatter::success(
                    $controller->getByJob((int)$_GET['job_id'], $lang)
                );

            // Get applications by user
            } elseif (isset($_GET['user_id'], $_GET['by_user'])) {
                ResponseFormatter::success(
                    $controller->getByUser((int)$_GET['user_id'], $lang)
                );

            // Check if user applied
            } elseif (isset($_GET['check_applied'], $_GET['job_id'], $_GET['user_id'])) {
                ResponseFormatter::success([
                    'has_applied' => $controller->hasApplied(
                        (int)$_GET['job_id'],
                        (int)$_GET['user_id']
                    ),
                ]);

            // Get statistics
            } elseif (isset($_GET['job_id'], $_GET['statistics'])) {
                ResponseFormatter::success(
                    $controller->getStatistics((int)$_GET['job_id'])
                );

            // Get single application
            } elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
                ResponseFormatter::success(
                    $controller->get((int)$_GET['id'], $lang)
                );

            // List all applications ← THE FIXED CALL
            } else {
                $result = $controller->list(
                    $tenantId,   // ✅ argument #1
                    $limit,      // ✅ argument #2
                    $offset,     // ✅ argument #3
                    $filters,    // ✅ argument #4
                    $orderBy,    // ✅ argument #5
                    $orderDir,   // ✅ argument #6
                    $lang        // ✅ argument #7
                );
                $total = $result['total'];
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $total,
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                        'from'        => $total > 0 ? $offset + 1          : 0,
                        'to'          => $total > 0 ? min($offset + $limit, $total) : 0,
                    ],
                ]);
            }
            break;

        case 'POST':
            $data['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
            $newId = $controller->create($data);
            ResponseFormatter::success(['id' => $newId], 'Application submitted successfully', 201);
            break;

        case 'PUT':
            $updatedId = $controller->update($data);
            ResponseFormatter::success(['id' => $updatedId], 'Application updated successfully');
            break;

        case 'PATCH':
            $id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $action = (string)($_GET['action'] ?? '');

            if ($id && isset($data['status'])) {
                $updated = $controller->updateStatus($id, $data['status']);
                ResponseFormatter::success(['updated' => $updated], 'Status updated successfully');

            } elseif ($id && isset($data['rating'])) {
                $updated = $controller->updateRating(
                    $id,
                    (int)$data['rating'],
                    isset($data['reviewed_by']) ? (int)$data['reviewed_by'] : null
                );
                ResponseFormatter::success(['updated' => $updated], 'Rating updated successfully');

            } elseif ($id && $action === 'review') {
                if (empty($data['notes']) || empty($data['reviewed_by'])) {
                    ResponseFormatter::error('notes and reviewed_by are required', 400);
                    exit;
                }
                $added = $controller->addReview($id, $data['notes'], (int)$data['reviewed_by']);
                ResponseFormatter::success(['added' => $added], 'Review added successfully');

            } elseif ($id && $action === 'shortlist') {
                ResponseFormatter::success(['updated' => $controller->shortlist($id)], 'Application shortlisted');

            } elseif ($id && $action === 'reject') {
                ResponseFormatter::success(['updated' => $controller->reject($id)], 'Application rejected');

            } elseif ($id && $action === 'schedule_interview') {
                ResponseFormatter::success(['updated' => $controller->scheduleInterview($id)], 'Interview scheduled');

            } elseif ($id && $action === 'mark_interviewed') {
                ResponseFormatter::success(['updated' => $controller->markInterviewed($id)], 'Marked as interviewed');

            } elseif ($id && $action === 'make_offer') {
                ResponseFormatter::success(['updated' => $controller->makeOffer($id)], 'Offer made');

            } elseif ($id && $action === 'accept_offer') {
                ResponseFormatter::success(['updated' => $controller->acceptOffer($id)], 'Offer accepted');

            } elseif ($id && $action === 'withdraw') {
                ResponseFormatter::success(['updated' => $controller->withdraw($id)], 'Application withdrawn');

            } elseif ($id && $action === 'under_review') {
                ResponseFormatter::success(['updated' => $controller->moveToUnderReview($id)], 'Moved to under review');

            } else {
                ResponseFormatter::error('Invalid PATCH request', 400);
            }
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing application ID for deletion', 400);
                exit;
            }
            $deleted = $controller->delete((int)$data['id']);
            ResponseFormatter::success(['deleted' => $deleted], 'Application deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'job_applications.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'job_applications.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'job_applications.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}