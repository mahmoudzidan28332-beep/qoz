Add tenant_id condition to prevent cross-tenant data leakage.
[JWT Security]
CVSS 7.4
CWE-347
Module: Penetration Testing
JWT using HS256 (symmetric) — vulnerable to secret brute-force
api/shared/helpers/jwt.php
Prefer RS256 or ES256 (asymmetric). If using HS256, ensure secret entropy ≥ 256 bits.
[IDOR]
CVSS 7.5
CWE-639
Module: Penetration Testing
Routes use dynamic resource ID without visible ownership verification (2)
api/v1/routes/public/jobs.php
api/v1/routes/public/vendors.php
After fetching resource by ID, verify ownership: WHERE id=:id AND tenant_id=:tid.
[Performance]
Module: Performance
SELECT * usage across 74 repository files
api/shared/core/repositories/AuthRepository.php
api/shared/core/repositories/RbacRepository.php
api/shared/core/repositories/UploadRepository.php
api/v1/models/ads/repositories/PdoAdPlacementsRepository.php
api/v1/models/attribute_types/repositories/PdoAttributeTypesRepository.php
api/v1/models/auctions/repositories/PdoAuctionActivityLogRepository.php
api/v1/models/auctions/repositories/PdoAuctionBidsRepository.php
api/v1/models/auctions/repositories/PdoAuctionTranslationsRepository.php
…and 66 more
Consider specifying only the columns your application needs.
[Index Usage]
Module: Performance
Function applied to indexed column in WHERE (1 files)
api/shared/core/repositories/AiRepository.php
Use virtual/computed columns or restructure condition to preserve index.
[Multi-Tenant Chain]
Module: Multi-Tenant
Entity-scoped tables using entity_id without explicit tenant_id (3)
api/v1/models/entities/repositories/PdoEntityProductVariantsRepository.php (entity_product_variants)
api/v1/models/entities/repositories/PdoEntityProductsRepository.php (entity_products)
api/v1/models/entities/repositories/PdoEntityTranslationsRepository.php (entity_translations)
Verify entity_id is always fetched with a tenant_id filter upstream.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception/Throwable) in core/service/repository files (77)
api/shared/core/BootstrapRateLimiter.php
api/shared/core/EventDispatcher.php
api/shared/core/QueueManager.php
api/shared/core/ResponseFormatter.php
api/shared/core/plugin_manager.php
api/shared/helpers/AuditLogger.php
api/shared/helpers/CSRF.php
api/shared/helpers/RBAC.php
…and 69 more
Catch specific types (PDOException, RuntimeException) in business logic for clearer error handling.
[Type Safety]
Module: Type Safety
Files missing declare(strict_types=1) — 2 files
api/shared/core/BootstrapRateLimiter.php
api/shared/helpers/color_utils.php
Add declare(strict_types=1); at the top of every PHP file for compile-time type safety.
[Type Safety]
Module: Type Safety
Public methods missing return/param types — 16 files affected
api/shared/helpers/ai.php
api/shared/helpers/jwt.php
api/shared/helpers/mail.php
api/shared/helpers/security_validation.php
api/shared/helpers/sms.php
api/shared/helpers/upload.php
api/shared/helpers/utils.php
api/v1/models/entities/validators/EntitiesAttributeValuesValidator.php
…and 8 more
Add typed signatures to all public methods for IDE support, static analysis, and safety.
[Code Quality]
Module: Code Quality
Methods between 50-80 LOC (63)
api/shared/core/DatabaseConnection.php getConnection() ~54 LOC
api/shared/helpers/RBAC.php fetchResourcePermissionsFromDb() ~51 LOC
api/shared/helpers/i18n.php loadTranslations() ~60 LOC
api/shared/helpers/notification_push.php getFcmAccessToken() ~68 LOC
api/shared/helpers/security.php validatePasswordStrength() ~63 LOC
api/shared/helpers/sms.php sendWithUnifonicCURL() ~59 LOC
api/shared/helpers/upload.php uploadImage() ~75 LOC
api/shared/security/SecurityMiddleware.php checkRateLimit() ~51 LOC
…and 55 more
Consider splitting for improved readability and testability.
[Dead Code]
Module: Code Quality
Potentially unused private methods (6 files)
api/shared/helpers/RBAC.php [invalidateCachePattern, logAudit]
api/shared/helpers/notification_push.php [handlePushChannel]
api/shared/helpers/security_utils.php [logError]
api/shared/security/SecurityRateLimiter.php [writeFile]
api/v1/auth/services/AuthService.php [upgradePasswordHash]
api/v1/models/categories/repositories/PdoCategoriesQueryTrait.php [logAction]
Verify these are not called via reflection or parent classes, then remove if unused.
[Runtime]
Module: Runtime
Simulation: 1000 requests / 197 route files — avg 0.0110 ms/req (total 0.011s)
