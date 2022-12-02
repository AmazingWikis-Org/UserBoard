<?php
// Global profile namespace reference
define( 'NS_USER_PROFILE', 202 );
define( 'NS_USER_WIKI', 200 );

/**
 * If you want to require users to have a certain number of certain things, like
 * five edits or three friends or two comments or whatever (is supported by
 * SocialProfile/the user_stats DB table) before they can use Special:UpdateProfile,
 * use this global.
 *
 * For example, to require a user to have five edits before they're allowed to access
 * Special:UpdateProfile, set:
 * @code
 * $wgUserProfileThresholds = [ 'edits' => 5 ];
 * @endcode
 *
 * @endcode
 */
$wgUserProfileThresholds = [
/**
 * All currently "supported" options (supported meaning that there is i18n support):
 * edits // normal edits in the namespaces that earn you points ($wgNamespacesForEditPoints)
 */
];

// Default setup for displaying sections
$wgUserPageChoice = true;

$wgUserProfileDisplay['board'] = true;
$wgUserProfileDisplay['activity'] = true; // Display recent social activity
$wgUserProfileDisplay['profile'] = true;
$wgUserProfileDisplay['personal'] = true;
$wgUserProfileDisplay['biography'] = true;
$wgUserProfileDisplay['accountlinks'] = true;

$wgUpdateProfileInRecentChanges = false; // Show a log entry in recent changes whenever a user updates their profile?

$wgAvailableRights[] = 'editothersprofiles';
$wgAvailableRights[] = 'editothersprofiles-private';
$wgAvailableRights[] = 'populate-user-profiles';

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

// Modules for Special:EditProfile/Special:UpdateProfile
$wgResourceModules['ext.userProfile.updateProfile'] = [
	'scripts' => 'resources/js/UpdateProfile.js',
	'dependencies' => [ 'mediawiki.api', 'mediawiki.util', 'jquery.ui' ],
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'SocialProfile/UserProfile'
];

// CSS for user avatars in page diffs
$wgResourceModules['ext.socialprofile.userprofile.tabs.css'] = [
	'styles' => 'resources/css/ProfileTabs.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'SocialProfile/UserProfile'
];

$wgResourceModules['ext.socialprofile.special.updateprofile.css'] = [
	'styles' => 'resources/css/SpecialUpdateProfile.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'SocialProfile/UserProfile'
];

# Add new log types for profile edits and avatar uploads
global $wgLogTypes, $wgLogNames, $wgLogHeaders, $wgLogActions;
$wgLogTypes[]                    = 'profile';
$wgLogNames['profile']           = 'profilelogpage';
$wgLogHeaders['profile']         = 'profilelogpagetext';
$wgLogActions['profile/profile'] = 'profilelogentry';

$wgHooks['ArticleFromTitle'][] = 'UserProfileHooks::onArticleFromTitle';
$wgHooks['TitleIsAlwaysKnown'][] = 'UserProfileHooks::onTitleIsAlwaysKnown';
$wgHooks['OutputPageBodyAttributes'][] = 'UserProfileHooks::onOutputPageBodyAttributes';
$wgHooks['ParserFirstCallInit'][] = 'UserProfileHooks::onParserFirstCallInit';
$wgHooks['DifferenceEngineShowDiff'][] = 'UserProfileHooks::onDifferenceEngineShowDiff';
$wgHooks['DifferenceEngineOldHeader'][] = 'UserProfileHooks::onDifferenceEngineOldHeader';
$wgHooks['DifferenceEngineNewHeader'][] = 'UserProfileHooks::onDifferenceEngineNewHeader';
