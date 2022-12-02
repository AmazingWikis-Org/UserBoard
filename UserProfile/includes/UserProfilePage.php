<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

/**
 * User profile Wiki Page
 *
 * @file
 * @ingroup Extensions
 * @author David Pean <david.pean@gmail.com>
 * @copyright Copyright © 2007, Wikia Inc.
 * @license GPL-2.0-or-later
 */

class UserProfilePage extends Article {

	/**
	 * @var Title
	 */
	public $title = null;

	/**
	 * @var User User object for the person whose profile is being viewed
	 */
	public $profileOwner;

	/**
	 * @var User User who is viewing someone's profile
	 */
	public $viewingUser;

	/**
	 * @var string user name of the user whose profile we're viewing
	 * @deprecated Prefer using getName() on $this->profileOwner or $this->viewingUser as appropriate
	 */
	public $user_name;

	/**
	 * @var int user ID of the user whose profile we're viewing
	 * @deprecated Prefer using getId() or better yet, getActorId(), on $this->profileOwner or $this->viewingUser as appropriate
	 */
	public $user_id;

	/**
	 * @var User User object representing the user whose profile we're viewing
	 * @deprecated Confusing name; prefer using $this->profileOwner or $this->viewingUser as appropriate
	 */
	public $user;

	/**
	 * @var bool is the current user the owner of the profile page?
	 */
	public $is_owner;

	/**
	 * @var array user profile data (interests, etc.) for the user whose
	 * profile we're viewing
	 */
	public $profile_data;

	/**
	 * @var array array of profile fields visible to the user viewing the profile
	 */
	public $profile_visible_fields;

	function __construct( $title ) {
		$context = $this->getContext();
		// This is the user *who is viewing* the page
		$user = $this->viewingUser = $context->getUser();

		parent::__construct( $title );
		// These vars represent info about the user *whose page is being viewed*
		$this->profileOwner = User::newFromName( $title->getText() );

		$this->user_name = $this->profileOwner->getName();
		$this->user_id = $this->profileOwner->getId();

		$this->user = $this->profileOwner;
		$this->user->load();

		$this->is_owner = ( $this->profileOwner->getName() == $user->getName() );

		$profile = new UserProfile( $this->profileOwner );
		$this->profile_data = $profile->getProfile();
		$this->profile_visible_fields = SPUserSecurity::getVisibleFields( $this->profileOwner, $this->viewingUser );
	}

	/**
	 * Is the current user the owner of the profile page?
	 * In other words, is the current user's username the same as that of the
	 * profile's owner's?
	 *
	 * @return bool
	 */
	function isOwner() {
		return $this->is_owner;
	}

