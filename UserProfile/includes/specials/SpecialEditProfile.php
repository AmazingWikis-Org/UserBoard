<?php

use MediaWiki\User\UserIdentity;

/**
 * A special page to allow privileged users to update others' social profiles
 *
 * @file
 * @ingroup Extensions
 * @author Frozen Wind <tuxlover684@gmail.com>
 * @license GPL-2.0-or-later
 */

/*
	public function deleteUserProfile( $up_id ) {
		$dbw = wfGetDB( DB_MASTER );
		$getRow = $dbw->selectRow(
			'user_profile',
			[ 'up_actor' ],
			[ 'up_actor' => $up_id ],
			__METHOD__
		);
		if ( $getRow !== false ) {
			$dbw->delete(
				'user_profile',
				[ 'up_actor' => $up_id ],
				__METHOD__
			);
		}
	}

*/
class SpecialEditProfile extends SpecialUpdateProfile {

	public function __construct() {
		SpecialPage::__construct( 'EditProfile', 'editothersprofiles' );
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par
	 */
	public function execute( $par ) {
		global $wgUpdateProfileInRecentChanges;

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// This feature is only available for logged-in users.
		$this->requireLogin();

		// make sure user has the correct permissions
		$this->checkPermissions();

		// Database operations require write mode
		$this->checkReadOnly();

		// No need to allow blocked users to access this page, they could abuse it, y'know.
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		// Set the page title, robot policies, etc.
		$this->setHeaders();

		// Add CSS & JS
		$out->addModuleStyles( [
			'ext.socialprofile.userprofile.tabs.css',
			'ext.socialprofile.special.updateprofile.css'
		] );
		$out->addModules( 'ext.userProfile.updateProfile' );

		// Get the user's name from the wpUser URL parameter
		$userFromRequest = $request->getText( 'wpUser' );

		// If the wpUser parameter isn't set but a parameter was passed to the
		// special page, use the given parameter instead
		if ( !$userFromRequest && $par ) {
			$userFromRequest = $par;
		}

		// Still not set? Just give up and show the "search for a user" form...
		if ( !$userFromRequest ) {
			$this->createUserInputForm();
			return;
		}

		$target = User::newFromName( $userFromRequest );

		if ( !$target || $target->getId() == 0 ) {
			$out->addHTML( $this->msg( 'nosuchusershort', htmlspecialchars( $userFromRequest ) )->escaped() );
			$this->createUserInputForm();
			return;
		}

		if ( $request->wasPosted() && $user->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
			$this->saveProfileBasic( $target );
			$this->saveBasicSettings( $target );
			$this->saveProfilePersonal( $target );

			UserProfile::clearCache( $target );

			$log = new LogPage( 'profile' );
			if ( !$wgUpdateProfileInRecentChanges ) {
				$log->updateRecentChanges = false;
			}
			$log->addEntry(
				'profile',
				$target->getUserPage(),
				$this->msg( 'user-profile-edit-profile',
					[ '[[User:' . $target->getName() . ']]' ] )
				->inContentLanguage()->text(),
				[],
				$user
			);
			$out->addHTML(
				'<span class="profile-on">' .
				$this->msg( 'user-profile-edit-profile-update-saved' )->escaped() .
				'</span><br /><br />'
			);

			// create the user page if it doesn't exist yet
			$title = Title::makeTitle( NS_USER, $target->getName() );
			$page = new WikiPage( $title );
			if ( !$page->exists() ) {
				$page->doEditContent(
					ContentHandler::makeContent( '', $title ),
					'create user page',
					EDIT_SUPPRESS_RC
				);
			}
		}

		$out->addHTML( $this->displayBasicForm( $target ) );
		$out->addHTML( $this->displayPersonalForm( $target ) );

		// Set the page title *once again* here
		// Needed because display*Form() functions can and do override our title so
		// if we don't do this here, the page title ends up being something like
		// "Other information" and the HTML title ends up being "Tidbits"
		$out->setPageTitle( $this->msg( 'editprofile' ) );
		$out->setHTMLTitle( $this->msg( 'pagetitle', $this->msg( 'edit-profiles-title' ) ) );
	}

	function createUserInputForm() {
		$actionUrl = $this->getPageTitle()->getLocalURL( '' );
		$formDescriptor = [
			'text' => [
				'type' => 'user',
				'name' => 'wpUser',
				'label-message' => 'username',
				'size' => 60,
				'id' => 'mw-socialprofile-user',
				'maxlength' => '200'
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->setSubmitTextMsg( 'edit' )
			->setWrapperLegend( null )
			->setAction( $actionUrl )
			->setId( 'mw-socialprofile-edit-profile-userform' )
			->setMethod( 'get' )
			->prepareForm()
			->displayForm( false );

		return true;
	}

	function saveBasicSettings( $tar ) {
		$request = $this->getRequest();

		$tar->setRealName( $request->getVal( 'real_name' ) );

		$tar->saveSettings();
	}

	function displayBasicForm( $tar ) {
		$dbr = wfGetDB( DB_REPLICA );
		$s = $dbr->selectRow(
			'user_profile',
			[
				'up_location_country', 'up_birthday', 'up_occupation', 
				'up_about', 'up_schools',
				'up_places_lived', 'up_websites'
			],
			[ 'up_actor' => $tar->getActorId() ],
			__METHOD__
		);

		if ( $s !== false ) {
			$location_country = $s->up_location_country;
			$about = $s->up_about;
			$occupation = $s->up_occupation;
			$birthday = self::formatBirthday( $s->up_birthday, true );
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
		$form = UserProfile::getEditProfileNav( $this->msg( 'user-profile-section-personal' )->escaped() );
		$form = '<form action="" method="post" enctype="multipart/form-data" name="profile">';
		$form .= '<div class="profile-info visualClear">';
		$form .= '<div class="profile-update">
			<p class="profile-update-title">' . $this->msg( 'user-profile-personal-info' )->escaped() . <div class="visualClear"></div>' . '</p>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-name' )->escaped() . '</p>
			<p class="profile-update-unit"><input type="text" size="25" name="real_name" id="real_name" value="' . htmlspecialchars( $real_name, ENT_QUOTES ) . '"/></p>
			<div class="visualClear"></div>';
		<div class="visualClear"></div>';

		$form .= '<div class="profile-update">
			<p class="profile-update-title">' . $this->msg( 'user-profile-personal-country' )->escaped() . '</p>
		$form .= '<p class="profile-update-unit">';
		// Hidden helper for UpdateProfile.js since JS cannot directly access PHP variables
		$form .= '<select name="location_country" id="location_country"><option></option>';

		foreach ( $countries as $country ) {
			$form .= Xml::option( $country, $country, ( $country == $location_country ) );
		}

		$form .= '</select>';
		$form .= '</p>
			<div class="visualClear"></div>
		</div>
		<div class="visualClear"></div>';

		$form .= '<div class="profile-update">
			<p class="profile-update-title">' . $this->msg( 'user-profile-personal-birthday' )->escaped() . '</p>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-join-with-year' )->escaped() . '</p>
			<p class="profile-update-unit"><input type="text" class="long-birthday" size="25" name="birthday" id="birthday" value="' . ( isset( $birthday ) ? htmlspecialchars( $birthday, ENT_QUOTES ) : '' ) . '" /></p>
			<div class="visualClear"></div>
		</div><div class="visualClear"></div>';

		$form .= '<div class="profile-update">
			<p class="profile-update-title">' . $this->msg( 'user-profile-personal-join' )->escaped() . '</p>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-birthdate-with-year' )->escaped() . '</p>
			<p class="profile-update-unit"><input type="text" class="long-birthday" size="25" name="birthday" id="birthday" value="' . ( isset( $birthday ) ? htmlspecialchars( $birthday, ENT_QUOTES ) : '' ) . '" /></p>
			<div class="visualClear"></div>
		</div><div class="visualClear"></div>';

		$form .= '<div class="profile-update" id="profile-update-personal-aboutme">
			<p class="profile-update-title">' . $this->msg( 'user-profile-personal-aboutme' )->escaped() . '</p>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-aboutme' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="about" id="about" rows="3" cols="75">' . ( isset( $about ) ? htmlspecialchars( $about, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear"></div>
		</div>
		<div class="visualClear"></div>

		<div class="profile-update" id="profile-update-personal-work">
			<p class="profile-update-title">' . $this->msg( 'user-profile-personal-work' )->escaped() . '</p>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-occupation' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="occupation" id="occupation" rows="2" cols="75">' . ( isset( $occupation ) ? htmlspecialchars( $occupation, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear"></div>
		</div>
		<div class="visualClear"></div>

		<div class="profile-update" id="profile-update-personal-education">
			<p class="profile-update-title">' . $this->msg( 'user-profile-personal-education' )->escaped() . '</p>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-schools' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="schools" id="schools" rows="2" cols="75">' . ( isset( $schools ) ? htmlspecialchars( $schools, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear"></div>
		</div>
		<div class="visualClear"></div>

		<div class="profile-update" id="profile-update-personal-places">
			<p class="profile-update-title">' . $this->msg( 'user-profile-personal-places' )->escaped() . '</p>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-placeslived' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="places" id="places" rows="3" cols="75">' . ( isset( $places ) ? htmlspecialchars( $places, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear"></div>
		</div>
		<div class="visualClear"></div>

		<div class="profile-update" id="profile-update-personal-web">
			<p class="profile-update-title">' . $this->msg( 'user-profile-personal-web' )->escaped() . '</p>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-personal-websites' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="websites" id="websites" rows="2" cols="75">' . ( isset( $websites ) ? htmlspecialchars( $websites, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear"></div>
		</div>
		<div class="visualClear"></div>';

		$form .= '</div>';

		return $form;
	}

	function displayPersonalForm( $tar ) {
		$dbr = wfGetDB( DB_REPLICA );
		$s = $dbr->selectRow(
			'user_profile',
			[
				'up_about', 'up_places_lived', 'up_websites', 'up_relationship',
				'up_occupation', 'up_companies', 'up_schools', 'up_movies',
				'up_tv', 'up_music', 'up_books', 'up_video_games',
				'up_magazines', 'up_snacks', 'up_drinks'
			],
			[ 'up_actor' => $tar->getActorId() ],
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

		$this->getOutput()->setPageTitle( $this->msg( 'user-profile-section-interests' )->escaped() );
		// $form = UserProfile::getEditProfileNav( $this->msg( 'user-profile-section-interests' )->escaped() );
		$form = '<div class="profile-info visualClear">
			<div class="profile-update">
			<p class="profile-update-title">' . $this->msg( 'user-profile-interests-entertainment' )->escaped() . '</p>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-interests-movies' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="movies" id="movies" rows="3" cols="75">' . ( isset( $movies ) ? htmlspecialchars( $movies, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear"></div>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-interests-tv' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="tv" id="tv" rows="3" cols="75">' . ( isset( $tv ) ? htmlspecialchars( $tv, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear"></div>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-interests-music' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="music" id="music" rows="3" cols="75">' . ( isset( $music ) ? htmlspecialchars( $music, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear"></div>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-interests-books' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="books" id="books" rows="3" cols="75">' . ( isset( $books ) ? htmlspecialchars( $books, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear"></div>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-interests-magazines' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="magazines" id="magazines" rows="3" cols="75">' . ( isset( $magazines ) ? htmlspecialchars( $magazines, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear"></div>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-interests-videogames' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="videogames" id="videogames" rows="3" cols="75">' . ( isset( $videogames ) ? htmlspecialchars( $videogames, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear"></div>
			</div>
			<div class="profile-info visualClear">
			<p class="profile-update-title">' . $this->msg( 'user-profile-interests-eats' )->escaped() . '</p>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-interests-foodsnacks' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="snacks" id="snacks" rows="3" cols="75">' . ( isset( $snacks ) ? htmlspecialchars( $tv, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear"></div>
			<p class="profile-update-unit-left">' . $this->msg( 'user-profile-interests-drinks' )->escaped() . '</p>
			<p class="profile-update-unit">
				<textarea name="drinks" id="drinks" rows="3" cols="75">' . ( isset( $drinks ) ? htmlspecialchars( $drinks, ENT_QUOTES ) : '' ) . '</textarea>
			</p>
			<div class="visualClear"></div>
			</div>
			</div>';

		return $form;
	}


	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return (usually 10)
	 * @param int $offset Number of results to skip (usually 0)
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		$user = User::newFromName( $search );
		if ( !$user ) {
			// No prefix suggestion for invalid user
			return [];
		}
		// Autocomplete subpage as user list - public to allow caching
		return UserNamePrefixSearch::search( 'public', $search, $limit, $offset );
	}

}