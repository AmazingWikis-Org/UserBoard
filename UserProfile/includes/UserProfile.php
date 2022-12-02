<?php
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;

/**
 * Class to access profile data for a user
 */
class UserProfile {
	/**
	 * @var User User object whose profile is being viewed
	 */
	public $user;

	/**
	 * @var int The current user's user ID.
	 * @deprecated Prefer using $this->user to get an actor ID instead
	 */
	public $user_id;

	/**
	 * @var string The current user's user name.
	 * @deprecated Prefer using $this->user instead
	 */
	public $user_name;

	/**
	 * @var Unused remove me?
	 */
	public $profile;

	/**
	 * @var int used in getProfileComplete()
	 */
	public $profile_fields_count;

	/**
	 * @var array Array of valid profile fields; used in getProfileComplete()
	 * These _mostly_ correspond to the fields in the user_profile DB table.
	 * If a field is not defined here, it won't be shown in profile pages!
	 * @see https://phabricator.wikimedia.org/T212290
	 */
	public $profile_fields = [
		'name', 
		'location_country', 
		'birthday',
		'joindate',
		'websites',
		'about',
		'hobby', 
		'bestMoment', 
		'favoriteCharacter', 
		'favoriteItem', 
		'worstMoment', 
		'friendcode', 
		'steam', 
		'xbox', 
		'twitter', 
		'mastodon', 
		'instagram', 
		'discord ', 
		'irc', 
		'reddit', 
		'twitch', 
		'youtube', 
		'rumble', 
		'bitchute'
	];

	/**
	 * @var array Unused, remove me?
	 */
	public $profile_missing = [];

	/**
	 * @param User|string $username User object (preferred) or user name (legacy b/c)
	 * @todo FIXME: will explode horribly if $username is an IP address (can't call
	 *  the getters here because $this->user is not an object then; adding an
	 *  instanceof check here will cause getProfile() instead to explode, etc.)
	 */
	public function __construct( $username ) {
		if ( $username instanceof User ) {
			$this->user = $username;
		} else {
			$this->user = User::newFromName( $username );
		}
		$this->user_name = $this->user->getName();
		$this->user_id = $this->user->getId();
	}

	/**
	 * Gets the memcached key for the given user.
	 *
	 * @param UserIdentity $user User object for the desired user
	 * @return string
	 */
	public static function getCacheKey( $user ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		return $cache->makeKey( 'user', 'profile', 'info', 'actor_id', $user->getActorId() );
	}

	/**
	 * Deletes the memcached key for the given user.
	 *
	 * @param UserIdentity $user User object for the desired user
	 */
	public static function clearCache( $user ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		$cache->delete( self::getCacheKey( $user ) );
	}

