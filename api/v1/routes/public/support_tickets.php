<?php
declare(strict_types=1);
/**
 * api/v1/routes/public/support_tickets.php
 * QOOQZ — Public Support Tickets API
 *
 * Serves /api/public/support_tickets/* requests for the currently logged-in user.
 * Loaded by api/v1/routes/public.php dispatcher when $first === 'support_tickets'.
 *
 * Endpoints:
 *  GET  /api/public/support_tickets              — list the current user's tickets
 *  POST /api/public/support_tickets              — create a new support ticket
 *  GET  /api/public/support_tickets/categories   — list active ticket categories
 *
 * Variables provided by the parent (public.php):
 *  $pdo, $pdoList, $pdoOne, $pdoCount,
 *  $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

$stMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$stSub    = strtolower($segments[1] ?? '');

if ($stMethod === 'OPTIONS') {
    if (!headers_sent()) {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
        http_response_code(204);
    }
    exit;
}

if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database unavailable', 503);
    exit;
}

$stUserId   = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
$stTenantId = (int)($tenantId ?? $_SESSION['pub_tenant_id'] ?? 1) ?: 1;

/* -------------------------------------------------------
 * GET /api/public/support_tickets/categories
 * Returns active ticket categories for the form dropdown.
 * No login required.
 * ----------------------------------------------------- */
if ($stMethod === 'GET' && $stSub === 'categories') {
    try {
        $cats = $pdoList(
            "SELECT tc.id, COALESCE(tct.name, tc.slug) AS name
             FROM ticket_categories tc
             LEFT JOIN ticket_category_translations tct
                ON tct.category_id = tc.id AND tct.lang = ?
             WHERE tc.tenant_id = ? AND tc.is_active = 1
             ORDER BY tc.sort_order ASC, tc.id ASC",
            [$lang, $stTenantId]
        );
        ResponseFormatter::success(['items' => $cats]);
    } catch (Throwable $ex) {
        // Fallback: ticket_category_translations table may not exist yet.
        try {
            $cats = $pdoList(
                "SELECT id, slug AS name FROM ticket_categories
                 WHERE tenant_id = ? AND is_active = 1
                 ORDER BY sort_order ASC, id ASC",
                [$stTenantId]
            );
            ResponseFormatter::success(['items' => $cats]);
        } catch (Throwable $ex2) {
            ResponseFormatter::error('Failed to load categories', 500);
        }
    }
    exit;
}

/* -------------------------------------------------------
 * GET /api/public/support_tickets
 * Returns the logged-in user's tickets (newest first).
 * Requires login.
 * ----------------------------------------------------- */
if ($stMethod === 'GET' && $stSub === '') {
    if (!$stUserId) {
        ResponseFormatter::error('Login required', 401);
        exit;
    }

    $filterStatus = isset($_GET['status']) && in_array($_GET['status'], [
        'open','pending','awaiting_customer','awaiting_vendor',
        'in_progress','resolved','closed','cancelled',
    ]) ? $_GET['status'] : '';

    $where  = 'WHERE st.tenant_id = ? AND st.user_id = ?';
    $params = [$stTenantId, $stUserId];
    if ($filterStatus) {
        $where  .= ' AND st.status = ?';
        $params[] = $filterStatus;
    }

    $total = $pdoCount(
        "SELECT COUNT(*) FROM support_tickets st $where",
        $params
    );

    $rows = $pdoList(
        "SELECT st.id, st.subject, st.status, st.priority, st.created_at,
                COALESCE(tct.name, tc.slug) AS category_name
         FROM support_tickets st
         LEFT JOIN ticket_categories tc ON tc.id = st.category_id
         LEFT JOIN ticket_category_translations tct
            ON tct.category_id = tc.id AND tct.lang = ?
         $where
         ORDER BY st.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge([$lang], $params, [$per, $offset])
    );

    // If the join query returned nothing but there IS a count, retry without translations join.
    if (!$rows && $total > 0) {
        $rows = $pdoList(
            "SELECT st.id, st.subject, st.status, st.priority, st.created_at,
                    tc.slug AS category_name
             FROM support_tickets st
             LEFT JOIN ticket_categories tc ON tc.id = st.category_id
             $where
             ORDER BY st.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$per, $offset])
        );
    }

    ResponseFormatter::success([
        'items' => $rows,
        'meta'  => [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per,
            'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1,
        ],
    ]);
    exit;
}

/* -------------------------------------------------------
 * POST /api/public/support_tickets
 * Create a new support ticket for the logged-in user.
 * Requires login.
 * Body: { category_id, subject, description, priority? }
 * ----------------------------------------------------- */
if ($stMethod === 'POST' && $stSub === '') {
    if (!$stUserId) {
        ResponseFormatter::error('Login required to submit a ticket', 401);
        exit;
    }

    $raw  = (string)(file_get_contents('php://input') ?: '');
    $body = (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json'))
          ? (json_decode($raw, true) ?? [])
          : $_POST;

    $categoryId  = isset($body['category_id'])  && is_numeric($body['category_id'])  ? (int)$body['category_id']  : null;
    $subject     = trim((string)($body['subject']     ?? ''));
    $description = trim((string)($body['description'] ?? ''));
    $priority    = trim((string)($body['priority']    ?? 'normal'));

    if (!$subject) {
        ResponseFormatter::error('subject is required', 422);
        exit;
    }
    if (!$description) {
        ResponseFormatter::error('description is required', 422);
        exit;
    }

    $allowedPriorities = ['low', 'normal', 'high', 'urgent'];
    if (!in_array($priority, $allowedPriorities, true)) {
        $priority = 'normal';
    }

    // Validate category belongs to this tenant (if provided)
    if ($categoryId) {
        $catCheck = $pdoOne(
            "SELECT id FROM ticket_categories WHERE id = ? AND tenant_id = ? AND is_active = 1 LIMIT 1",
            [$categoryId, $stTenantId]
        );
        if (!$catCheck) {
            $categoryId = null;
        }
    }

    try {
        $ticketRepo = new PdoSupportTicketsRepository($pdo);
        $newId = $ticketRepo->createPublic($stTenantId, $stUserId, $categoryId, $subject, $description, $priority);
        ResponseFormatter::success(['id' => $newId, 'success' => true], 'Ticket submitted successfully', 201);
    } catch (Throwable $ex) {
        ResponseFormatter::error('Failed to create ticket', 500);
    }
    exit;
}

ResponseFormatter::error('Method not allowed', 405);