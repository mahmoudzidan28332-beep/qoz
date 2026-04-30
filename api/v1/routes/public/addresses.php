<?php
declare(strict_types=1);
/**
 * Public API sub-route: addresses
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'addresses') {
    $addrSessUser   = $_SESSION['user'] ?? null;
    $addrSessUserId = isset($addrSessUser['id']) ? (int)$addrSessUser['id'] : 0;
    // Also accept user_id from session (scalar form)
    if (!$addrSessUserId && !empty($_SESSION['user_id'])) {
        $addrSessUserId = (int)$_SESSION['user_id'];
    }
    if (!$addrSessUserId) {
        ResponseFormatter::error('Login required', 401); exit;
    }

    $addrId = isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : 0;

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_method'] ?? '') === 'DELETE')) {
        if (!$addrId) { ResponseFormatter::error('Address ID required', 422); exit; }
        $addrRow = $pdoOne('SELECT id FROM addresses WHERE id = ? AND owner_id = ? AND owner_type = "user" LIMIT 1', [$addrId, $addrSessUserId]);
        if (!$addrRow) { ResponseFormatter::notFound('Address not found'); exit; }
        try {
            $addrRepo = new PdoAddressesRepository($pdo);
            $addrRepo->deleteByOwner($addrId, $addrSessUserId);
            ResponseFormatter::success(['ok' => true]);
        } catch (ApplicationException|\RuntimeException $_) { ResponseFormatter::error('Delete failed', 500); }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $addrLine1  = trim($_POST['address_line1'] ?? '');
        $addrLine2  = trim($_POST['address_line2'] ?? '');
        $cityId     = (int)($_POST['city_id']     ?? 0);
        $countryId  = (int)($_POST['country_id']  ?? 0);
        $postalCode = trim($_POST['postal_code']  ?? '');
        $isPrimary  = !empty($_POST['is_primary']) ? 1 : 0;
        if (!$addrLine1) { ResponseFormatter::error('address_line1 is required', 422); exit; }

        try {
            $addrRepo = new PdoAddressesRepository($pdo);
            if ($isPrimary) {
                $addrRepo->resetPrimaryByOwner($addrSessUserId);
            }
            // tenant_id = null for regular user addresses
            $newAddrId = $addrRepo->createAddress($addrSessUserId, $addrLine1, $addrLine2 ?: null, $cityId ?: null, $countryId ?: null, $postalCode ?: null, $isPrimary, null);
            ResponseFormatter::success(['ok' => true, 'id' => $newAddrId], 'Address added', 201);
        } catch (ApplicationException|\RuntimeException $_) { ResponseFormatter::error('Failed to save address', 500); }
        exit;
    }

    // GET — list addresses for logged-in user
    $addrRows = $pdoList(
        "SELECT a.id, a.address_line1, a.address_line2, a.postal_code, a.is_primary,
                c.name AS city_name, co.name AS country_name
           FROM addresses a
      LEFT JOIN cities c ON c.id = a.city_id
      LEFT JOIN countries co ON co.id = a.country_id
          WHERE a.owner_id = ? AND a.owner_type = 'user'
          ORDER BY a.is_primary DESC, a.id DESC",
        [$addrSessUserId]
    );
    ResponseFormatter::success($addrRows);
    exit;
}


/* -------------------------------------------------------
 * Route: Register as Entity (Vendor)
 * POST /api/public/register/entity — requires login
 * ----------------------------------------------------- */