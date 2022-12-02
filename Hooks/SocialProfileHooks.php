<?php
/**
 * Hooked functions used by SocialProfile.
 *
 * All class methods are public and static.
 *
 * @file
 */
class SocialProfileHooks {

	/**
	 * Load some responsive CSS on all pages.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public static function onBeforePageDisplay( OutputPage $out, $skin ) {
		$out->addModuleStyles( 'ext.socialprofile.responsive' );
	}

	/**
	 * Register the canonical names for our custom namespaces and their talkspaces.
	 *
	 * @param string[] &$list Array of namespace numbers
	 * with corresponding canonical names
	 */
	public static function onCanonicalNamespaces( &$list ) {
		$list[NS_USER_WIKI] = 'UserWiki';
		$list[NS_USER_WIKI_TALK] = 'UserWiki_talk';
		$list[NS_USER_PROFILE] = 'User_profile';
		$list[NS_USER_PROFILE_TALK] = 'User_profile_talk';
	}

	/**
	 * Creates SocialProfile's new database tables when the user runs
	 * /maintenance/update.php, the MediaWiki core updater script.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__;
		$dbExt = '';
		$db = $updater->getDB();

		$updater->addExtensionTable( 'user_board', "$dir/UserBoard/sql/user_board$dbExt.sql" );
		$updater->addExtensionTable( 'user_profile', "$dir/UserProfile/sql/user_profile$dbExt.sql" );
	}
}