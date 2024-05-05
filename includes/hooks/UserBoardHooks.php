<?php

use MediaWiki\MediaWikiServices;

class UserBoardHooks {

	/**
	 * Load some responsive CSS on all pages.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public static function onBeforePageDisplay( OutputPage $out, $skin ) {
		$out->addModuleStyles( 'userboard.responsive' );
	}

	/**
	 * Creates UserBoard's new database tables when the user runs
	 * /maintenance/update.php, the MediaWiki core updater script.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__;
		$db = $updater->getDB();
		$updater->addExtensionTable( 'user_board', "$dir/../sql/user_board.sql" );
	}

	/**
	 * Add a link to the user's userboard page among "personal URLs" at the top
	 *
	 * @param array &$personal_urls
	 * @param Title &$title
	 * @param SkinTemplate $skinTemplate
	 *
	 * @return bool true
	 */
	public static function addURLToUserLinks(
		array &$personal_urls,
		Title &$title,
		SkinTemplate $skinTemplate
	) {
		if ( $skinTemplate->getUser()->isAllowed( 'edit' ) ) {
			$ubl = SpecialPage::getTitleFor( 'UserBoard' );
			$href = $ubl->getLocalURL();
			$userboard_links_vals = array(
				'text' => $skinTemplate->msg( 'userboard' )->text(),
				'href' => $href,
				'active' => ( $href == $title->getLocalURL() )
			);

			// find the location of the 'talk' link, and
			// add the link to 'UserBoard' right before it.
			// this is a "key-safe" splice - it preserves both the
			// keys and the values of the array, by editing them
			// separately and then rebuilding the array.
			// based on the example at http://us2.php.net/manual/en/function.array-splice.php#31234
			$tab_keys = array_keys( $personal_urls );
			$tab_values = array_values( $personal_urls );
			$new_location = array_search( 'mytalk', $tab_keys );
			array_splice( $tab_keys, $new_location, 0, 'userboard' );
			array_splice( $tab_values, $new_location, 0, array( $userboard_links_vals ) );

			$personal_urls = array();
			$tabKeysCount = count( $tab_keys );
			for ( $i = 0; $i < $tabKeysCount; $i++ ) {
				$personal_urls[$tab_keys[$i]] = $tab_values[$i];
			}
		}
		return true;
	}

	/**
	 * For the Echo extension.
	 *
	 * @param array[] &$notifications Echo notifications
	 * @param array[] &$notificationCategories Echo notification categories
	 * @param array[] &$icons Icon details
	 */
	public static function onBeforeCreateEchoEvent( array &$notifications, array &$notificationCategories, array &$icons ) {
		$notificationCategories['social-msg'] = [
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-social-msg',
		];

		$notifications['social-msg-send'] = [
			'category' => 'social-msg',
			'group' => 'interactive',
			'presentation-model' => 'EchoUserBoardMessagePresentationModel',
			EchoAttributeManager::ATTR_LOCATORS => [
				'EchoUserLocator::locateEventAgent'
			],
		];
	}

	/**
	 * Add user to be notified on Echo event
	 *
	 * @param EchoEvent $event
	 * @param User[] &$users
	 */
	public static function onEchoGetDefaultNotifiedUsers( EchoEvent $event, array &$users ) {
		switch ( $event->getType() ) {
			case 'social-msg-send':
				$extra = $event->getExtra();
				$targetId = $extra['target'];
				$users[] = User::newFromId( $targetId );
				break;
		}
	}

	/**
	 * Set bundle for message
	 *
	 * @param EchoEvent $event
	 * @param string &$bundleString
	 */
	public static function onEchoGetBundleRules( EchoEvent $event, &$bundleString ) {
		switch ( $event->getType() ) {
			case 'social-msg-send':
				$bundleString = 'social-msg-send';
				break;
		}
	}

	/**
	 * Mark page as uncacheable (why is this being done and should it be changed? sounds like a performance issue)
	 *
	 * @param Parser $parser
	 * @param ParserOutput $output
	 *
	 */
	public static function onParserLimitReportPrepare( $parser, $output ) {
		$parser->getOutput()->updateCacheExpiry( 0 );
	}
}