	function view() {
		$context = $this->getContext();
		$out = $context->getOutput();
		$logger = LoggerFactory::getInstance( 'SocialProfile' );

		$out->setPageTitle( $this->getTitle()->getPrefixedText() );

		// No need to display noarticletext, we use our own message
		// @todo FIXME: this was basically "!$this->profileOwner" prior to actor.
		// Now we need to explicitly check for this b/c if we don't and we're viewing
		// the User: page of a nonexistent user as an anon, that profile page will
		// display as User:<your IP address> and $this->profileOwner will have been
		// set to a User object representing that anonymous user (IP address).
		if ( $this->profileOwner->isAnon() ) {
			parent::view();
			return '';
		}

		$out->addHTML( '<div id="profile-top">' );
		$out->addHTML( $this->getProfileHeader() );
		$out->addHTML( '<div class="visualClear"></div></div>' );

		// Add JS -- needed by UserBoard stuff but also by the "change profile type" button
		// If this were loaded in getUserBoard() as it originally was, then the JS that deals
		// with the "change profile type" button would *not* work when the user is using a
		// regular wikitext user page despite that the social profile header would still be
		// displayed.
		// @see T202272, T242689
		$out->addModules( 'ext.socialprofile.userprofile.js' );

		// User does not want social profile for User:user_name, so we just
		// show header + page content
		if (
			$this->getTitle()->getNamespace() == NS_USER &&
			$this->profile_data['actor'] &&
			$this->profile_data['user_page_type'] == 0
		) {
			parent::view();
			return '';
		}

		// Left side
		$out->addHTML( '<div id="user-page-left" class="clearfix">' );

		// Avoid PHP 7.1 warning of passing $this by reference
		$userProfilePage = $this;



		if ( !Hooks::run( 'UserProfileBeginLeft', [ &$userProfilePage ] ) ) {
			$logger->debug( "{method}: UserProfileBeginLeft messed up profile!\n", [
				'method' => __METHOD__
			] );
		}

		$out->addHTML( $this->getPersonalInfo() );

		$out->addHTML( $this->getBiography() );

		$out->addHTML( $this->getAccountLinks() );

		if ( !Hooks::run( 'UserProfileEndLeft', [ &$userProfilePage ] ) ) {
			$logger->debug( "{method}: UserProfileEndLeft messed up profile!\n", [
				'method' => __METHOD__
			] );
		}

		$out->addHTML( '</div>' );

		$logger->debug( "profile start right\n" );

		// Right side
		$out->addHTML( '<div id="user-page-right" class="clearfix">' );

		if ( !Hooks::run( 'UserProfileBeginRight', [ &$userProfilePage ] ) ) {
			$logger->debug( "{method}: UserProfileBeginRight messed up profile!\n", [
				'method' => __METHOD__
			] );
		}

		$out->addHTML( $this->getUserBoard( $context->getUser() ) );


		if ( !Hooks::run( 'UserProfileEndRight', [ &$userProfilePage ] ) ) {
			$logger->debug( "{method}: UserProfileEndRight messed up profile!\n", [
				'method' => __METHOD__
			] );
		}

		$out->addHTML( '</div><div class="visualClear"></div>' );
	}

	function getProfileSection( $label, $value, $required = true ) {
		$context = $this->getContext();
		$out = $context->getOutput();
		$user = $context->getUser();

		$output = '';
		if ( $value || $required ) {
			if ( !$value ) {
				if ( $user->getName() == $this->getTitle()->getText() ) {
					$value = wfMessage( 'profile-updated-personal' )->escaped();
				} else {
					$value = wfMessage( 'profile-not-provided' )->escaped();
				}
			}

			$value = $out->parseAsInterface( trim( $value ), false );

			$output = "<div><b>{$label}</b>{$value}</div>";
		}
		return $output;
	}

