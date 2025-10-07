# Intranet-Based-Communication-and-File-Sharing-System-for-Batangas-Eastern-Colleges-BEC-

127.0.0.1/bec_intranet/		http://localhost/phpmyadmin/index.php?route=/database/sql&db=bec_intranet
Your SQL query has been executed successfully.

Your SQL query has been executed successfully.

Your SQL query has been executed successfully.

Your SQL query has been executed successfully.

Your SQL query has been executed successfully.

Your SQL query has been executed successfully.

Your SQL query has been executed successfully.

Your SQL query has been executed successfully.

DESCRIBE admins;


DESCRIBE announcements;


DESCRIBE conversation_members;


DESCRIBE conversation;


DESCRIBE files;


DESCRIBE messages;


DESCRIBE students;


DESCRIBE teachers;



id	int	NO	PRI	NULL	auto_increment	
admin_id	varchar(50)	YES	UNI	NULL		
fullname	varchar(255)	YES		NULL		
email	varchar(100)	YES	UNI	NULL		
password	varchar(255)	NO		NULL		
created_at	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
profile_picture	varchar(255)	YES		default.png		
id	int	NO	PRI	NULL	auto_increment	
title	varchar(255)	NO		NULL		
content	text	NO		NULL		
created_at	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
conversation_id	int	NO	PRI	NULL		
user_id	varchar(255)	NO	PRI	NULL		
role	varchar(50)	NO	PRI	NULL		
id	int	NO	PRI	NULL	auto_increment	
name	varchar(255)	YES		NULL		
is_group	tinyint(1)	YES	MUL	0		
created_at	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
id	int	NO	PRI	NULL	auto_increment	
file_name	varchar(255)	NO		NULL		
uploaded_by	varchar(50)	NO		NULL		
uploaded_at	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
id	int	NO	PRI	NULL	auto_increment	
conversation_id	int	YES	MUL	NULL		
sender_id	varchar(255)	YES	MUL	NULL		
sender_role	varchar(50)	YES		NULL		
message	text	NO		NULL		
created_at	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
id	int	NO	PRI	NULL	auto_increment	
student_id	varchar(50)	YES	UNI	NULL		
fullname	varchar(255)	YES		NULL		
email	varchar(100)	YES	UNI	NULL		
password	varchar(255)	NO		NULL		
created_at	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
profile_picture	varchar(255)	YES		default.png		
id	int	NO	PRI	NULL	auto_increment	
teacher_id	varchar(50)	YES	UNI	NULL		
fullname	varchar(255)	YES		NULL		
email	varchar(100)	YES	UNI	NULL		
password	varchar(255)	NO		NULL		
created_at	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
profile_picture	varchar(255)	YES		default.png		
