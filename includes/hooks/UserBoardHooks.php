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
		$dbExt = '';
		$db = $updater->getDB();
		$updater->addExtensionTable( 'user_board', "$dir/UserBoard/includes/sql/user_board$dbExt.sql" );
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
	 * Mark page as uncacheable
	 *
	 * @param Parser $parser
	 * @param ParserOutput $output
	 */
	public static function onParserLimitReportPrepare( $parser, $output ) {
		$parser->getOutput()->updateCacheExpiry( 0 );
	}

	/**
	 * Called by ArticleFromTitle hook
	 * Calls UserProfilePage instead of standard article on registered users'
	 * User: or User_profile: pages which are not subpages
	 *
	 * @param Title $title
	 * @param Article|null &$article
	 * @param IContextSource $context
	 */
	public static function onArticleFromTitle( Title $title, &$article, $context ) {
		global $wgHooks, $wgUserPageChoice;

		$out = $context->getOutput();
		$request = $context->getRequest();
		$pageTitle = $title->getText();
		$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
		if (
			!$title->isSubpage() &&
			$title->inNamespaces( [ NS_USER, NS_USER_PROFILE ] ) &&
			!$userNameUtils->isIP( $pageTitle )
		) {
			$show_user_page = false;
			if ( $wgUserPageChoice ) {
				$profile = new UserProfile( $pageTitle );
				$profile_data = $profile->getProfile();

				// If they want regular page, ignore this hook
				if ( isset( $profile_data['actor'] ) && $profile_data['actor'] && $profile_data['user_page_type'] == 0 ) {
					$show_user_page = true;
				}
			}

			if ( !$show_user_page ) {
				// Prevents editing of userpage
				if ( $request->getVal( 'action' ) == 'edit' ) {
					$out->redirect( $title->getFullURL() );
				}
			} else {
				$out->enableClientCache( false );
				$wgHooks['ParserLimitReportPrepare'][] = 'UserProfileHooks::onParserLimitReportPrepare';
			}

			$out->addModuleStyles( [
				'ext.socialprofile.clearfix',
				'ext.socialprofile.userprofile.css'
			] );

			$article = new UserProfilePage( $title );
		}
	}

	/**
	 * Mark social user pages as known so they appear in blue, unless the user
	 * is explicitly using a wiki user page, which may or may not exist.
	 *
	 * The assumption here is that when we have a Title pointing to a non-subpage
	 * page in the user NS (i.e. a user profile page), we _probably_ want to treat
	 * it as a blue link unless we have a good reason not to.
	 *
	 * Pages like Special:TopUsers etc. which use LinkRenderer would be slightly
	 * confusing if they'd show a mixture of red and blue links when in fact,
	 * regardless of the URL params, with SocialProfile installed they behave the
	 * same.
	 *
	 * @param Title $title title to check
	 * @param bool &$isKnown Whether the page should be considered known
	 */
	public static function onTitleIsAlwaysKnown( $title, &$isKnown ) {
		// global $wgUserPageChoice;

		if ( $title->inNamespace( NS_USER ) && !$title->isSubpage() ) {
			$isKnown = true;
			/* @todo Do we care? Also, how expensive would this be in the long run?
			if ( $wgUserPageChoice ) {
				$profile = new UserProfile( $title->getText() );
				$profile_data = $profile->getProfile();

				if ( isset( $profile_data['user_id'] ) && $profile_data['user_id'] ) {
					if ( $profile_data['user_page_type'] == 0 ) {
						$isKnown = false;
					}
				}
			}
			*/
		}
	}
}