	function getPersonalInfo() {
		global $wgUserProfileDisplay;

		if ( $wgUserProfileDisplay['personal'] == false ) {
			return '';
		}

		$this->initializeProfileData();
		$profile_data = $this->profile_data;

		$defaultCountry = wfMessage( 'user-profile-default-country' )->inContentLanguage()->text();

		// Current location
		$location = $profile_data['location_city'] . ', ' . $profile_data['location_state'];
		if ( $profile_data['location_country'] != $defaultCountry ) {
			if ( $profile_data['location_city'] && $profile_data['location_state'] ) { // city AND state
				$location = $profile_data['location_city'] . ', ' .
							$profile_data['location_state'] . ', ' .
							$profile_data['location_country'];
				// Privacy
				$location = '';
				if ( in_array( 'up_location_city', $this->profile_visible_fields ) ) {
					$location .= $profile_data['location_city'] . ', ';
				}
				$location .= $profile_data['location_state'];
				if ( in_array( 'up_location_country', $this->profile_visible_fields ) ) {
					$location .= ', ' . $profile_data['location_country'] . ', ';
				}
			} elseif ( $profile_data['location_city'] && !$profile_data['location_state'] ) { // city, but no state
				$location = '';
				if ( in_array( 'up_location_city', $this->profile_visible_fields ) ) {
					$location .= $profile_data['location_city'] . ', ';
				}
				if ( in_array( 'up_location_country', $this->profile_visible_fields ) ) {
					$location .= $profile_data['location_country'];
				}
			} elseif ( $profile_data['location_state'] && !$profile_data['location_city'] ) { // state, but no city
				$location = $profile_data['location_state'];
				if ( in_array( 'up_location_country', $this->profile_visible_fields ) ) {
					$location .= ', ' . $profile_data['location_country'];
				}
			} else {
				$location = '';
				if ( in_array( 'up_location_country', $this->profile_visible_fields ) ) {
					$location .= $profile_data['location_country'];
				}
			}
		}

		if ( $location == ', ' ) {
			$location = '';
		}


		$joined_data = $profile_data['real_name'] . $location .
				$profile_data['birthday'] . $profile_data['joindate'] . 
				$profile_data['websites'];
		$edit_info_link = SpecialPage::getTitleFor( 'UpdateProfile' );

		$personal_output = '';
		if ( in_array( 'up_real_name', $this->profile_visible_fields ) ) {
			$personal_output .= $this->getProfileSection( wfMessage( 'user-personal-info-real-name' )->escaped(), $profile_data['real_name'], false );
		}

		$personal_output .= $this->getProfileSection( wfMessage( 'user-personal-info-location' )->escaped(), $location, false );

		if ( in_array( 'up_birthday', $this->profile_visible_fields ) ) {
			$personal_output .= $this->getProfileSection( wfMessage( 'user-personal-info-birthday' )->escaped(), $profile_data['birthday'], false );
		}

		if ( in_array( 'up_occupation', $this->profile_visible_fields ) ) {
			$personal_output .= $this->getProfileSection( wfMessage( 'user-personal-info-joindate' )->escaped(), $profile_data['joindate'], false );
		}

		if ( in_array( 'up_websites', $this->profile_visible_fields ) ) {
			$personal_output .= $this->getProfileSection( wfMessage( 'user-personal-info-websites' )->escaped(), $profile_data['websites'], false );
		}

		$output = '';
		if ( $joined_data ) {
			$output .= '<div class="user-section-heading">
				<div class="user-section-title">' .
					wfMessage( 'user-personal-basicinfo-title' )->escaped() .
				'</div>
				<div class="user-section-actions">
					<div class="action-right">';
			if ( $this->viewingUser->getName() == $this->profileOwner->getName() ) {
				$output .= '<a href="' . htmlspecialchars( $edit_info_link->getFullURL() ) . '">' .
					wfMessage( 'user-edit-this' )->escaped() . '</a>';
			}
			$output .= '</div>
					<div class="visualClear"></div>
				</div>
			</div>
			<div class="visualClear"></div>
			<div class="profile-info-container">' .
				$personal_output .
			'</div>';
		} elseif ( $this->viewingUser->getName() == $this->profileOwner->getName() ) {
			$output .= '<div class="user-section-heading">
				<div class="user-section-title">' .
					wfMessage( 'user-personal-basicinfo-title' )->escaped() .
				'</div>
				<div class="user-section-actions">
					<div class="action-right">
						<a href="' . htmlspecialchars( $edit_info_link->getFullURL() ) . '">' .
							wfMessage( 'user-edit-this' )->escaped() .
						'</a>
					</div>
					<div class="visualClear"></div>
				</div>
			</div>
			<div class="visualClear"></div>
			<div class="no-info-container">' .
				wfMessage( 'user-no-personal-info' )->escaped() .
			'</div>';
		}

		return $output;
	}

