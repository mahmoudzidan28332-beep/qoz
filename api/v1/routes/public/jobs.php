<?php
declare(strict_types=1);
/**
 * Public API sub-route: jobs
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'jobs') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);

    if ($id) {
        // Full job detail with translated title/description via job_translations
        // Multi-tenant safety: scope job lookup by tenant_id through entity relationship
        $tidCond = $tenantId ? ' AND e.tenant_id = ?' : '';
        $tidParam = $tenantId ? [$tenantId] : [];
        $row = $pdoOne(
            "SELECT j.*, COALESCE(jt.job_title, j.slug) AS title,
                    jt.description, jt.requirements, jt.benefits
               FROM jobs j
          INNER JOIN entities e ON e.id = j.entity_id" . $tidCond . "
          LEFT JOIN job_translations jt ON jt.job_id = j.id AND jt.language_code = ?
              WHERE j.id = ? AND j.status NOT IN ('cancelled','filled','closed') LIMIT 1",
            array_merge($tidParam, [$lang, $id])
        );
        if ($row) ResponseFormatter::success(['ok' => true, 'job' => $row]);
        else      ResponseFormatter::notFound('Job not found');
        exit;
    }

    // $whereParams: params for WHERE only (no $lang — $lang is for the JOIN)
    $where       = "WHERE j.status NOT IN ('cancelled', 'filled', 'closed')";
    $whereParams = [];
    // Multi-tenant safety: scope job listing by tenant_id through entity relationship
    $joinEntity = '';
    $joinParams = [];
    if ($tenantId) {
        $joinEntity = ' INNER JOIN entities e ON e.id = j.entity_id AND e.tenant_id = ?';
        $joinParams[] = $tenantId;
    }
    if (!empty($_GET['is_featured']))    { $where .= ' AND j.is_featured = ?'; $whereParams[] = 1; }
    if (!empty($_GET['is_urgent']))      { $where .= ' AND j.is_urgent = ?';   $whereParams[] = 1; }
    if (!empty($_GET['is_remote']))      { $where .= ' AND j.is_remote = ?';   $whereParams[] = 1; }
    if (!empty($_GET['employment_type'])) {
        // Frontend sends full_time/part_time/etc., stored in j.job_type (not j.employment_type)
        $where .= ' AND j.job_type = ?'; $whereParams[] = $_GET['employment_type'];
    }
    if (!empty($_GET['search'])) {
        // Escape LIKE wildcards so user-typed % or _ match literally; SQL injection prevented by PDO params.
        $kw = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], trim($_GET['search'])) . '%';
        $where .= ' AND (j.slug LIKE ? OR j.id IN (SELECT job_id FROM job_translations WHERE job_title LIKE ? AND language_code = ?))';
        $whereParams[] = $kw;
        $whereParams[] = $kw;
        $whereParams[] = $lang;
    }

    $total = $pdoCount("SELECT COUNT(*) FROM jobs j $joinEntity $where", array_merge($joinParams, $whereParams));
    $rows  = $pdoList(
        "SELECT j.id, COALESCE(jt.job_title, j.slug) AS title,
                j.job_type AS employment_type,
                j.is_remote, j.is_featured, j.is_urgent,
                j.application_deadline AS deadline,
                j.salary_min, j.salary_max, j.salary_currency,
                j.city_id, j.entity_id, j.created_at
           FROM jobs j
           $joinEntity
      LEFT JOIN job_translations jt ON jt.job_id = j.id AND jt.language_code = ?
         $where ORDER BY j.is_featured DESC, j.created_at DESC LIMIT ? OFFSET ?",
        array_merge($joinParams, [$lang], $whereParams, [$per, $offset])
    );

    ResponseFormatter::success([
        'ok'   => true,
        'data' => $rows,
        'meta' => ['total' => $total, 'page' => $page, 'per_page' => $per,
                   'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1],
    ]);
    exit;
}

/* -------------------------------------------------------
 * Route: Entity detail — full vendor profile
 * GET /api/public/entity/{id}           — full entity profile
 * GET /api/public/entity/{id}/products  — entity products
 * GET /api/public/entity/{id}/categories — entity categories
 * GET /api/public/entity/{id}/discounts  — entity discounts
 * ----------------------------------------------------- */