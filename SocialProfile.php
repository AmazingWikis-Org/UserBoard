<?php
/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die(
		'This is the setup file for the SocialProfile extension to MediaWiki.' .
		'Please see https://www.mediawiki.org/wiki/Extension:SocialProfile for' .
		' more information about this extension.'
	);
}

// Extension credits that show up on Special:Version
$wgExtensionCredits['other'][] = [
	'path' => __FILE__,
	'name' => 'SocialProfile',
	'author' => [ 'Aaron Wright', 'David Pean', 'Jack Phoenix', 'Amazing Wikis Org' ],
	'version' => '1.20',
	'url' => 'https://www.mediawiki.org/wiki/Extension:SocialProfile',
	'descriptionmsg' => 'socialprofile-desc',
];

/**
 * This is the loader file for the SocialProfile extension. You should include
 * this file in your wiki's LocalSettings.php to activate SocialProfile.
 *
 * If you want to use the UserWelcome extension (bundled with SocialProfile),
 * the <topusers /> tag or the user levels feature, there are some other files
 * you will need to include in LocalSettings.php. The online manual has more
 * details about this.
 *
 * For more info about SocialProfile, please see https://www.mediawiki.org/wiki/Extension:SocialProfile.
 */


// Hack to make installer load extension properly. (T243861)
// Based on Installer::includeExtensions()
if ( defined( 'MEDIAWIKI_INSTALL' ) ) {
	$subext = [
		__DIR__ . '/UserBoard/extension.json' => 1,
	];

	$registry = new ExtensionRegistry();
	$data = $registry->readFromQueue( $subext );
	// @phan-suppress-next-line PhanUndeclaredVariableAssignOp Obviously not undeclared
	$wgAutoloadClasses += $data['globals']['wgAutoloadClasses'];
}

// Classes to be autoloaded
// @phan-suppress-next-line PhanTypeArraySuspicious
$wgAutoloadClasses['UserProfileHooks'] = __DIR__ . '/hooks/UserProfileHooks.php';
$wgAutoloadClasses['SocialProfileHooks'] = __DIR__ . '/hooks/SocialProfileHooks.php';


// Hook functions
$wgHooks['LoadExtensionSchemaUpdates'][] = 'SocialProfileHooks::onLoadExtensionSchemaUpdates';
$wgHooks['ArticleFromTitle'][] = 'UserProfileHooks::onArticleFromTitle';
$wgHooks['TitleIsAlwaysKnown'][] = 'UserProfileHooks::onTitleIsAlwaysKnown';
$wgHooks['OutputPageBodyAttributes'][] = 'UserProfileHooks::onOutputPageBodyAttributes';
$wgHooks['DifferenceEngineShowDiff'][] = 'UserProfileHooks::onDifferenceEngineShowDiff';
$wgHooks['DifferenceEngineOldHeader'][] = 'UserProfileHooks::onDifferenceEngineOldHeader';
$wgHooks['DifferenceEngineNewHeader'][] = 'UserProfileHooks::onDifferenceEngineNewHeader';


// Loader files
wfLoadExtensions( [
	'SocialProfile/UserBoard'
] );


// What to display on social profile pages by default?
$wgUserProfileDisplay['board'] = true;


// Should we display UserBoard-related things on social profile pages?
$wgUserBoard = true;


// ResourceLoader module definitions for certain components which do not have
// their own loader file
$wgResourceModules['ext.socialprofile.clearfix'] = [
	'styles' => 'clearfix.css',
	'localBasePath' => __DIR__ . '/shared',
	'remoteExtPath' => 'SocialProfile/shared',
];

$wgResourceModules['ext.socialprofile.responsive'] = [
	'styles' => 'responsive.less',
	'localBasePath' => __DIR__ . '/shared',
	'remoteExtPath' => 'SocialProfile/shared',
];

// General/shared JS modules -- not (necessarily) directly used by SocialProfile,
// but rather by other social tools which depend on SP
// @see https://phabricator.wikimedia.org/T100025
$wgResourceModules['ext.socialprofile.LightBox'] = [
	'scripts' => 'LightBox.js',
	'localBasePath' => __DIR__ . '/shared',
	'remoteExtPath' => 'SocialProfile/shared',
];

// End ResourceLoader stuff

