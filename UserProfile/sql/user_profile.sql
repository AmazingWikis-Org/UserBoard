--
-- Table structure for table `user_profile`
--

CREATE TABLE IF NOT EXISTS /*_*/user_profile (
  `up_actor` bigint unsigned NOT NULL PRIMARY KEY,
  `up_name` varchar(255) default NULL,
  `up_location_country` varchar(255) default NULL,
  `up_birthday` date default NULL,
  `up_joindate` date default NULL,
  `up_websites` text,
  `up_hobbies` text,
  `up_bestMoment` text,
  `up_favoriteCharacter` text,
  `up_favoriteItem` text,
  `up_worstMoment` text,
  `up_friendcode` text,
  `up_steam` text,
  `up_xbox` text,
  `up_twitter` text,
  `up_mastodon` text,
  `up_instagram` text,
  `up_discord ` text,
  `up_irc` text,
  `up_reddit` text,
  `up_twitch` text,
  `up_youtube` text,
  `up_rumble` text,
  `up_bitchute` text,
  `up_type` int(5) NOT NULL default '1'
) /*$wgDBTableOptions*/;


CREATE INDEX /*i*/up_actor ON /*_*/user_profile (up_actor);


















