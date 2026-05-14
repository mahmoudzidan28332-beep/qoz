our SQL query has been executed successfully.
DESCRIBE entities;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
parent_id
bigint(20) unsigned
YES
MUL
NULL
tenant_id
int(10) unsigned
NO
MUL
NULL
branch_code
varchar(50)
YES
NULL
user_id
int(11) unsigned
NO
MUL
NULL
store_name
varchar(255)
NO
NULL
slug
varchar(255)
NO
UNI
NULL
vendor_type
varchar(50)
YES
store
store_type
enum('individual','company','brand')
YES
individual
registration_number
varchar(100)
YES
NULL
tax_number
varchar(100)
YES
NULL
phone
varchar(45)
NO
NULL
mobile
varchar(45)
YES
NULL
email
varchar(191)
NO
NULL
website_url
varchar(500)
YES
NULL
timezone_id
int(10) unsigned
YES
MUL
NULL
status
enum('pending','approved','suspended','rejected')
YES
MUL
pending
suspension_reason
text
YES
NULL
is_verified
tinyint(1)
YES
0
joined_at
datetime
YES
current_timestamp()
approved_at
datetime
YES
NULL
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_translations;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
entity_id
bigint(20) unsigned
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
store_name
varchar(255)
NO
NULL
branch_code
varchar(50)
YES
NULL
meta_title
varchar(255)
YES
NULL
meta_description
text
YES
NULL
description
text
YES
NULL
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entities_attributes;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20)
NO
PRI
NULL
slug
varchar(100)
NO
UNI
NULL
attribute_type
enum('text','number','select','boolean')
YES
text
is_required
tinyint(1)
YES
0
sort_order
int(11)
YES
0
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entities_attribute_translations;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20)
NO
PRI
NULL
attribute_id
bigint(20)
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
name
varchar(255)
NO
NULL
description
text
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entities_attribute_values;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
entity_id
bigint(20) unsigned
NO
MUL
NULL
attribute_id
bigint(20)
NO
MUL
NULL
value
text
NO
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_bank_accounts;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
entity_id
bigint(20) unsigned
NO
MUL
NULL
bank_name
varchar(255)
NO
NULL
account_holder_name
varchar(255)
NO
NULL
account_number
varbinary(255)
NO
NULL
iban
varbinary(255)
YES
NULL
swift_code
varbinary(255)
YES
NULL
is_primary
tinyint(1)
YES
0
is_verified
tinyint(1)
YES
0
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entities_working_hours;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
entity_id
bigint(20) unsigned
NO
MUL
NULL
day_of_week
tinyint(3) unsigned
NO
NULL
is_open
tinyint(1)
NO
1
open_time
time
YES
NULL
close_time
time
YES
NULL
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_financial_balances;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
entity_id
bigint(20) unsigned
NO
PRI
NULL
tenant_id
int(10) unsigned
NO
MUL
NULL
total_transactions
int(11)
YES
0
total_sales_count
int(11)
YES
0
total_refunds_count
int(11)
YES
0
total_sales_amount
decimal(15,2)
YES
0.00
total_refunds_amount
decimal(15,2)
YES
0.00
net_sales
decimal(15,2)
YES
0.00
total_commission
decimal(15,2)
YES
0.00
total_vat
decimal(15,2)
YES
0.00
total_net_commission
decimal(15,2)
YES
0.00
total_invoiced
decimal(15,2)
YES
0.00
total_paid
decimal(15,2)
YES
0.00
total_balance
decimal(15,2)
YES
0.00
pending_balance
decimal(15,2)
YES
0.00
invoiced_balance
decimal(15,2)
YES
0.00
total_invoices
int(11)
YES
0
total_payments
int(11)
YES
0
total_credit_notes
int(11)
YES
0
last_transaction_date
datetime
YES
NULL
last_invoice_date
datetime
YES
NULL
last_payment_date
datetime
YES
NULL
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_logs;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
tenant_id
int(10) unsigned
YES
MUL
NULL
user_id
int(11) unsigned
YES
MUL
NULL
entity_type
varchar(100)
NO
MUL
NULL
entity_id
bigint(20) unsigned
YES
NULL
action
enum('create','update','delete')
NO
NULL
changes
longtext
YES
NULL
ip_address
varchar(45)
YES
NULL
created_at
datetime
NO
current_timestamp()
updated_at
datetime
NO
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_payment_methods;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
entity_id
bigint(20) unsigned
NO
MUL
NULL
account_email
varbinary(191)
YES
NULL
account_id
varbinary(255)
YES
NULL
is_active
tinyint(1)
YES
1
created_at
datetime
YES
current_timestamp()
payment_method_id
bigint(20) unsigned
NO
MUL
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_settings;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
entity_id
bigint(20) unsigned
NO
PRI
NULL
auto_accept_orders
tinyint(1)
YES
0
allow_cod
tinyint(1)
YES
0
min_order_amount
decimal(10,2)
YES
0.00
preparation_time_minutes
int(11)
YES
0
allow_online_booking
tinyint(1)
YES
0
booking_window_days
int(11)
YES
0
max_bookings_per_slot
int(11)
YES
0
booking_cancellation_allowed
tinyint(1)
YES
1
allow_preorders
tinyint(1)
YES
0
max_daily_orders
int(11)
YES
0
is_visible
tinyint(1)
YES
1
maintenance_mode
tinyint(1)
YES
0
show_reviews
tinyint(1)
YES
1
show_contact_info
tinyint(1)
YES
1
featured_in_app
tinyint(1)
YES
0
default_payment_method
varchar(50)
YES
NULL
allow_multiple_payment_methods
tinyint(1)
YES
1
delivery_radius_km
int(11)
YES
0
free_delivery_min_order
decimal(10,2)
YES
0.00
notification_preferences
longtext
YES
NULL
additional_settings
longtext
YES
NULL
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_types;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
code
varchar(50)
NO
UNI
NULL
name
varchar(150)
NO
NULL
description
text
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE addresses;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
tenant_id
int(11) unsigned
NO
MUL
0
owner_type
enum('user','entity')
NO
MUL
NULL
owner_id
bigint(20) unsigned
NO
NULL
address_line1
varchar(255)
NO
NULL
address_line2
varchar(255)
YES
NULL
city_id
int(11)
YES
MUL
NULL
country_id
int(11)
YES
MUL
NULL
postal_code
varchar(20)
YES
NULL
latitude
decimal(10,7)
YES
NULL
longitude
decimal(11,7)
YES
NULL
is_primary
tinyint(1)
YES
0
created_at
timestamp
YES
current_timestamp()
updated_at
timestamp
YES
current_timestamp()
on update current_timestamp()
primary_marker
varchar(100)
YES
UNI
NULL
VIRTUAL GENERATED
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_products;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
tenant_id
int(10) unsigned
NO
MUL
NULL
entity_id
bigint(20) unsigned
NO
MUL
NULL
product_id
bigint(20) unsigned
NO
MUL
NULL
stock_quantity
int(11)
NO
0
low_stock_threshold
int(11)
YES
5
is_active
tinyint(1)
NO
1
is_featured
tinyint(1)
NO
0
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entity_product_variants;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
tenant_id
int(10) unsigned
NO
MUL
NULL
entity_id
bigint(20) unsigned
NO
MUL
NULL
product_id
bigint(20) unsigned
NO
MUL
NULL
variant_id
bigint(20) unsigned
NO
MUL
NULL
stock_quantity
int(11)
NO
0
low_stock_threshold
int(11)
NO
5
manage_stock
tinyint(1)
NO
1
stock_status
enum('in_stock','out_of_stock','unlimited')
NO
in_stock
is_active
tinyint(1)
NO
1
is_featured
tinyint(1)
NO
0
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()

