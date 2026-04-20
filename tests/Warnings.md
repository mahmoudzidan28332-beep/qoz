🏗️ Architecture + Penetration Test Report
v5.0 — Generated: 2026-04-20 10:05:32 — Total: 1971.6 ms + Penetration Testing Module

🌙 Toggle Theme
Score
60
Grade
C
Fair
Tests Run
51
Critical
0
Warnings
37
Info
493
Module	Time (ms)	Tests	Findings
Architecture Validation	218.3	7	0
Performance Validation	147.8	7	80
Multi-Tenant Safety	22.9	4	50
Security Validation	332.1	6	0
Penetration Testing	363.7	8	37
Type Safety	72.1	4	27
Configuration Safety	569.8	4	0
Exception Handling	30.9	3	264
Code Quality	190.6	6	70
Runtime Simulation	23.5	2	2
[Performance]
Module: Performance Validation
Correlated subquery inside JOIN ON clause — executes once per row
📁 api/v1/models/products/repositories/PdoProductsRepository.php
💡 Rewrite as a derived table (subquery in FROM) or a CTE (WITH …).
[Multi-Tenant] CVSS 6.5 CWE-284
Module: Multi-Tenant Safety
Query on 'addresses' may be missing a tenant_id filter
📁 api/v1/models/addresses/repositories/PdoAddressesRepository.php:73
💡 All queries on 'addresses' must include a tenant_id condition to prevent data leakage.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/models/attribute_types/attribute_types.php:62
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/models/country_taxes/country_taxes.php:74
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/models/currencies/currencies.php:57
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/models/languages/languages.php:50
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/attribute_types.php:62
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/auction_bids.php:80
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/auction_translations.php:63
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/auction_watchers.php:63
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/auto_bid_settings.php:79
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/commission_credit_notes.php:82
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/commission_invoice_items.php:71
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/commission_invoices.php:84
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/commission_payments.php:84
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/commission_transactions.php:80
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/country_taxes.php:74
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/currencies.php:57
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/discounts.php:80
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/image-types.php:55
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/payment_methods.php:66
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/product_attribute_assignments.php:73
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/product_attribute_translations.php:57
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/product_attribute_value_translations.php:57
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/product_attribute_values.php:57
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/product_attributes.php:57
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/product_categories.php:65
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/product_physical_attributes.php:84
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/product_stock_movements.php:107
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/subscription_invoices.php:94
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/subscription_payments.php:87
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/subscription_plans.php:75
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/subscriptions.php:77
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/tenant_domains.php:77
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/units.php:34
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/users.php:123
💡 Whitelist fields explicitly before writing: only accept known keys.
[Mass Assignment] CVSS 7.5 CWE-915
Module: Penetration Testing
json_decode() result passed to write method without field whitelisting
📁 api/v1/routes/users_account.php:123
💡 Whitelist fields explicitly before writing: only accept known keys.