	/**
	 * Loads social profile info for the current user.
	 * First tries fetching the info from memcached and if that fails,
	 * queries the database.
	 * Fetched info is cached in memcached.
	 *
	 * @return array
	 */
	public function getProfile() {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		$this->user->load();

		// Try cache first
		$key = self::getCacheKey( $this->user );
		$data = $cache->get( $key );
		if ( $data ) {
			wfDebug( "Got user profile info for {$this->user->getName()} from cache\n" );
			$profile = $data;
		} else {
			wfDebug( "Got user profile info for {$this->user->getName()} from DB\n" );
			$dbr = wfGetDB( DB_REPLICA );
			$row = $dbr->selectRow(
				'user_profile',
				'*',
				[ 'up_actor' => $this->user->getActorId() ],
				__METHOD__,
				[ 'LIMIT' => 5 ]
			);

			$profile = [];
			if ( $row ) {
				$profile['actor'] = $this->user->getActorId();
			} else {
				$profile['user_page_type'] = 1;
				$profile['actor'] = 0;
			}
			$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
			$showYOB = $userOptionsLookup->getIntOption( $this->user, 'showyearofbirth', !isset( $row->up_birthday ) ) == 1;
			$issetUpBirthday = $row->up_birthday ?? '';
			//$showYOJ = $userOptionsLookup->getIntOption( $this->user, 'showyearofjoin', !isset( $row->up_join ) ) == 1;
			//$issetUpjoin = $row->up_join ?? '';

			$profile['location_country'] = $row->up_location_country ?? '';
			$profile['birthday'] = $this->formatBirthday( $issetUpBirthday, $showYOB );
			//$profile['joindate'] = $this->formatBirthday( $issetUpJoin, $showYOJ );
			$profile['name'] = $row->up_name ?? '';
			$profile['websites'] = $row->up_websites ?? '';
			$profile['about'] = $row->up_about ?? '';
			$profile['hobby'] = $row->up_hobby ?? '';
			$profile['bestMoment'] = $row->up_bestMoment ?? '';
			$profile['favoriteCharacter'] = $row->up_favoriteCharacter ?? '';
			$profile['favoriteItem'] = $row->up_favoriteItem ?? '';
			$profile['worstMoment'] = $row->up_worstMoment ?? '';
			$profile['friendcode'] = $row->up_friendcode ?? '';
			$profile['steam'] = $row->up_steam ?? '';
			$profile['xbox'] = $row->up_xbox ?? '';
			$profile['mastodon'] = $row->up_mastodon ?? '';
			$profile['instagram'] = $row->up_instagram ?? '';
			$profile['discord '] = $row->up_discord ?? '';
			$profile['irc'] = $row->up_irc ?? '';
			$profile['reddit'] = $row->up_reddit ?? '';
			$profile['twitch'] = $row->up_twitch ?? '';
			$profile['youtube'] = $row->up_youtube ?? '';
			$profile['rumble'] = $row->up_rumble ?? '';
			$profile['bitchute'] = $row->up_bitchute?? '';
			$profile['user_page_type'] = $row->up_type ?? '';
			$cache->set( $key, $profile );
		}

		//$profile['real_name'] = $this->user->getRealName();

		return $profile;
	}

	/**
	 * Format the user's birthday.
	 *
	 * @param string $birthday birthday in YYYY-MM-DD format
	 * @param bool $showYear
	 * @return string formatted birthday
	 */
	function formatBirthday( $birthday, $showYear = true ) {
		$dob = explode( '-', $birthday );
		if ( count( $dob ) == 3 ) {
			$month = $dob[1];
			$day = $dob[2];
			if ( !$showYear ) {
				if ( $dob[1] == '00' && $dob[2] == '00' ) {
					return '';
				} else {
					return date( 'F jS', mktime( 0, 0, 0, $month, $day ) );
				}
			}
			$year = $dob[0];
			if ( $dob[0] == '00' && $dob[1] == '00' && $dob[2] == '00' ) {
				return '';
			} else {
				return date( 'F jS, Y', mktime( 0, 0, 0, $month, $day, $year ) );
			}
			// return $day . ' ' . $wgLang->getMonthNameGen( $month );
		}
		return $birthday;
	}

	/**
	 * How many % of this user's profile is complete?
	 * Currently unused, I think that this might've been used in some older
	 * ArmchairGM code, but this looks useful enough to be kept around.
	 *
	 * @return int
	 */
	public function getProfileComplete() {
		$complete_count = 0;

		// Check all profile fields
		$profile = $this->getProfile();
		foreach ( $this->profile_fields as $field ) {
			if ( $profile[$field] ) {
				$complete_count++;
			}
			$this->profile_fields_count++;
		}

		// Check if the user has a non-default avatar
		$this->profile_fields_count++;

		return round( $complete_count / $this->profile_fields_count * 100 );
	}

	public static function getEditProfileNav( $current_nav ) {
		$lines = explode( "\n", wfMessage( 'update_profile_nav' )->inContentLanguage()->text() );
		$output = '<div class="profile-tab-bar">';
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		foreach ( $lines as $line ) {
			if ( strpos( $line, '*' ) !== 0 ) {
				continue;
			} else {
				$line = explode( '|', trim( $line, '* ' ), 2 );
				$page = Title::newFromText( $line[0] );
				$link_text = $line[1];

				// Maybe it's the name of a system message? (bug #30030)
				$msgObj = wfMessage( $line[1] );
				if ( !$msgObj->isDisabled() ) {
					$link_text = $msgObj->text();
				}

				$output .= '<div class="profile-tab' . ( ( $current_nav == $link_text ) ? '-on' : '' ) . '">';
				$output .= $linkRenderer->makeLink( $page, $link_text );
				$output .= '</div>';
			}
		}

		$output .= '<div class="visualClear"></div></div>';

		return $output;
	}
}