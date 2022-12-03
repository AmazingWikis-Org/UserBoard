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
	}
}