// pending new DB table design

CREATE TABLE IF NOT EXISTS /*_*/message_board (
`mb_board_id` int(11) PRIMARY KEY auto_increment,
`mb_board_owner` int(11) unsigned NOT NULL, -- foreign key to creating user's actor_id
`mb_board_type` int(11) NOT NULL, -- 0 is public, 1 is private, 2 is confidential, 3 is group chat, 4 is wiki team
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/mb_thread (
`mb_thread_id` int(11) PRIMARY KEY auto_increment,
`mb_thread_title` TEXT NOT NULL,
`mb_thread_date` datetime default NULL,
`actor_id` bigint unsigned NOT NULL, -- foreign key to actor table
`mb_board_id` int(11) NOT NULL, -- foreign key to message_board.mb_board_id
`mb_message_id` bigint unsigned NOT NULL, -- foreign key to mb_message.mb_message_id
`mb_thread_privacy` int(11) NOT NULL -- foreign key to message_board.mb_board_type
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/mb_message (
`mb_message_id` int(11) PRIMARY KEY auto_increment,
`mb_message_content` TEXT NOT NULL,
`mb_message_date` datetime default NULL,
`mb_board_type` int(11) NOT NULL, -- foreign key to message_board.mb_board_type
`mb_thread_id` int(11) unsigned NOT NULL, -- foreign key to mb_thread.mb_thread_id
`actor_id` bigint unsigned NOT NULL -- foreign key to actor table
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/mb_archive (
`mb_board_id` int(11) PRIMARY KEY auto_increment,
`mb_board_owner` int(11) unsigned NOT NULL, 
`mb_board_type` int(11) NOT NULL, 
`mb_thread_id` int(11) PRIMARY KEY auto_increment,
`mb_thread_title` TEXT NOT NULL,
`mb_thread_date` datetime default NULL,
`actor_id` bigint unsigned NOT NULL, -- foreign key to actor table
`mb_message_id` int(11) PRIMARY KEY auto_increment,
`mb_message_content` TEXT NOT NULL,
`mb_message_date` datetime default NULL
) /*$wgDBTableOptions*/;
