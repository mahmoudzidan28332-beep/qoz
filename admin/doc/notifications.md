DESCRIBE notifications;
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
sender_entity_id
bigint(20) unsigned
YES
MUL
NULL
entity_id
bigint(20) unsigned
YES
MUL
NULL
title
varchar(500)
NO
NULL
message
mediumtext
NO
NULL
sent_at
timestamp
YES
current_timestamp()
data
longtext
YES
NULL
notification_type_id
int(10) unsigned
YES
MUL
NULL
priority
enum('low','normal','high','urgent')
YES
normal
expires_at
datetime
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE notification_types;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
int(10) unsigned
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
is_active
tinyint(1)
NO
1
owner_scope
enum('platform','tenant','shared')
YES
NULL
default_template
longtext
YES
NULL
created_at
timestamp
YES
current_timestamp()
updated_at
timestamp
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE notification_recipients;
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
notification_id
bigint(20) unsigned
NO
MUL
NULL
tenant_id
int(10) unsigned
YES
NULL
recipient_type
enum('user','entity','tenant')
NO
MUL
NULL
recipient_id
bigint(20) unsigned
NO
NULL
is_read
tinyint(1)
YES
0
read_at
datetime
YES
NULL
created_at
timestamp
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE notification_deliveries;
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
notification_id
bigint(20) unsigned
NO
MUL
NULL
channel_id
int(10) unsigned
NO
MUL
NULL
delivery_status
enum('pending','sent','failed')
YES
pending
attempts
int(11)
YES
0
sent_at
datetime
YES
NULL
error_message
text
YES
NULL
created_at
timestamp
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE notification_counters;
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
recipient_type
enum('user','entity','tenant')
NO
NULL
recipient_id
bigint(20) unsigned
NO
NULL
unread_count
int(11)
YES
0
updated_at
timestamp
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE notification_channels;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
int(10) unsigned
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
varchar(100)
NO
NULL
is_active
tinyint(1)
YES
1
created_at
timestamp
YES
current_timestamp()


