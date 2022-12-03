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
$wgAutoloadClasses['UserBoardHooks'] = __DIR__ . '/hooks/UserBoardHooks.php';

// Hook functions
$wgHooks['LoadExtensionSchemaUpdates'][] = 'UserBoardHooks::onLoadExtensionSchemaUpdates';
$wgHooks['ArticleFromTitle'][] = 'UserBoardHooks::onArticleFromTitle';
$wgHooks['TitleIsAlwaysKnown'][] = 'UserBoardHooks::onTitleIsAlwaysKnown';
$wgHooks['OutputPageBodyAttributes'][] = 'UserBoardHooks::onOutputPageBodyAttributes';
$wgHooks['DifferenceEngineShowDiff'][] = 'UserBoardHooks::onDifferenceEngineShowDiff';
$wgHooks['DifferenceEngineOldHeader'][] = 'UserBoardHooks::onDifferenceEngineOldHeader';
$wgHooks['DifferenceEngineNewHeader'][] = 'UserBoardHooks::onDifferenceEngineNewHeader';


// Loader files
wfLoadExtensions( [
	'SocialProfile/UserBoard'
] );

// ResourceLoader module definitions for certain components which do not have
// their own loader file
$wgResourceModules['ext.socialprofile.clearfix'] = [
	'styles' => 'clearfix.css',
	'localBasePath' => __DIR__ . '/UserBoard/resources/css',
	'remoteExtPath' => 'UserBoard/resources/css',
];

$wgResourceModules['ext.socialprofile.responsive'] = [
	'styles' => 'responsive.less',
	'localBasePath' => __DIR__ . '/UserBoard/resources/css',
	'remoteExtPath' => 'UserBoard/resources/css',
];

// General/shared JS modules -- not (necessarily) directly used by SocialProfile,
// but rather by other social tools which depend on SP
// @see https://phabricator.wikimedia.org/T100025
$wgResourceModules['ext.socialprofile.LightBox'] = [
	'scripts' => 'LightBox.js',
	'localBasePath' => __DIR__ . '/UserBoard/resources/js',
	'remoteExtPath' => 'UserBoard/resources/js',
];

// End ResourceLoader stuff

