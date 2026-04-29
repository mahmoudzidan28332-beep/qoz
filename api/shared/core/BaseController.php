<?php
declare(strict_types=1);

/**
 * BaseController
 *
 * Abstract foundation for all controller classes.
 *
 * PROVIDES:
 *  1. requirePermission() — a named wrapper around authorize() that makes the
 *     permission check mandatory and self-documenting at each call site.
 *
 *  2. requireTenantScope() — fail-fast guard asserting TenantContext is set.
 *
 *  3. notFound() / forbidden() / badRequest() — standardised HTTP error
 *     helpers so every controller responds consistently.
 *
 * PERMISSION MAPPING CONVENTION (enforce across ALL route files / controllers):
 *
 *   HTTP Method │ Permission suffix  │ Example
 *   ────────────┼────────────────────┼──────────────────
 *   GET         │ *.view             │ addresses.view
 *   POST        │ *.create           │ addresses.create
 *   PUT / PATCH │ *.edit             │ addresses.edit
 *   DELETE      │ *.delete           │ addresses.delete
 *
 * USAGE:
 *
 *   final class AddressesController extends BaseController
 *   {
 *       public function list(...): array
 *       {
 *           $this->requirePermission('addresses.view');
 *           $this->requireTenantScope();
 *           return $this->service->list(...);
 *       }
 *   }
 *
 * BACKWARD COMPATIBILITY:
 *  Existing controllers that do NOT yet extend BaseController continue to work
 *  unchanged.  Extend incrementally — there is no forced migration.
 */
abstract class BaseController
{
    // =========================================================================
    // Permission guard
    // =========================================================================

    /**
     * Enforce a named permission for the current user.
     *
     * Delegates to the global authorize() function (authorize.php), which:
     *  - Allows super-admins through (after logging the bypass).
     *  - Checks the session permission cache.
     *  - Falls back to a DB lookup via PermissionService.
     *  - Terminates the request with HTTP 403 if the permission is absent.
     *
     * @param  string $permission  Dot-notated permission key, e.g. 'products.edit'.
     */
    protected function requirePermission(string $permission): void
    {
        authorize($permission);
    }

    // =========================================================================
    // Tenant scope guard
    // =========================================================================

    /**
     * Assert that a valid tenant scope is active for this request.
     *
     * @throws \RuntimeException  If TenantContext has not been initialised.
     */
    protected function requireTenantScope(): void
    {
        TenantContext::require();
    }

    // =========================================================================
    // Standard HTTP error helpers
    // =========================================================================

    /**
     * Terminate with HTTP 404 and a JSON body.
     *
     * @param  string $message  Human-readable description.
     * @throws never             Always calls exit().
     */
    protected function notFound(string $message = 'Resource not found.'): never
    {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Terminate with HTTP 403 and a JSON body.
     *
     * @param  string $message  Human-readable description.
     * @throws never             Always calls exit().
     */
    protected function forbidden(string $message = 'Forbidden.'): never
    {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Terminate with HTTP 400 and a JSON body.
     *
     * @param  string $message  Human-readable description.
     * @throws never             Always calls exit().
     */
    protected function badRequest(string $message = 'Bad request.'): never
    {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}