// pending new DB table design
CREATE TABLE IF NOT EXISTS /*_*/message_board (
`mb_board_id` int(11) PRIMARY KEY auto_increment,
`actor_id` bigint unsigned NOT NULL // foreign key
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/mb_thread (
`mb_thread_id` int(11) PRIMARY KEY auto_increment,
`actor_id` bigint unsigned NOT NULL, // foreign key
`mb_board_id` bigint unsigned NOT NULL, // foreign key
`mb_message_id` bigint unsigned NOT NULL, // foreign key
`thread_title` TEXT NOT NULL,
`mb_board_type` int(11) NOT NULL,
`mb_date` datetime default NULL
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/mb_message (
`mb_message_id` int(11) PRIMARY KEY auto_increment,
`mb_thread_id` int(11) unsigned NOT NULL, // foreign key
`actor_id` bigint unsigned NOT NULL, // foreign key
`mb_message` TEXT NOT NULL,
`mb_board_type` int(11) NOT NULL,
`mb_date` datetime default NULL
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/mb_archive (
`mb_archive_id` int(11) PRIMARY KEY auto_increment,
`mb_board_id` int(11) // foreign key
`mb_thread_id` int(11) // foreign key
`mb_message_id` bigint unsigned NOT NULL, // foreign key
`actor_id` bigint unsigned NOT NULL // foreign key
`thread_title` TEXT NOT NULL,
`mb_message` TEXT NOT NULL,
`mb_board_type` int(11) NOT NULL,
`mb_date` datetime default NULL
) /*$wgDBTableOptions*/;