	/**
	 * Get the Biography for a given
	 * user.
	 *
	 * @return string HTML
	 */
	function getBiography() {
		global $wgUserProfileDisplay;

		if ( $wgUserProfileDisplay['biography'] == false ) {
			return '';
		}

		$this->initializeProfileData();

		$profile_data = $this->profile_data;
		$joined_data = $profile_data['about'] . $profile_data['hobbies'] . $profile_data['bestMoment'] .
				$profile_data['favoriteCharacter '] . $profile_data['favoriteItem'] .
				$profile_data['worstMoment'];

		$edit_info_link = SpecialPage::getTitleFor( 'UpdateProfile' );

		$interests_output = '';

		if ( in_array( 'up_about', $this->profile_visible_fields ) ) {
			$personal_output .= $this->getProfileSection( wfMessage( 'user-personal-personal-aboutme' )->escaped(), $profile_data['about'], false );
		}

		if ( in_array( 'up_movies', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'user-personal-personal-hobbies' )->escaped(), $profile_data['hobbies'], false );
		}
		if ( in_array( 'up_tv', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'user-profile-personal-best-moment' )->escaped(), $profile_data['bestMoment'], false );
		}
		if ( in_array( 'up_music', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'user-profile-personal-favorite-character' )->escaped(), $profile_data['favoriteCharacter '], false );
		}
		if ( in_array( 'up_books', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'user-profile-personal-favorite-item' )->escaped(), $profile_data['favoriteItem'], false );
		}
		if ( in_array( 'up_video_games', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'user-profile-personal-worst-moment' )->escaped(), $profile_data['worstMoment'], false );
		}

		$output = '';
		if ( $joined_data ) {
			$output .= '<div class="user-section-heading">
				<div class="user-section-title">' .
					wfMessage( 'user-personal-biography-title' )->escaped() .
				'</div>
				<div class="user-section-actions">
					<div class="action-right">';
			if ( $this->viewingUser->getName() == $this->profileOwner->getName() ) {
				$output .= '<a href="' . htmlspecialchars( $edit_info_link->getFullURL() ) . '/personal">' .
					wfMessage( 'user-edit-this' )->escaped() . '</a>';
			}
			$output .= '</div>
					<div class="visualClear"></div>
				</div>
			</div>
			<div class="visualClear"></div>
			<div class="profile-info-container">' .
				$interests_output .
			'</div>';
		} elseif ( $this->isOwner() ) {
			$output .= '<div class="user-section-heading">
				<div class="user-section-title">' .
					wfMessage( 'user-personal-accountlinks-title' )->escaped() .
				'</div>
				<div class="user-section-actions">
					<div class="action-right">
						<a href="' . htmlspecialchars( $edit_info_link->getFullURL() ) . '/personal">' .
							wfMessage( 'user-edit-this' )->escaped() .
						'</a>
					</div>
					<div class="visualClear"></div>
				</div>
			</div>
			<div class="visualClear"></div>
			<div class="no-info-container">' .
				wfMessage( 'other-no-info' )->escaped() .
			'</div>';
		}
		return $output;
	}


	/**
	 * Get the Social Media Accounts for a given user
	 *
	 * @return string HTML
	 */
	function getAccountLinks() {
		global $wgUserProfileDisplay;

		if ( $wgUserProfileDisplay['accountlinks'] == false ) {
			return '';
		}

		$this->initializeProfileData();

		$profile_data = $this->profile_data;
		$joined_data = $profile_data['friendcode'] . $profile_data['steam'] . 
				$profile_data['xbox'] . $profile_data['twitter'] . 
				$profile_data['mastodon'] . $profile_data['instagram'] . 
				$profile_data['discord '] . $profile_data['irc'] . 
				$profile_data['reddit'] . $profile_data['twitch'] . 
				$profile_data['youtube'] . $profile_data['rumble'] . 
				$profile_data['bitchute'];


		$edit_info_link = SpecialPage::getTitleFor( 'UpdateProfile' );

		$interests_output = '';

		if ( in_array( 'up_about', $this->profile_visible_fields ) ) {
			$personal_output .= $this->getProfileSection( wfMessage( 'account-links-friendcode' )->escaped(), $profile_data['friendcode'], false );
		}
		if ( in_array( 'up_movies', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'account-links-steam' )->escaped(), $profile_data['steam'], false );
		}
		if ( in_array( 'up_tv', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'account-links-xbox' )->escaped(), $profile_data['xbox'], false );
		}
		if ( in_array( 'up_music', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'account-links-twitter' )->escaped(), $profile_data['twitter'], false );
		}
		if ( in_array( 'up_books', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'account-links-mastodon' )->escaped(), $profile_data['mastodon'], false );
		}
		if ( in_array( 'up_video_games', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'account-links-instagram' )->escaped(), $profile_data['instagram'], false );
		}
		if ( in_array( 'up_about', $this->profile_visible_fields ) ) {
			$personal_output .= $this->getProfileSection( wfMessage( 'account-links-discord' )->escaped(), $profile_data['discord'], false );
		}
		if ( in_array( 'up_movies', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'account-links-irc' )->escaped(), $profile_data['irc'], false );
		}
		if ( in_array( 'up_tv', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'account-links-reddit' )->escaped(), $profile_data['reddit'], false );
		}
		if ( in_array( 'up_music', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'account-links-twitch' )->escaped(), $profile_data['twitch'], false );
		}
		if ( in_array( 'up_music', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'account-links-youtube' )->escaped(), $profile_data['youtube'], false );
		}
		if ( in_array( 'up_books', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'account-links-rumble' )->escaped(), $profile_data['rumble'], false );
		}
		if ( in_array( 'up_video_games', $this->profile_visible_fields ) ) {
			$interests_output .= $this->getProfileSection( wfMessage( 'account-links-bitchute' )->escaped(), $profile_data['bitchute'], false );
		}


		$output = '';
		if ( $joined_data ) {
			$output .= '<div class="user-section-heading">
				<div class="user-section-title">' .
					wfMessage( 'user-personal-accountlinks-title' )->escaped() .
				'</div>
				<div class="user-section-actions">
					<div class="action-right">';
			if ( $this->viewingUser->getName() == $this->profileOwner->getName() ) {
				$output .= '<a href="' . htmlspecialchars( $edit_info_link->getFullURL() ) . '/personal">' .
					wfMessage( 'user-edit-this' )->escaped() . '</a>';
			}
			$output .= '</div>
					<div class="visualClear"></div>
				</div>
			</div>
			<div class="visualClear"></div>
			<div class="profile-info-container">' .
				$interests_output .
			'</div>';
		} elseif ( $this->isOwner() ) {
			$output .= '<div class="user-section-heading">
				<div class="user-section-title">' .
					wfMessage( 'user-personal-biography-title' )->escaped() .
				'</div>
				<div class="user-section-actions">
					<div class="action-right">
						<a href="' . htmlspecialchars( $edit_info_link->getFullURL() ) . '/personal">' .
							wfMessage( 'user-edit-this' )->escaped() .
						'</a>
					</div>
					<div class="visualClear"></div>
				</div>
			</div>
			<div class="visualClear"></div>
			<div class="no-info-container">' .
				wfMessage( 'other-no-info' )->escaped() .
			'</div>';
		}
		return $output;
	}



	/**
	 * Get the header for the social profile page, which includes the user's
	 * points and user level (if enabled in the site configuration) and lots
	 * more.
	 *
	 * @return string HTML suitable for output
	 */
	function getProfileHeader() {
		global $wgUserLevels;

		$context = $this->getContext();
		$language = $context->getLanguage();

		$this->initializeProfileData();
		$profile_data = $this->profile_data;

		// Safe URLs
		$update_profile = SpecialPage::getTitleFor( 'UpdateProfile' );
		$watchlist = SpecialPage::getTitleFor( 'Watchlist' );
		$contributions = SpecialPage::getTitleFor( 'Contributions', $this->profileOwner->getName() );
		$send_message = SpecialPage::getTitleFor( 'UserBoard' );
		$user_social_profile = Title::makeTitle( NS_USER_PROFILE, $this->profileOwner->getName() );
		$user_wiki = Title::makeTitle( NS_USER_WIKI, $this->profileOwner->getName() );

		$logger = LoggerFactory::getInstance( 'SocialProfile' );
		$logger->debug( "profile type: {user_profile_type} \n", [
			'user_profile_type' => $profile_data['user_page_type']
		] );

		$output = '';

		// Show the link for changing user page type for the user whose page
		// it is
		if ( $this->isOwner() ) {
			$toggle_title = SpecialPage::getTitleFor( 'ToggleUserPage' );
			// Cast it to an int because PHP is stupid.
			if (
				(int)$profile_data['user_page_type'] == 1 ||
				$profile_data['user_page_type'] === ''
			) {
				$toggleMessage = wfMessage( 'user-type-toggle-old' )->escaped();
			} else {
				$toggleMessage = wfMessage( 'user-type-toggle-new' )->escaped();
			}
			$output .= '<div id="profile-toggle-button">
				<a href="' . htmlspecialchars( $toggle_title->getFullURL() ) . '" rel="nofollow">' .
					$toggleMessage . '</a>
			</div>';
		}

		$output .= '<div id="profile-right">';

		$output .= '<div id="profile-title-container">
				<div id="profile-title">' .
					htmlspecialchars( $this->profileOwner->getName() ) .
				'</div>';
		$output .= '<div class="visualClear"></div>
			</div>
			<div class="profile-actions">';

		$profileLinks = [];
		if ( $this->isOwner() ) {
			$profileLinks['user-watchlist'] =
				'<a href="' . htmlspecialchars( $watchlist->getFullURL() ) . '">' . wfMessage( 'user-watchlist' )->escaped() . '</a>';
		} elseif ( $this->viewingUser->isRegistered() ) {

			global $wgUserBoard;
			if ( $wgUserBoard ) {
				$profileLinks['user-send-message'] =
					'<a href="' . htmlspecialchars( $send_message->getFullURL( [
						'user' => $this->viewingUser->getName(),
						'conv' => $this->profileOwner->getName()
					] ) ) . '" rel="nofollow">' .
					wfMessage( 'user-send-message' )->escaped() . '</a>';
			}

		}

		$profileLinks['user-contributions'] =
			'<a href="' . htmlspecialchars( $contributions->getFullURL() ) . '" rel="nofollow">' .
				wfMessage( 'user-contributions' )->escaped() . '</a>';

		// Links to User:user_name from User_profile:
		if (
			$this->getTitle()->getNamespace() == NS_USER_PROFILE &&
			$this->profile_data['actor'] &&
			$this->profile_data['user_page_type'] == 0
		) {
			$profileLinks['user-page-link'] =
				'<a href="' . htmlspecialchars( $this->profileOwner->getUserPage()->getFullURL() ) . '" rel="nofollow">' .
					wfMessage( 'user-page-link' )->escaped() . '</a>';
		}

		// Links to User:user_name from User_profile:
		if (
			$this->getTitle()->getNamespace() == NS_USER &&
			$this->profile_data['actor'] &&
			$this->profile_data['user_page_type'] == 0
		) {
			$profileLinks['user-social-profile-link'] =
				'<a href="' . htmlspecialchars( $user_social_profile->getFullURL() ) . '" rel="nofollow">' .
					wfMessage( 'user-social-profile-link' )->escaped() . '</a>';
		}

		if (
			$this->getTitle()->getNamespace() == NS_USER && (
				!$this->profile_data['actor'] ||
				$this->profile_data['user_page_type'] == 1
			)
		) {
			$profileLinks['user-wiki-link'] =
				'<a href="' . htmlspecialchars( $user_wiki->getFullURL() ) . '" rel="nofollow">' .
					wfMessage( 'user-wiki-link' )->escaped() . '</a>';
		}

		// Provide a hook point for adding links to the profile header
		// or maybe even removing them
		// @see https://phabricator.wikimedia.org/T152930
		Hooks::run( 'UserProfileGetProfileHeaderLinks', [ $this, &$profileLinks ] );

		$output .= $language->pipeList( $profileLinks );
		$output .= '</div>

		</div>';

		return $output;
	}

	
	/**
	 * Get the user board for a given user.
	 *
	 * @return string
	 */
	function getUserBoard() {
		global $wgUserProfileDisplay;

		// Anonymous users cannot have user boards
		if ( $this->profileOwner->isAnon() ) {
			return '';
		}

		// Don't display anything if user board on social profiles isn't
		// enabled in site configuration
		if ( $wgUserProfileDisplay['board'] == false ) {
			return '';
		}

		$output = ''; // Prevent E_NOTICE

		// If the user is viewing their own profile or is allowed to delete
		// board messages, add the amount of private messages to the total
		// sum of board messages.
		if (
			$this->viewingUser->getName() == $this->profileOwner->getName() ||
			$this->viewingUser->isAllowed( 'userboard-delete' )
		) {}

		$output .= '<div class="user-section-heading">
			<div class="user-section-title">' .
				wfMessage( 'user-board-title' )->escaped() .
			'</div>
			<div class="user-section-actions">
				<div class="action-right">';
		
			$output .= '<a href="' .
				htmlspecialchars(
					SpecialPage::getTitleFor( 'UserBoard' )->getFullURL( [ 'user' => $this->profileOwner->getName() ] )
				) . '">' .
				wfMessage( 'user-view-all' )->escaped() . '</a>';

		$output .= '</div>
				<div class="action-left">';
		if ( $total > 10 ) {
			$output .= wfMessage( 'user-count-separator', '10', $total )->escaped();
		} elseif ( $total > 0 ) {
			$output .= wfMessage( 'user-count-separator', $total, $total )->escaped();
		}
		$output .= '</div>
				<div class="visualClear"></div>
			</div>
		</div>
		<div class="visualClear"></div>';

		if ( $this->viewingUser->getName() !== $this->profileOwner->getName() ) {
			if ( $this->viewingUser->isRegistered() && !$this->viewingUser->isBlocked() ) {
				// @todo FIXME: This code exists in an almost identical form in
				// ../../UserBoard/incldues/specials/SpecialUserBoard.php
				$url = htmlspecialchars(
					SpecialPage::getTitleFor( 'UserBoard' )->getFullURL( [
						'user' => $this->profileOwner->getName(),
						'action' => 'send'
					] ),
					ENT_QUOTES
				);
				$output .= '<div class="user-page-message-form">
					<form id="board-post-form" action="' . $url . '" method="post">
						<input type="hidden" id="user_name_to" name="user_name_to" value="' . htmlspecialchars( $this->profileOwner->getName(), ENT_QUOTES ) . '" />
						<span class="profile-board-message-type">' .
							wfMessage( 'userboard_messagetype' )->escaped() .
						'</span>
						<select id="message_type" name="message_type">
							<option value="0">' .
								wfMessage( 'userboard_public' )->escaped() .
							'</option>
						</select><p>
						<textarea name="message" id="message" cols="43" rows="4"></textarea>
						<div class="user-page-message-box-button">
							<input type="submit" value="' . wfMessage( 'userboard_sendbutton' )->escaped() . '" class="site-button" />
						</div>' .
						Html::hidden( 'wpEditToken', $this->viewingUser->getEditToken() ) .
					'</form></div>';
			} elseif ( $this->viewingUser->isRegistered() && $this->viewingUser->isBlocked() ) {
				// Show a better i18n message for registered users who are blocked
				// @see https://phabricator.wikimedia.org/T266918
				$output .= '<div class="user-page-message-form-blocked">' .
					wfMessage( 'user-board-blocked-message' )->escaped() .
				'</div>';
			} else {
				$output .= '<div class="user-page-message-form">' .
					wfMessage( 'user-board-login-message' )->parse() .
				'</div>';
			}
		}

		$output .= '<div id="user-page-board">';
		$b = new UserBoard( $this->viewingUser );
		$output .= $b->displayMessages( $this->profileOwner, 0, 10 );
		$output .= '</div>';

		return $output;
	}

	/**
	 * Initialize UserProfile data for the given user if that hasn't been done
	 * already.
	 */
	private function initializeProfileData() {
		if ( !$this->profile_data ) {
			$profile = new UserProfile( $this->profileOwner );
			$this->profile_data = $profile->getProfile();
		}
	}
}