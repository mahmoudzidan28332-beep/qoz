🏗️ Architecture + Penetration Test Report
v5.0 — Generated: 2026-04-20 11:55:24 — Total: 1520.9 ms + Penetration Testing Module

🌙 Toggle Theme
Score
88
Grade
B
Good
Tests Run
51
Critical
0
Warnings
1
Info
489
Module	Time (ms)	Tests	Findings
Architecture Validation	205.1	7	0
Performance Validation	78.4	7	79
Multi-Tenant Safety	12.3	4	50
Security Validation	169.7	6	0
Penetration Testing	205.4	8	2
Type Safety	66.7	4	27
Configuration Safety	451.5	4	0
Exception Handling	42.5	3	260
Code Quality	269	6	70
Runtime Simulation	20.2	2	2
[Multi-Tenant] CVSS 6.5 CWE-284
Module: Multi-Tenant Safety
Query on 'addresses' may be missing a tenant_id filter
📁 api/v1/models/addresses/repositories/PdoAddressesRepository.php:72
💡 All queries on 'addresses' must include a tenant_id condition to prevent data leakage.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/shared/core/repositories/AuthRepository.php:15
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 4 time(s) — fetches unnecessary columns
📁 api/shared/core/repositories/RbacRepository.php:73
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/shared/core/repositories/UploadRepository.php:43
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/ads/repositories/PdoAdPlacementsRepository.php:24
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/attribute_types/repositories/PdoAttributeTypesRepository.php:41
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/auctions/repositories/PdoAuctionActivityLogRepository.php:28
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/auctions/repositories/PdoAuctionBidsRepository.php:27
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/auctions/repositories/PdoAuctionTranslationsRepository.php:20
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/auctions/repositories/PdoAuctionWatchersRepository.php:20
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 3 time(s) — fetches unnecessary columns
📁 api/v1/models/auctions/repositories/PdoAutoBidSettingsRepository.php:26
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/bad_words/repositories/PdoBadWordsRepository.php:112
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/banners/repositories/PdoBannersRepository.php:93
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/brands/repositories/PdoBrandsRepository.php:182
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/button_styles/repositories/PdoButtonStylesRepository.php:46
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/card_styles/repositories/PdoCardStylesRepository.php:49
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/cart_events/repositories/PdoCartEventsRepository.php:20
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/categories/repositories/PdoCategoriesRepository.php:166
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/color_settings/repositories/PdoColorSettingsRepository.php:46
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/commissions/repositories/PdoCommissionCreditNotesRepository.php:135
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/commissions/repositories/PdoCommissionInvoiceItemsRepository.php:119
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/commissions/repositories/PdoCommissionInvoicesRepository.php:144
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/commissions/repositories/PdoCommissionPaymentsRepository.php:143
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/commissions/repositories/PdoCommissionTransactionsRepository.php:144
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/core_events/repositories/PdoCoreEventRepository.php:92
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/currencies/repositories/PdoCurrenciesRepository.php:19
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/design_settings/repositories/PdoDesignSettingsRepository.php:46
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/discounts/repositories/PdoDiscountActionsRepository.php:26
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/discounts/repositories/PdoDiscountConditionsRepository.php:32
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/discounts/repositories/PdoDiscountExclusionsRepository.php:22
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/discounts/repositories/PdoDiscountRedemptionsRepository.php:21
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/discounts/repositories/PdoDiscountScopesRepository.php:26
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/discounts/repositories/PdoDiscountTranslationsRepository.php:22
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/discounts/repositories/PdoDiscountsRepository.php:136
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/entities/repositories/PdoEntitiesAttributesRepository.php:339
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/entities/repositories/PdoEntitiesWorkingHoursRepository.php:132
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/entities/repositories/PdoEntityTranslationsRepository.php:16
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/entities/repositories/PdoEntityTypesRepository.php:31
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/escrow/repositories/PdoEscrowDisputeEvidenceRepository.php:24
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/escrow/repositories/PdoEscrowDisputesRepository.php:24
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/escrow/repositories/PdoEscrowLedgerRepository.php:24
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/escrow/repositories/PdoEscrowStatusHistoryRepository.php:24
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/font_settings/repositories/PdoFontSettingsRepository.php:46
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/homepage_sections/repositories/PdoHomepageSectionsRepository.php:79
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 5 time(s) — fetches unnecessary columns
📁 api/v1/models/images/repositories/PdoImagesRepository.php:18
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/jobs/repositories/PdoJobApplicationQuestionsRepository.php:146
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/jobs/repositories/PdoJobSkillsRepository.php:145
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/languages/repositories/PdoLanguagesRepository.php:78
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 3 time(s) — fetches unnecessary columns
📁 api/v1/models/notification/repositories/PdoNotificationChannelsRepository.php:23
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 3 time(s) — fetches unnecessary columns
📁 api/v1/models/notification/repositories/PdoNotificationTypesRepository.php:28
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 7 time(s) — fetches unnecessary columns
📁 api/v1/models/notification/repositories/PdoUserDevicesRepository.php:36
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/notification_types/repositories/PdoNotificationTypeRepository.php:68
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/payment_methods/repositories/PdoPaymentMethodsRepository.php:36
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 4 time(s) — fetches unnecessary columns
📁 api/v1/models/permissions/repositories/PdoResourcePermissionsRepository.php:27
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 4 time(s) — fetches unnecessary columns
📁 api/v1/models/platform_report/repositories/PdoPlatformReportRepository (1).php:31
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/pos_sessions/repositories/PdoPosSessionsRepository.php:230
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/products/repositories/PdoProductReviewsRepository.php:13
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/products/repositories/PdoProductStockAlertsRepository.php:15
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/products/repositories/PdoProduct_categoriesRepository.php:29
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 3 time(s) — fetches unnecessary columns
📁 api/v1/models/products/repositories/PdoProduct_physical_attributesRepository.php:42
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 3 time(s) — fetches unnecessary columns
📁 api/v1/models/products/repositories/PdoProduct_typesRepository.php:48
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/queues/repositories/PdoQueuesRepository.php:46
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 3 time(s) — fetches unnecessary columns
📁 api/v1/models/seo_meta/repositories/PdoSeoMetaRepository.php:20
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/store_pages/repositories/PdoStorePagesRepository.php:224
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/subscriptions/repositories/PdoEscrowPaymentsRepository.php:113
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/subscriptions/repositories/PdoEscrowTransactionsRepository.php:142
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/subscriptions/repositories/PdoSubscriptionInvoicesRepository.php:165
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/subscriptions/repositories/PdoSubscriptionPaymentsRepository.php:129
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 3 time(s) — fetches unnecessary columns
📁 api/v1/models/subscriptions/repositories/PdoSubscriptionPlanTranslationsRepository.php:21
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/subscriptions/repositories/PdoSubscriptionPlansRepository.php:132
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/subscriptions/repositories/PdoSubscriptionsRepository.php:132
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 2 time(s) — fetches unnecessary columns
📁 api/v1/models/system_settings/repositories/PdoSystemSettingsRepository.php:41
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/tenant_users/repositories/PdoTenant_usersRepository.php:233
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 4 time(s) — fetches unnecessary columns
📁 api/v1/models/themes/repositories/PdoThemesRepository.php:40
💡 Explicitly list only the columns your application needs.
[Performance]
Module: Performance Validation
SELECT * found 1 time(s) — fetches unnecessary columns
📁 api/v1/models/tickets/repositories/PdoTicketCategoriesRepository.php:126
💡 Explicitly list only the columns your application needs.
[Index Usage]
Module: Performance Validation
Function applied to column in WHERE clause — may prevent index usage
📁 api/shared/core/repositories/AiRepository.php
💡 Use a virtual/computed column or rewrite to avoid wrapping indexed columns in functions.
[Missing Index Hint]
Module: Performance Validation
Multiple FK columns in WHERE (tenant_id, entity_id, user_id…) — verify DB indexes exist
📁 api/v1/models/carts/repositories/PdoCartsRepository.php
💡 Ensure all foreign key columns used in WHERE clauses have database indexes.
[Missing Index Hint]
Module: Performance Validation
Multiple FK columns in WHERE (tenant_id, category_id, owner_id…) — verify DB indexes exist
📁 api/v1/models/categories/repositories/PdoCategoriesRepository.php
💡 Ensure all foreign key columns used in WHERE clauses have database indexes.
[Missing Index Hint]
Module: Performance Validation
Multiple FK columns in WHERE (tenant_id, entity_id, cashier_user_id…) — verify DB indexes exist
📁 api/v1/models/pos_sessions/repositories/PdoPosSessionsRepository.php
💡 Ensure all foreign key columns used in WHERE clauses have database indexes.
[Missing Index Hint]
Module: Performance Validation
Multiple FK columns in WHERE (user_id, wishlist_id, owner_id…) — verify DB indexes exist
📁 api/v1/models/wishlists/repositories/PdoWishlistsRepository.php
💡 Ensure all foreign key columns used in WHERE clauses have database indexes.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'getFcmTokensForDevices()' may lack tenant scoping
📁 api/shared/core/repositories/NotificationRepository.php:61
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/auctions/repositories/PdoAuctionActivityLogRepository.php:20
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/auctions/repositories/PdoAuctionBidsRepository.php:19
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/auctions/repositories/PdoAuctionTranslationsRepository.php:17
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/auctions/repositories/PdoAuctionWatchersRepository.php:17
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/bad_words/repositories/PdoBadWordsRepository.php:18
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'list()' may lack tenant scoping
📁 api/v1/models/commissions/repositories/PdoCommissionCreditNotesRepository.php:28
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'list()' may lack tenant scoping
📁 api/v1/models/commissions/repositories/PdoCommissionInvoiceItemsRepository.php:27
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'list()' may lack tenant scoping
📁 api/v1/models/commissions/repositories/PdoCommissionInvoicesRepository.php:29
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'list()' may lack tenant scoping
📁 api/v1/models/commissions/repositories/PdoCommissionPaymentsRepository.php:28
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'list()' may lack tenant scoping
📁 api/v1/models/commissions/repositories/PdoCommissionTransactionsRepository.php:27
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'list()' may lack tenant scoping
📁 api/v1/models/commissions/repositories/PdoEntityFinancialBalancesRepository.php:32
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'find()' may lack tenant scoping
📁 api/v1/models/discounts/repositories/PdoDiscountTranslationsRepository.php:32
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'list()' may lack tenant scoping
📁 api/v1/models/discounts/repositories/PdoDiscountsRepository.php:30
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/entities/repositories/PdoEntitiesAttributeValuesRepository.php:26
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/entities/repositories/PdoEntitiesAttributesRepository.php:27
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/entities/repositories/PdoEntitiesWorkingHoursRepository.php:30
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/entities/repositories/PdoEntityProductVariantsRepository.php:61
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/entities/repositories/PdoEntityProductsRepository.php:73
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'getByEntity()' may lack tenant scoping
📁 api/v1/models/entities/repositories/PdoEntityTranslationsRepository.php:13
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/entities/repositories/PdoEntityTypesRepository.php:24
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'getWorkingHours()' may lack tenant scoping
📁 api/v1/models/entity_context/repositories/PdoEntityContextRepository.php:85
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'getImageProcessingSettings()' may lack tenant scoping
📁 api/v1/models/images/repositories/PdoImagesRepository.php:373
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/jobs/repositories/PdoJobAlertsRepository.php:35
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/jobs/repositories/PdoJobApplicationAnswersRepository.php:21
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/jobs/repositories/PdoJobApplicationQuestionsRepository.php:27
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'find()' may lack tenant scoping
📁 api/v1/models/jobs/repositories/PdoJobApplicationsRepository.php:224
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'find()' may lack tenant scoping
📁 api/v1/models/jobs/repositories/PdoJobInterviewsRepository.php:213
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/jobs/repositories/PdoJobSkillsRepository.php:26
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'getTranslations()' may lack tenant scoping
📁 api/v1/models/jobs/repositories/PdoJobsRepository.php:229
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/notification/repositories/PdoUserDevicesRepository.php:29
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'getTranslations()' may lack tenant scoping
📁 api/v1/models/product_variants/repositories/PdoProductVariantsRepository.php:166
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/products/repositories/PdoProductReviewsRepository.php:12
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'find()' may lack tenant scoping
📁 api/v1/models/products/repositories/PdoProductStockAlertsRepository.php:47
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/products/repositories/PdoProduct_physical_attributesRepository.php:35
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/products/repositories/PdoProduct_typesRepository.php:39
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'list()' may lack tenant scoping
📁 api/v1/models/stock_movements/repositories/PdoStockMovementsRepository.php:27
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'findSection()' may lack tenant scoping
📁 api/v1/models/store_pages/repositories/PdoStorePagesRepository.php:194
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/subscriptions/repositories/PdoEscrowPaymentsRepository.php:28
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'list()' may lack tenant scoping
📁 api/v1/models/subscriptions/repositories/PdoEscrowTransactionsRepository.php:30
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'list()' may lack tenant scoping
📁 api/v1/models/subscriptions/repositories/PdoSubscriptionInvoicesRepository.php:29
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/subscriptions/repositories/PdoSubscriptionPaymentsRepository.php:29
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'find()' may lack tenant scoping
📁 api/v1/models/subscriptions/repositories/PdoSubscriptionPlanTranslationsRepository.php:29
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'list()' may lack tenant scoping
📁 api/v1/models/subscriptions/repositories/PdoSubscriptionPlansRepository.php:29
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'findPendingByTokenHash()' may lack tenant scoping
📁 api/v1/models/users_account/repositories/PdoUserPhoneVerificationsRepository.php:19
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant]
Module: Multi-Tenant Safety
Repository method 'all()' may lack tenant scoping
📁 api/v1/models/users_account/repositories/PdoUsersRepository.php:17
💡 Add a $tenantId parameter or apply a tenant_id filter inside the method.
[Multi-Tenant Chain]
Module: Multi-Tenant Safety
'entity_product_variants' uses entity_id — verify entity is tenant-scoped in query/service layer
📁 api/v1/models/entities/repositories/PdoEntityProductVariantsRepository.php:75
💡 Ensure the entity_id value is always fetched with a tenant_id filter upstream.
[Multi-Tenant Chain]
Module: Multi-Tenant Safety
'entity_products' uses entity_id — verify entity is tenant-scoped in query/service layer
📁 api/v1/models/entities/repositories/PdoEntityProductsRepository.php:88
💡 Ensure the entity_id value is always fetched with a tenant_id filter upstream.
[Multi-Tenant Chain]
Module: Multi-Tenant Safety
'entity_translations' uses entity_id — verify entity is tenant-scoped in query/service layer
📁 api/v1/models/entities/repositories/PdoEntityTranslationsRepository.php:16
💡 Ensure the entity_id value is always fetched with a tenant_id filter upstream.
[IDOR]
Module: Penetration Testing
Route uses dynamic resource ID without visible ownership verification
📁 api/v1/routes/public/jobs.php
💡 After fetching a resource by ID, verify it belongs to the authenticated user/tenant.
[IDOR]
Module: Penetration Testing
Route uses dynamic resource ID without visible ownership verification
📁 api/v1/routes/public/vendors.php
💡 After fetching a resource by ID, verify it belongs to the authenticated user/tenant.
[Type Safety]
Module: Type Safety
Missing declare(strict_types=1)
📁 api/shared/core/BootstrapRateLimiter.php:1
💡 Add declare(strict_types=1); at the top of every PHP file for type safety.
[Type Safety]
Module: Type Safety
Missing declare(strict_types=1)
📁 api/shared/helpers/color_utils.php:1
💡 Add declare(strict_types=1); at the top of every PHP file for type safety.
[Type Safety]
Module: Type Safety
Public method(s) missing return type: setPDO, setOpenAIKey, getProductRecommendations +2 more
📁 api/shared/helpers/ai.php
💡 Add explicit return types (e.g. : array, : string, : void) to all public methods.
[Type Safety]
Module: Type Safety
Public method(s) missing return type: encode, decode, getBearerToken +17 more
📁 api/shared/helpers/jwt.php
💡 Add explicit return types (e.g. : array, : string, : void) to all public methods.
[Type Safety]
Module: Type Safety
Public method(s) missing return type: setPDO, send, sendWelcomeEmail +5 more
📁 api/shared/helpers/mail.php
💡 Add explicit return types (e.g. : array, : string, : void) to all public methods.
[Type Safety]
Module: Type Safety
Public method(s) missing return type: sanitizeInput, validateSaudiPhone
📁 api/shared/helpers/security_validation.php
💡 Add explicit return types (e.g. : array, : string, : void) to all public methods.
[Type Safety]
Module: Type Safety
Public method(s) missing return type: setPDO, send, sendOTP +10 more
📁 api/shared/helpers/sms.php
💡 Add explicit return types (e.g. : array, : string, : void) to all public methods.
[Type Safety]
Module: Type Safety
Public method(s) missing return type: setPDO, uploadImage, uploadDocument +5 more
📁 api/shared/helpers/upload.php
💡 Add explicit return types (e.g. : array, : string, : void) to all public methods.
[Type Safety]
Module: Type Safety
Public method(s) missing return type: formatDate, timeAgo, isFutureDate +37 more
📁 api/shared/helpers/utils.php
💡 Add explicit return types (e.g. : array, : string, : void) to all public methods.
[Type Safety]
Module: Type Safety
Public method(s) missing return type: convertValueByType
📁 api/v1/models/entities/validators/EntitiesAttributeValuesValidator.php
💡 Add explicit return types (e.g. : array, : string, : void) to all public methods.
[Type Safety]
Module: Type Safety
Public method(s) missing return type: count
📁 api/v1/models/tenant_users/services/Tenant_usersService.php
💡 Add explicit return types (e.g. : array, : string, : void) to all public methods.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): get, set
📁 api/shared/core/ConfigLoader.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): set
📁 api/shared/core/SettingsManager.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): setOpenAIKey, getProductRecommendations, chatbotResponse
📁 api/shared/helpers/ai.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): t
📁 api/shared/helpers/i18n.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): encode, decode, createRefreshToken
📁 api/shared/helpers/jwt.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): send, sendWelcomeEmail, sendOTP
📁 api/shared/helpers/mail.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): sanitizeInput, validateInteger, validateFloat
📁 api/shared/helpers/security_validation.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): send, sendOTP, sendOrderNotification
📁 api/shared/helpers/sms.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): uploadImage, uploadDocument, uploadMultiple
📁 api/shared/helpers/upload.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): formatDate, timeAgo, isFutureDate
📁 api/shared/helpers/utils.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): convertValueByType
📁 api/v1/models/entities/validators/EntitiesAttributeValuesValidator.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): findByUnique
📁 api/v1/models/permissions/repositories/PdoResourcePermissionsRepository.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): validateDeleteId
📁 api/v1/models/permissions/validators/ResourcePermissionsValidator.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): createPublicQuestion
📁 api/v1/models/products/repositories/PdoProductQuestionsRepository.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): createPublicReview
📁 api/v1/models/products/repositories/PdoProductReviewsRepository.php
💡 Add type declarations to all method parameters.
[Type Safety]
Module: Type Safety
Untyped parameter(s) in public method(s): list, count
📁 api/v1/models/tenant_users/services/Tenant_usersService.php
💡 Add type declarations to all method parameters.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/core/BootstrapRateLimiter.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/core/EventDispatcher.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/core/QueueManager.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/core/ResponseFormatter.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/core/plugin_manager.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/helpers/AuditLogger.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/helpers/CSRF.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/helpers/RBAC.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/helpers/RedisHelper.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/helpers/get_user_permissions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/helpers/jwt.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/helpers/mail.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/helpers/notification.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/helpers/notification_push.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/helpers/security.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/helpers/sms.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/helpers/upload.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/helpers/utils.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/security/RateLimiter.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/security/SecurityValidators.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/services/PermissionService.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/shared/ui/AdminUiThemeLoader.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/auth/controllers/AuthController.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/auth/services/AuthService.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/audit_logs/services/AuditLogsService.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/banners/repositories/PdoBannersRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/brands/repositories/PdoBrandsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/button_styles/button_styles.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/button_styles/repositories/PdoButtonStylesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/card_styles/card_styles.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/card_styles/repositories/PdoCardStylesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/categories/repositories/PdoCategoriesQueryTrait.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/categories/repositories/PdoCategoriesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/categories/repositories/PdoTenantCategoriesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/categories/services/CategoriesService.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/category_attributes/repositories/PdoCategoryAttributesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/cities/repositories/PdoCitiesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/color_settings/repositories/PdoColorSettingsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/commissions/repositories/PdoCommissionCreditNotesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/commissions/repositories/PdoCommissionInvoicesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/commissions/repositories/PdoCommissionPaymentsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/commissions/repositories/PdoCommissionTransactionsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/countries/repositories/PdoCountriesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/design_settings/design_settings.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/design_settings/repositories/PdoDesignSettingsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/entities/repositories/PdoEntitiesAttributeValuesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/entities/repositories/PdoEntitiesAttributesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/entities/repositories/PdoEntityBankAccountsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/entities/repositories/PdoEntityPaymentMethodsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/entities/repositories/PdoEntityProductVariantsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/entities/repositories/PdoEntityProductsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/font_settings/font_settings.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/font_settings/repositories/PdoFontSettingsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/homepage_sections/repositories/PdoHomepageSectionsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/images/repositories/PdoImagesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/images/services/ImagesService.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/jobs/repositories/PdoJobApplicationAnswersRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/jobs/repositories/PdoJobApplicationQuestionsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/jobs/repositories/PdoJobCategoryTranslationsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/jobs/services/JobAlertsService.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/notification_types/notification_types.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/permissions/controllers/ResourcePermissionsController.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/permissions/repositories/PdoResourcePermissionsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/pos_sessions/repositories/PdoPosSessionsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/products/repositories/PdoProductAttributeAssignmentsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/products/repositories/PdoProductAttributeValuesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/products/repositories/PdoProductAttributesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/products/repositories/PdoProductRelationsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/queues/controllers/QueuesController.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/search_logs/repositories/PdoSearchSuggestRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/stock_movements/repositories/PdoStockMovementsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/store_pages/repositories/PdoStorePagesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/subscriptions/repositories/PdoSubscriptionsRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/tenants/services/TenantsService.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/themes/repositories/PdoThemesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/tickets/repositories/PdoTicketCategoriesRepository.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/models/users_account/services/UsersService.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/account.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/ad_campaigns.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/ad_payments.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/ad_placement_items.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/ad_placements.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/ad_stats.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/ad_translations.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/addresses.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/ads.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/attribute_types.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/attributes.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/auction_activity_log.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/auction_bids.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/auction_translations.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/auction_watchers.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/auctions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/audit_logs.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/auto_bid_settings.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/bad_words.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/banners.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/brands.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/button_styles.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/card_styles.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/cart_events.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/cart_items.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/carts.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/categories-tenants.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/categories.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/category_attributes.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/cities.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/color_settings.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/commission_credit_notes.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/commission_invoice_items.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/commission_invoices.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/commission_payments.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/commission_transactions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/core_events.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/countries.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/country_taxes.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/coupons.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/currencies.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/delivery_orders.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/delivery_providers.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/delivery_tracking.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/delivery_zones.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/design_settings.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/discount_actions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/discount_conditions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/discount_exclusions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/discount_redemptions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/discount_scopes.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/discount_translations.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/discounts.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/driver_locations.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/entities.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/entities_attribute_values.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/entities_attributes.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/entities_working_hours.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/entity_bank_accounts.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/entity_financial_balances.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/entity_payment_methods.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/entity_product_variants.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/entity_products.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/entity_settings.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/entity_translations.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/entity_types.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/escrow_dispute_evidence.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/escrow_disputes.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/escrow_ledger.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/escrow_payments.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/escrow_status_history.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/escrow_transactions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/font_settings.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/homepage_sections.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/image-types.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/images.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/job_alerts.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/job_application_answers.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/job_application_questions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/job_applications.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/job_categories.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/job_interviews.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/job_skills.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/jobs.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/languages.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/notification_channels.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/notification_counters.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/notification_deliveries.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/notification_types.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/notifications.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/order_items.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/order_reviews.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/order_status_history.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/orders.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/payment_methods.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/payments.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/permissions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/platform_report.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/pos_sessions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_attribute_assignments.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_attribute_translations.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_attribute_value_translations.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_attribute_values.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_attributes.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_bundle-items.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_bundles.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_categories.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_comparison_items.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_comparisons.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_meta.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_physical_attributes.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_pricing.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_questions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_relations.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_reviews.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_stock_alerts.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_stock_movements.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_translations.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_types.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_variant_attributes.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/product_variants.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/products.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/provider_zones.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/addresses.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/ads.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/auctions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/bundles.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/cart.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/compare.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/contact.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/discounts.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/entity.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/entity_context.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/job_applications.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/languages.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/notifications.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/orders.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/products.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/recent.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/register.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/returns.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/search_suggest.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/support_tickets.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/ticket_categories.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/user_devices.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/public/wishlist.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/queues.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/resource_permissions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/return_items.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/return_status_history.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/returns.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/reviews.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/role_permissions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/roles.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/seo_meta.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/services.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/store_pages.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/subscription_invoices.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/subscription_payments.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/subscription_plan_translations.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/subscription_plans.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/subscriptions.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/support.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/support_tickets.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/system_settings.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/tenant_domains.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/tenant_users.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/tenants.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/themes.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/ticket_categories.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/ticket_messages.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/ticket_status_history.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/timezones.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/units.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/user.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/user_devices.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/users.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/users_account.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Over-broad catch(Exception) or catch(Throwable) detected
📁 api/v1/routes/verify_phone.php
💡 Catch specific exception types (e.g. PDOException, RuntimeException) for clarity.
[Exception Handling]
Module: Exception Handling
Generic Exception thrown — consider domain-specific exception classes
📁 api/shared/helpers/RedisHelper.php
💡 Create descriptive exception classes (e.g. NotFoundException, ValidationException).
[Exception Handling]
Module: Exception Handling
Generic Exception thrown — consider domain-specific exception classes
📁 api/v1/models/queues/services/QueuesService.php
💡 Create descriptive exception classes (e.g. NotFoundException, ValidationException).
[Exception Handling]
Module: Exception Handling
Generic Exception thrown — consider domain-specific exception classes
📁 api/v1/routes/queues.php
💡 Create descriptive exception classes (e.g. NotFoundException, ValidationException).
[Large Method]
Module: Code Quality
Method 'getConnection()' has ~54 LOC
📁 api/shared/core/DatabaseConnection.php:8
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'fetchResourcePermissionsFromDb()' has ~51 LOC
📁 api/shared/helpers/RBAC.php:284
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'loadTranslations()' has ~60 LOC
📁 api/shared/helpers/i18n.php:162
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'getFcmAccessToken()' has ~68 LOC
📁 api/shared/helpers/notification_push.php:233
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'validatePasswordStrength()' has ~63 LOC
📁 api/shared/helpers/security.php:140
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'sendWithUnifonicCURL()' has ~59 LOC
📁 api/shared/helpers/sms.php:116
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'uploadImage()' has ~75 LOC
📁 api/shared/helpers/upload.php:47
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'checkRateLimit()' has ~51 LOC
📁 api/shared/security/SecurityMiddleware.php:198
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'validate()' has ~52 LOC
📁 api/shared/security/SecurityValidators.php:141
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'getResourcePermissions()' has ~67 LOC
📁 api/shared/services/PermissionService.php:189
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'list()' has ~60 LOC
📁 api/v1/models/ad_stats/repositories/PdoAdStatRepository.php:27
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'validate()' has ~55 LOC
📁 api/v1/models/ads/validators/AdPlacementsValidator.php:8
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~64 LOC
📁 api/v1/models/auctions/repositories/PdoAuctionsRepository.php:139
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~65 LOC
📁 api/v1/models/banners/repositories/PdoBannersRepository.php:105
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'validate()' has ~57 LOC
📁 api/v1/models/banners/validators/BannersValidator.php:14
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~73 LOC
📁 api/v1/models/button_styles/repositories/PdoButtonStylesRepository.php:80
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'validate()' has ~66 LOC
📁 api/v1/models/button_styles/validators/ButtonStylesValidator.php:8
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~76 LOC
📁 api/v1/models/card_styles/repositories/PdoCardStylesRepository.php:83
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'validate()' has ~55 LOC
📁 api/v1/models/card_styles/validators/CardStylesValidator.php:26
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'validate()' has ~59 LOC
📁 api/v1/models/cart_items/validators/CartItemsValidator.php:10
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'validate()' has ~55 LOC
📁 api/v1/models/carts/validators/CartsValidator.php:10
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'countAll()' has ~63 LOC
📁 api/v1/models/categories/repositories/PdoCategoriesQueryTrait.php:14
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'validate()' has ~68 LOC
📁 api/v1/models/categories/validators/CategoriesValidator.php:6
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'recalculate()' has ~69 LOC
📁 api/v1/models/commissions/repositories/PdoEntityFinancialBalancesRepository.php:171
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~57 LOC
📁 api/v1/models/country_taxes/repositories/PdoCountryTaxesRepository.php:80
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~60 LOC
📁 api/v1/models/entities/repositories/PdoEntityBankAccountsRepository.php:172
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'getEntityProducts()' has ~71 LOC
📁 api/v1/models/entities/repositories/PdoEntityProductsRepository.php:206
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'getEntitiesWithContext()' has ~68 LOC
📁 api/v1/models/entity_context/repositories/PdoEntityContextRepository.php:13
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~60 LOC
📁 api/v1/models/escrow/repositories/PdoEscrowDisputesRepository.php:93
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~72 LOC
📁 api/v1/models/escrow/repositories/PdoEscrowTransactionsRepository.php:147
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~55 LOC
📁 api/v1/models/font_settings/repositories/PdoFontSettingsRepository.php:80
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~80 LOC
📁 api/v1/models/homepage_sections/repositories/PdoHomepageSectionsRepository.php:90
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'validate()' has ~72 LOC
📁 api/v1/models/homepage_sections/validators/HomepageSectionsValidator.php:8
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'calculateDimensions()' has ~51 LOC
📁 api/v1/models/images/services/ImagesService.php:451
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~54 LOC
📁 api/v1/models/jobs/repositories/PdoJobAlertsRepository.php:164
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~51 LOC
📁 api/v1/models/jobs/repositories/PdoJobApplicationQuestionsRepository.php:170
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'count()' has ~59 LOC
📁 api/v1/models/jobs/repositories/PdoJobApplicationsRepository.php:148
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~67 LOC
📁 api/v1/models/jobs/repositories/PdoJobApplicationsRepository.php:334
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'all()' has ~51 LOC
📁 api/v1/models/jobs/repositories/PdoJobCategoriesRepository.php:18
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'count()' has ~57 LOC
📁 api/v1/models/jobs/repositories/PdoJobInterviewsRepository.php:140
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~55 LOC
📁 api/v1/models/jobs/repositories/PdoJobInterviewsRepository.php:293
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'validate()' has ~67 LOC
📁 api/v1/models/jobs/validators/JobAlertsValidator.php:17
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~52 LOC
📁 api/v1/models/notification/repositories/PdoNotificationsRepository.php:130
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'aggregateAdsPerformance()' has ~56 LOC
📁 api/v1/models/platform_report/repositories/PlatformReportAggregationTrait.php:68
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'validate()' has ~53 LOC
📁 api/v1/models/pos_sessions/validators/PosSessionsValidator.php:8
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~58 LOC
📁 api/v1/models/products/repositories/PdoProductAttributesRepository.php:105
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~61 LOC
📁 api/v1/models/products/repositories/PdoProductBundlesRepository.php:168
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~59 LOC
📁 api/v1/models/products/repositories/PdoProductPricingRepository.php:122
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'update()' has ~52 LOC
📁 api/v1/models/products/repositories/PdoProductRelationsRepository.php:165
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'saveForProduct()' has ~56 LOC
📁 api/v1/models/products/repositories/PdoProduct_physical_attributesRepository.php:177
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'saveForVariant()' has ~56 LOC
📁 api/v1/models/products/repositories/PdoProduct_physical_attributesRepository.php:242
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~76 LOC
📁 api/v1/models/products/repositories/PdoProductsRepository.php:182
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'handleRequest()' has ~74 LOC
📁 api/v1/models/queues/controllers/QueuesController.php:13
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'create()' has ~59 LOC
📁 api/v1/models/stock_movements/repositories/PdoStockMovementsRepository.php:165
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'create()' has ~55 LOC
📁 api/v1/models/subscriptions/repositories/PdoSubscriptionsRepository.php:159
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'upgrade()' has ~60 LOC
📁 api/v1/models/subscriptions/repositories/PdoSubscriptionsRepository.php:226
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'count()' has ~57 LOC
📁 api/v1/models/tenant_users/repositories/PdoTenant_usersRepository.php:110
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~58 LOC
📁 api/v1/models/tenant_users/repositories/PdoTenant_usersRepository.php:300
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'create()' has ~72 LOC
📁 api/v1/models/tenant_users/services/Tenant_usersService.php:41
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~52 LOC
📁 api/v1/models/themes/repositories/PdoThemesRepository.php:93
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~52 LOC
📁 api/v1/models/tickets/repositories/PdoSupportTicketsRepository.php:126
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~73 LOC
📁 api/v1/models/tickets/repositories/PdoTicketCategoriesRepository.php:133
💡 Consider breaking this method into smaller parts.
[Large Method]
Module: Code Quality
Method 'save()' has ~60 LOC
📁 api/v1/models/users_account/repositories/PdoUsersRepository.php:114
💡 Consider breaking this method into smaller parts.
[Duplicated Logic]
Module: Code Quality
'JSON response building' repeated across 8 files
💡 Extract into a shared Trait, Service method, or utility class.
[Dead Code]
Module: Code Quality
Potentially unused private method(s): invalidateCachePattern, logAudit
📁 api/shared/helpers/RBAC.php
💡 Verify these methods are not called via reflection or dynamic dispatch, then remove if unused.
[Dead Code]
Module: Code Quality
Potentially unused private method(s): handlePushChannel
📁 api/shared/helpers/notification_push.php
💡 Verify these methods are not called via reflection or dynamic dispatch, then remove if unused.
[Dead Code]
Module: Code Quality
Potentially unused private method(s): logError
📁 api/shared/helpers/security_utils.php
💡 Verify these methods are not called via reflection or dynamic dispatch, then remove if unused.
[Dead Code]
Module: Code Quality
Potentially unused private method(s): writeFile
📁 api/shared/security/SecurityRateLimiter.php
💡 Verify these methods are not called via reflection or dynamic dispatch, then remove if unused.
[Dead Code]
Module: Code Quality
Potentially unused private method(s): upgradePasswordHash
📁 api/v1/auth/services/AuthService.php
💡 Verify these methods are not called via reflection or dynamic dispatch, then remove if unused.
[Dead Code]
Module: Code Quality
Potentially unused private method(s): logAction
📁 api/v1/models/categories/repositories/PdoCategoriesQueryTrait.php
💡 Verify these methods are not called via reflection or dynamic dispatch, then remove if unused.
[File Size]
Module: Runtime Simulation
Large file (55.4 KB) — may slow autoload/include
📁 api/tests/security_comprehensive_test.php
💡 Split into smaller, lazily-loaded modules.
[Runtime Summary]
Module: Runtime Simulation
Simulation: 1000 requests in 0.012s (avg 0.0116 ms/req, 197 route files)
