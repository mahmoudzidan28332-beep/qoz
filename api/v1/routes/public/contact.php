<?php
declare(strict_types=1);
/**
 * api/v1/routes/public/contact.php
 * QOOQZ — Public Contact Messages API
 *
 * Serves /api/public/contact requests.
 * Loaded by api/v1/routes/public.php dispatcher when $first === 'contact'.
 *
 * Endpoints:
 *  POST /api/public/contact — submit a contact message (requires login)
 *
 * Variables provided by the parent (public.php):
 *  $pdo, $pdoList, $pdoOne, $pdoCount,
 *  $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

$ctMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($ctMethod === 'OPTIONS') {
    if (!headers_sent()) {
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
        http_response_code(204);
    }
    exit;
}

if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database unavailable', 503);
    exit;
}

$ctUserId   = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
$ctTenantId = (int)($tenantId ?? $_SESSION['pub_tenant_id'] ?? 1) ?: 1;

/* -------------------------------------------------------
 * POST /api/public/contact
 * Submit a contact message. Requires login (user_id).
 * ----------------------------------------------------- */
if ($ctMethod === 'POST') {
    // Require logged-in user
    if (!$ctUserId) {
        ResponseFormatter::error('You must be logged in to send a message.', 401);
        exit;
    }

    // Parse input
    $name    = trim((string)($_POST['name']    ?? ''));
    $email   = trim((string)($_POST['email']   ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    // Validate required fields
    $errors = [];
    if ($name === '')    $errors[] = 'Name is required.';
    if ($email === '')   $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    if ($subject === '') $errors[] = 'Subject is required.';
    if ($message === '') $errors[] = 'Message is required.';

    // Length validation
    if (mb_strlen($name) > 255)    $errors[] = 'Name is too long.';
    if (mb_strlen($email) > 255)   $errors[] = 'Email is too long.';
    if (mb_strlen($subject) > 255) $errors[] = 'Subject is too long.';
    if (mb_strlen($message) > 5000) $errors[] = 'Message is too long (max 5000 characters).';

    if (!empty($errors)) {
        ResponseFormatter::error(implode(' ', $errors), 422);
        exit;
    }

    try {
        $contactRepo = new PdoContactMessagesRepository($pdo);
        $contactService = new ContactMessagesService($contactRepo);
        $msgId = $contactService->createMessage($ctTenantId, $ctUserId, $name, $email, $subject, $message);

        ResponseFormatter::success([
            'id'      => $msgId,
            'message' => 'Your message has been sent successfully.',
        ]);
    } catch (ApplicationException|\RuntimeException $ex) {
        error_log('[contact.php POST] ' . $ex->getMessage());
        ResponseFormatter::error('Failed to send your message. Please try again.', 500);
    }
    exit;
}

ResponseFormatter::error('Method not allowed', 405);
