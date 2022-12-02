<?php
// Global profile namespace reference
define( 'NS_USER_PROFILE', 202 );
define( 'NS_USER_WIKI', 200 );

// Default setup for displaying sections
$wgUserPageChoice = true;

$wgUserProfileDisplay['board'] = true;

// ResourceLoader support for MediaWiki 1.17+
$wgResourceModules['ext.socialprofile.userprofile.css'] = [
	'styles' => 'resources/css/UserProfile.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'SocialProfile/UserProfile'
];

$wgResourceModules['ext.socialprofile.userprofile.js'] = [
	'scripts' => 'resources/js/UserProfilePage.js',
	'messages' => [ 'user-board-confirm-delete' ],
	'dependencies' => [ 'mediawiki.api', 'mediawiki.util' ],
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'SocialProfile/UserProfile',
];

// CSS for user avatars in page diffs
$wgResourceModules['ext.socialprofile.userprofile.tabs.css'] = [
	'styles' => 'resources/css/ProfileTabs.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'SocialProfile/UserProfile'
];


$wgHooks['ArticleFromTitle'][] = 'UserProfileHooks::onArticleFromTitle';
$wgHooks['TitleIsAlwaysKnown'][] = 'UserProfileHooks::onTitleIsAlwaysKnown';
$wgHooks['OutputPageBodyAttributes'][] = 'UserProfileHooks::onOutputPageBodyAttributes';
$wgHooks['DifferenceEngineShowDiff'][] = 'UserProfileHooks::onDifferenceEngineShowDiff';
$wgHooks['DifferenceEngineOldHeader'][] = 'UserProfileHooks::onDifferenceEngineOldHeader';
$wgHooks['DifferenceEngineNewHeader'][] = 'UserProfileHooks::onDifferenceEngineNewHeader';
