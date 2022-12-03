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
	
	/**
	 * Add a class to the <body> element on user pages to indicate which type
	 * of user page -- social profile or traditional wiki user page -- has been
	 * chosen by the user in question to make CSS styling easier.
	 *
	 * @see https://phabricator.wikimedia.org/T167506
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @param array &$bodyAttrs Pre-existing attributes of the <body> tag
	 */
	public static function onOutputPageBodyAttributes( $out, $skin, array &$bodyAttrs ) {
		global $wgUserPageChoice;

		$title = $out->getTitle();
		$pageTitle = $title->getText();
		$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
		// Only NS_USER is "ambiguous", NS_USER_PROFILE and NS_USER_WIKI are not
		// Also we don't care about subpages here since only the main user page
		// can be something else than wikitext
		// Also ignore anonymous users since they can't have social profiles and
		// passing an IP address to UserProfile's constructor would break things
		if (
			$title->inNamespace( NS_USER ) &&
			!$title->isSubpage() &&
			$wgUserPageChoice &&
			!$userNameUtils->isIP( $pageTitle )
		) {
			$profile = new UserProfile( $pageTitle );
			$profile_data = $profile->getProfile();

			if ( isset( $profile_data['actor'] ) && $profile_data['actor'] ) {
				if ( $profile_data['user_page_type'] == 0 ) {
					$bodyAttrs['class'] .= ' mw-wiki-user-page';
				} else {
					$bodyAttrs['class'] .= ' mw-social-profile-page';
				}
			}
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
	 * Mark page as uncacheable
	 *
	 * @param Parser $parser
	 * @param ParserOutput $output
	 */
	public static function onParserLimitReportPrepare( $parser, $output ) {
		$parser->getOutput()->updateCacheExpiry( 0 );
	}

}
