<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;

/**
 * A special page to allow users to update their social profile
 *
 * @file
 * @ingroup Extensions
 * @author David Pean <david.pean@gmail.com>
 * @copyright Copyright © 2007, Wikia Inc.
 * @license GPL-2.0-or-later
 */

class SpecialUpdateProfile extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'UpdateProfile' );
	}

	/**
	 * Initialize the user_profile records for a given user (either the current
	 * user or someone else).
	 *
	 * @param UserIdentity|null $user User object; null by default (=current user)
	 */
	function initProfile( $user = null ) {
		if ( $user === null ) {
			$user = $this->getUser();
		}

		$dbw = wfGetDB( DB_MASTER );
		$s = $dbw->selectRow(
			'user_profile',
			[ 'up_actor' ],
			[ 'up_actor' => $user->getActorId() ],
			__METHOD__
		);
		if ( $s === false ) {
			$dbw->insert(
				'user_profile',
				[ 'up_actor' => $user->getActorId() ],
				__METHOD__
			);
		}
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $section
	 */
	public function execute( $section ) {
		global $wgUpdateProfileInRecentChanges, $wgUserProfileThresholds, $wgAutoConfirmCount, $wgEmailConfirmToEdit;

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// This feature is only available for logged-in users.
		$this->requireLogin();

		// Database operations require write mode
		$this->checkReadOnly();

		// No need to allow blocked users to access this page, they could abuse it, y'know.
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		// Set the page title, robot policies, etc.
		$this->setHeaders();
		$out->setHTMLTitle( $this->msg( 'pagetitle', $this->msg( 'edit-profile-title' ) ) );

		/**
		 * Create thresholds based on user stats
		 */
		if ( is_array( $wgUserProfileThresholds ) && count( $wgUserProfileThresholds ) > 0 ) {
			$can_create = true;

			$stats = new UserStats( $user->getId(), $user->getName() );
			$stats_data = $stats->getUserStats();

			$thresholdReasons = [];
			foreach ( $wgUserProfileThresholds as $field => $threshold ) {
				// If the threshold is greater than the user's amount of whatever
				// statistic we're looking at, then it means that they can't use
				// this special page.
				// Why, oh why did I want to be so fucking smart with these
				// field names?! This str_replace() voodoo all over the place is
				// outright painful.
				$correctField = str_replace( '-', '_', $field );
				if ( $stats_data[$correctField] < $threshold ) {
					$can_create = false;
					$thresholdReasons[$threshold] = $field;
				}
			}

			$hasEqualEditThreshold = isset( $wgUserProfileThresholds['edit'] ) && $wgUserProfileThresholds['edit'] == $wgAutoConfirmCount;
			$can_create = ( $user->isAllowed( 'createpage' ) && $hasEqualEditThreshold ) ? true : $can_create;

			// Ensure we enforce profile creation exclusively to members who confirmed their email
			if ( $user->getEmailAuthenticationTimestamp() === null && $wgEmailConfirmToEdit === true ) {
				$can_create = false;
			}

			if ( !$can_create ) {
				$out->setPageTitle( $this->msg( 'user-profile-create-threshold-title' )->text() );
				$thresholdMessages = [];
				foreach ( $thresholdReasons as $requiredAmount => $reason ) {
					// Replace underscores with hyphens for consistency in i18n
					// message names.
					$reason = str_replace( '_', '-', $reason );
					$thresholdMessages[] = $this->msg( 'user-profile-create-threshold-' . $reason )->numParams( $requiredAmount )->parse();
				}
				// Set a useful message of why.
				if ( $user->getEmailAuthenticationTimestamp() === null && $wgEmailConfirmToEdit === true ) {
					$thresholdMessages[] = $this->msg( 'user-profile-create-threshold-only-confirmed-email' )->text();
				}
				$out->addHTML(
					$this->msg( 'user-profile-create-threshold-reason',
						$this->getLanguage()->commaList( $thresholdMessages )
					)->parse()
				);
				return;
			}
		}

		// Add CSS & JS
		$out->addModuleStyles( [
			'ext.socialprofile.clearfix',
			'ext.socialprofile.userprofile.tabs.css',
			'ext.socialprofile.special.updateprofile.css'
		] );
		$out->addModules( 'ext.userProfile.updateProfile' );

		if ( $request->wasPosted() && $user->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
			// NoJS support

			if ( !$section ) {
				$section = 'basic';
			}
			switch ( $section ) {
				case 'basic':
					$this->saveProfileBasic( $user );
					$this->saveBasicSettings( $user );
					break;
				case 'personal':
					$this->saveProfilePersonal( $user );
					break;
			}

			UserProfile::clearCache( $user );

			$log = new LogPage( 'profile' );
			if ( !$wgUpdateProfileInRecentChanges ) {
				$log->updateRecentChanges = false;
			}
			$log->addEntry(
				'profile',
				$user->getUserPage(),
				$this->msg( 'user-profile-update-log-section' )
					->inContentLanguage()->text() .
					" '{$section}'",
				[],
				$user
			);
			$out->addHTML(
				'<span class="profile-on">' .
				$this->msg( 'user-profile-update-saved' )->escaped() .
				'</span><br /><br />'
			);

			// create the user page if it doesn't exist yet
			$title = Title::makeTitle( NS_USER, $user->getName() );
			$page = WikiPage::factory( $title );
			if ( !$page->exists() ) {
				$page->doEditContent(
					ContentHandler::makeContent( '', $title ),
					'create user page',
					EDIT_SUPPRESS_RC
				);
			}
		}

		if ( !$section ) {
			$section = 'basic';
		}
		switch ( $section ) {
			case 'basic':
				$out->addHTML( $this->displayBasicForm( $user ) );
				break;
			case 'personal':
				$out->addHTML( $this->displayPersonalForm( $user ) );
				break;
		}
	}

	/**
	 * Save basic settings about the user (real name, e-mail address) into the
	 * database.
	 *
	 * @param User $user Representing the current user
	 */
	function saveBasicSettings( $user ) {

		$request = $this->getRequest();

		$user->setRealName( $request->getVal( 'real_name' ) );

		$user->saveSettings();
	}

	public static function formatBirthdayDB( $birthday ) {
		$dob = explode( '/', $birthday );
		if ( count( $dob ) == 2 || count( $dob ) == 3 ) {
			$year = $dob[2] ?? '00';
			$month = $dob[0];
			$day = $dob[1];
			$birthday_date = $year . '-' . $month . '-' . $day;
		} else {
			$birthday_date = null;
		}
		return $birthday_date;
	}

	// WHY DOES THIS DUPLICATION EXIST???? Don't remove it [Amazing Wikis]
	public static function formatBirthday( $birthday, $showYOB = false ) {
		$dob = explode( '-', $birthday );
		if ( count( $dob ) == 3 ) {
			$month = $dob[1];
			$day = $dob[2];
			$birthday_date = $month . '/' . $day;
			if ( $showYOB ) {
				$year = $dob[0];
				$birthday_date .= '/' . $year;
			}
		} else {
			$birthday_date = '';
		}
		return $birthday_date;
	}

	/**
	 * Save the basic user profile info fields into the database.
	 *
	 * @param UserIdentity|null $user User object, null by default (=the current user)
	 */
	function saveProfileBasic( $user = null ) {
		if ( $user === null ) {
			$user = $this->getUser();
		}

		$this->initProfile( $user );
		$dbw = wfGetDB( DB_MASTER );
		$request = $this->getRequest();

		$basicProfileData = [
			'up_location_country' => $request->getVal( 'location_country' ) ?? '',
			'up_birthday' => self::formatBirthdayDB( $request->getVal( 'birthday' ) ),
			'up_joindate' => self::formatBirthdayDB( $request->getVal( 'joindate' ) ),
			'up_websites' => $request->getVal( 'websites' )
		];

		$dbw->update(
			'user_profile',
			/* SET */$basicProfileData,
			/* WHERE */[ 'up_actor' => $user->getActorId() ],
			__METHOD__
		);

		// BasicProfileChanged hook
		$basicProfileData['up_name'] = $request->getVal( 'real_name' );
		Hooks::run( 'BasicProfileChanged', [ $user, $basicProfileData ] );
		// end of the hook

		UserProfile::clearCache( $user );
	}


	/**
	 * Save the user's biography into the database.
	 *
	 * @param UserIdentity|null $user
	 */
	function saveProfilePersonal( $user = null ) {
		if ( $user === null ) {
			$user = $this->getUser();
		}

		$this->initProfile( $user );
		$request = $this->getRequest();

		$dbw = wfGetDB( DB_MASTER );

		$biographyData = [
			'up_about' => $request->getVal( 'about' ),
			'up_hobbies' => $request->getVal( 'hobbies' ),
			'up_bestMoment' => $request->getVal( 'bestMoment' ),
			'up_favoriteCharacter ' => $request->getVal( 'favoriteCharacter' ),
			'up_favoriteItem' => $request->getVal( 'favoriteItem' ),
			'up_worstMoment' => $request->getVal( 'worstMoment' )
		];

		$dbw->update(
			'user_profile',
			/* SET */$biographyData,
			/* WHERE */[ 'up_actor' => $user->getActorId() ],
			__METHOD__
		);

		// PersonalAccountLinksChanged hook
		Hooks::run( 'PersonalInterestsChanged', [ $user, $accountLinksData ] );
		// end of the hook

		UserProfile::clearCache( $user );
	}


	/**
	 * Save the user's social media accounts into the database.
	 *
	 * @param UserIdentity|null $user
	 */
	function saveProfileAccountLinks( $user = null ) {
		if ( $user === null ) {
			$user = $this->getUser();
		}

		$this->initProfile( $user );
		$request = $this->getRequest();

		$dbw = wfGetDB( DB_MASTER );

		$accountLinksData = [
			'up_friendcode' => $request->getVal( 'friendcode' ),
			'up_steam' => $request->getVal( 'steam' ),
			'up_xbox' => $request->getVal( 'xbox' ),
			'up_twitter' => $request->getVal( 'twitter' ),
			'up_mastodon' => $request->getVal( 'mastodon' ),
			'up_instagram' => $request->getVal( 'instagram' ),
			'up_discord ' => $request->getVal( 'discord ' ),
			'up_irc' => $request->getVal( 'irc' ),
			'up_reddit' => $request->getVal( 'reddit' ),
			'up_twitch' => $request->getVal( 'twitch' ),
			'up_youtube' => $request->getVal( 'youtube' ),
			'up_rumble' => $request->getVal( 'rumble' ),
			'up_bitchute' => $request->getVal( 'bitchute' )
		];

		$dbw->update(
			'user_profile',
			/* SET */$accountLinksData ,
			/* WHERE */[ 'up_actor' => $user->getActorId() ],
			__METHOD__
		);

		// PersonalAccountLinksChanged hook
		Hooks::run( 'PersonalInterestsChanged', [ $user, $accountLinksData ] );
		// end of the hook

		UserProfile::clearCache( $user );
	}



	/**
	 * @param User $user
	 *
	 * @return string
	 */
	function displayBasicForm( $user ) {
		$dbr = wfGetDB( DB_REPLICA );
		$s = $dbr->selectRow( 'user_profile',
			[
				'up_location_country', 'up_birthday', 'up_occupation', 
				'up_about', 'up_schools',
				'up_places_lived', 'up_websites'
			],
			[ 'up_actor' => $user->getActorId() ],
			__METHOD__
		);

		$showYOB = true;
		if ( $s !== false ) {
			$location_country = $s->up_location_country;
			$about = $s->up_about;
			$occupation = $s->up_occupation;
			$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
			$showYOB = $userOptionsLookup->getIntOption( $user, 'showyearofbirth', !isset( $s->up_birthday ) ) == 1;
			$birthday = self::formatBirthday( $s->up_birthday, $showYOB );
			//$showYOJ = $userOptionsLookup->getIntOption( $user, 'showyearofjoin', !isset( $s->up_joindate ) ) == 1;
			//$joindate = self::formatBirthday( $s->up_joindate, $showYOJ );
			$schools = $s->up_schools;
			$places = $s->up_places_lived;
			$websites = $s->up_websites;
		}

		if ( !isset( $location_country ) ) {
			$location_country = $this->msg( 'user-profile-default-country' )->inContentLanguage()->escaped();
		}


		$countries = explode( "\n*", $this->msg( 'userprofile-country-list' )->inContentLanguage()->text() );
		array_shift( $countries );

		$this->getOutput()->setPageTitle( $this->msg( 'edit-profile-title' )->escaped() );

		$form = UserProfile::getEditProfileNav( $this->msg( 'user-personal-biography-title' )->escaped() );
		$form .= '<form action="" method="post" enctype="multipart/form-data" name="profile">';
		// NoJS thing -- JS sets this to false, which means that in execute() we skip updating
		// profile field visibilities for users with JS enabled can do and have already done that
		// with the nice JS-enabled drop-down (instead of having to rely on a plain ol'
		// <select> + form submission, as no-JS users have to)
		$form .= Html::hidden( 'should_update_field_visibilities', true );
		$form .= '<div class="profile-info clearfix">';
		$form .= '<div class="profile-update">
			<p class="profile-update-title">' . $this->msg( 'user-profile-personal-info' )->escaped() . '</p>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-name' )->escaped() . '</p>
			<p class="profile-update-unit"><input type="text" size="25" name="real_name" id="real_name" value="' . htmlspecialchars( $real_name, ENT_QUOTES ) . '"/></p>
			<div class="visualClear">' . '</div>
		<div class="visualClear"></div>';

		$form .= '<div class="profile-update">
			<p class="profile-update-unit-left" id="location_country_label">' . $this->msg( 'user-profile-personal-country' )->escaped() . '</p>';
		$form .= '<p class="profile-update-unit">';
		$form .= '<select name="location_country" id="location_country"><option></option>';

		foreach ( $countries as $country ) {
			$form .= Xml::option( $country, $country, ( $country == $location_country ) );
		}

		$form .= '</select>';
		$form .= '</p>
			<div class="visualClear">' . '</div>
		</div>
		<div class="visualClear"></div>';


		$form .= '<div class="profile-update">
			<p class="profile-update-title">' . $this->msg( 'user-profile-personal-birthday' )->escaped() . '</p>
			<p class="profile-update-unit-left" id="birthday-format">' .
				$this->msg( $showYOB ? 'user-profile-personal-birthdate-with-year' : 'user-profile-personal-birthdate' )->escaped() .
			'</p>
			<p class="profile-update-unit"><input type="text"' .
			( $showYOB ? ' class="long-birthday"' : null ) .
			' size="25" name="birthday" id="birthday" value="' .
			( isset( $birthday ) ? htmlspecialchars( $birthday, ENT_QUOTES ) : '' ) . '" /></p>
			<div class="visualClear">' . '</div>
		</div><div class="visualClear"></div>';

		$form .= '<div class="profile-update">
			<p class="profile-update-title">' . $this->msg( 'user-profile-personal-join' )->escaped() . '</p>
			<p class="profile-update-unit-left" id="birthday-format">' .
				$this->msg( $showYOJ ? 'user-profile-personal-join-with-year' : 'user-profile-personal-joindate' )->escaped() .
			'</p>
			<p class="profile-update-unit"><input type="text"' .
			( $showYOJ ? ' class="long-join"' : null ) .
			' size="25" name="joindate" id="joindate" value="' .
			( isset( $joindate ) ? htmlspecialchars( $joindate, ENT_QUOTES ) : '' ) . '" /></p>
			<div class="visualClear">' . '</div>
		</div><div class="visualClear"></div>

		<div class="profile-update" id="profile-update-personal-websites">
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-websites' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="websites " id="websites " rows="3" cols="75">' . ( isset( $websites ) ? htmlspecialchars( $websites , ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
		</div>
		<div class="visualClear"></div>';



		$form .= '<div class="profile-update" id="profile-update-personal-aboutme">
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-aboutme' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="about" id="about" rows="3" cols="75">' . ( isset( $about ) ? htmlspecialchars( $about, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
		</div>
		<div class="visualClear"></div>

		<div class="profile-update" id="profile-update-personal-hobbies">
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-hobbies' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="hobbies" id="hobbies" rows="2" cols="75">' . ( isset( $hobbies) ? htmlspecialchars( $hobbies, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
		</div>
		<div class="visualClear"></div>

		<div class="profile-update">
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-best-moment' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="bestZeldaMoment" id="schools" rows="2" cols="75">' . ( isset( $bestMoment) ? htmlspecialchars( $bestMoment, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
		</div>
		<div class="visualClear"></div>

		<div class="profile-update">
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-favorite-character' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="places" id="places" rows="3" cols="75">' . ( isset( $favoriteCharacter ) ? htmlspecialchars( $favoriteCharacter , ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
		</div>
		<div class="visualClear"></div>

		<div class="profile-update">
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-favorite-item' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="places" id="places" rows="3" cols="75">' . ( isset( $favoriteItem ) ? htmlspecialchars( $favoriteItem , ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
		</div>
		<div class="visualClear"></div>

		<div class="profile-update">
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-worst-moment' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="places" id="places" rows="3" cols="75">' . ( isset( $worstMoment ) ? htmlspecialchars( $worstMoment , ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
		</div>
		<div class="visualClear"></div>';

		$form .= '
			<input type="submit" class="site-button" value="' . $this->msg( 'user-profile-update-button' )->escaped() . '" size="20" onclick="document.profile.submit()" />
			</div>
			<input type="hidden" name="wpEditToken" value="' . htmlspecialchars( $this->getUser()->getEditToken(), ENT_QUOTES ) . '" />
		</form>';

		return $form;
	}

	/**
	 * @param UserIdentity $user
	 *
	 * @return string
	 */
	function displayPersonalForm( $user ) {
		$dbr = wfGetDB( DB_REPLICA );
		$s = $dbr->selectRow(
			'user_profile',
			[
				'up_about', 'up_places_lived', 'up_websites', 'up_relationship',
				'up_occupation', 'up_companies', 'up_schools', 'up_movies',
				'up_tv', 'up_music', 'up_books', 'up_video_games',
				'up_magazines', 'up_snacks', 'up_drinks'
			],
			[ 'up_actor' => $user->getActorId() ],
			__METHOD__
		);

		if ( $s !== false ) {
			$places = $s->up_places_lived;
			$websites = $s->up_websites;
			$relationship = $s->up_relationship;
			$companies = $s->up_companies;
			$schools = $s->up_schools;
			$movies = $s->up_movies;
			$tv = $s->up_tv;
			$music = $s->up_music;
			$books = $s->up_books;
			$videogames = $s->up_video_games;
			$magazines = $s->up_magazines;
			$snacks = $s->up_snacks;
			$drinks = $s->up_drinks;
		}

		$this->getOutput()->setPageTitle( $this->msg( 'edit-profile-title' )->escaped() );

		$form = UserProfile::getEditProfileNav( $this->msg( 'user-profile-section-accountLinks' )->escaped() );
		$form .= '<form action="" method="post" enctype="multipart/form-data" name="profile">';
		// NoJS thing -- JS sets this to false, which means that in execute() we skip updating
		// profile field visibilities for users with JS enabled can do and have already done that
		// with the nice JS-enabled drop-down (instead of having to rely on a plain ol'
		// <select> + form submission, as no-JS users have to)
		$form .= Html::hidden( 'should_update_field_visibilities', true );
		$form .= '<div class="profile-info profile-info-other-info clearfix">
			<div class="profile-update">
			<p class="profile-update-title">' . $this->msg( 'account-links' )->escaped() . '</p>

			<p class="profile-update-unit-left">' . $this->msg( 'account-links-friendcode' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="friendcode" id="friendcode" rows="3" cols="75">' . ( isset( $friendcode) ? htmlspecialchars( $friendcode, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
			<p class="profile-update-unit-left">' . $this->msg( 'account-links-steam' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="steam" id="steam" rows="3" cols="75">' . ( isset( $steam) ? htmlspecialchars( $steam, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
			<p class="profile-update-unit-left">' . $this->msg( 'account-links-xbox' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="xbox" id="xbox" rows="3" cols="75">' . ( isset( $xbox ) ? htmlspecialchars( $xbox, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
			<p class="profile-update-unit-left">' . $this->msg( 'account-links-twitter' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="twitter" id="twitter" rows="3" cols="75">' . ( isset( $twitter ) ? htmlspecialchars( $twitter, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
			<p class="profile-update-unit-left">' . $this->msg( 'account-links-mastodon' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="mastodon" id="mastodon" rows="3" cols="75">' . ( isset( $mastodon ) ? htmlspecialchars( $mastodon, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
			<p class="profile-update-unit-left">' . $this->msg( 'account-links-instagram' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="instagram" id="instagram" rows="3" cols="75">' . ( isset( $instagram ) ? htmlspecialchars( $instagram, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' .  '</div>
			<p class="profile-update-unit-left">' . $this->msg( 'account-links-discord' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="discord" id="discord" rows="3" cols="75">' . ( isset( $discord ) ? htmlspecialchars( $discord, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			
			<div class="visualClear">' . '</div>
			<p class="profile-update-unit-left">' . $this->msg( 'account-links-irc' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="irc" id="irc" rows="3" cols="75">' . ( isset( $irc ) ? htmlspecialchars( $irc, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
			<p class="profile-update-unit-left">' . $this->msg( 'account-links-reddit' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="reddit" id="reddit" rows="3" cols="75">' . ( isset( $reddit ) ? htmlspecialchars( $reddit, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
			<p class="profile-update-unit-left">' . $this->msg( 'account-links-twitch' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="twitch" id="twitch" rows="3" cols="75">' . ( isset( $twitch ) ? htmlspecialchars( $twitch, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
			<p class="profile-update-unit-left">' . $this->msg( 'account-links-youtube' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="youtube" id="youtube" rows="3" cols="75">' . ( isset( $youtube ) ? htmlspecialchars( $youtube, ENT_QUOTES ) : '' ) . '</textarea>
			</p>


			<div class="visualClear">' . '</div>
			<p class="profile-update-unit-left">' . $this->msg( 'account-links-rumble' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="rumble" id="rumble" rows="3" cols="75">' . ( isset( $rumble ) ? htmlspecialchars( $rumble, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear">' . '</div>
			<p class="profile-update-unit-left">' . $this->msg( 'account-links-bitchute' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="bitchute" id="bitchute" rows="3" cols="75">' . ( isset( $bitchute ) ? htmlspecialchars( $bitchute, ENT_QUOTES ) : '' ) . '</textarea>
			</p>

			</div>
			<input type="submit" class="site-button" value="' . $this->msg( 'user-profile-update-button' )->escaped() . '" size="20" onclick="document.profile.submit()" />
			</div>
			<input type="hidden" name="wpEditToken" value="' . htmlspecialchars( $this->getUser()->getEditToken(), ENT_QUOTES ) . '" />
		</form>';

		return $form;
	}

}