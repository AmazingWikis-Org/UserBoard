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

		$updater->addExtensionTable( 'user_relationship', "$dir/UserRelationship/sql/user_relationship$dbExt.sql" );
		$updater->addExtensionTable( 'user_board', "$dir/UserBoard/sql/user_board$dbExt.sql" );
		$updater->addExtensionTable( 'user_fields_privacy', "$dir/UserProfile/sql/user_fields_privacy$dbExt.sql" );
		$updater->addExtensionTable( 'user_profile', "$dir/UserProfile/sql/user_profile$dbExt.sql" );
		$updater->dropExtensionField( 'user_profile', 'up_last_seen', "$dir/UserProfile/sql/patches/patch-drop-column-up_last_seen.sql" );

		// Actor support

		# UserBoard
		if ( !$db->fieldExists( 'user_board', 'ub_actor', __METHOD__ ) ) {
			// 1) add new actor columns
			$updater->addExtensionField( 'user_board', 'ub_actor_from', "$dir/UserBoard/sql/patches/actor/add-ub_actor_from$dbExt.sql" );
			$updater->addExtensionField( 'user_board', 'ub_actor', "$dir/UserBoard/sql/patches/actor/add-ub_actor$dbExt.sql" );
			// 2) add the corresponding indexes
			$updater->addExtensionIndex( 'user_board', 'ub_actor_from', "$dir/UserBoard/sql/patches/actor/add-ub_actor_from_index.sql" );
			$updater->addExtensionIndex( 'user_board', 'ub_actor', "$dir/UserBoard/sql/patches/actor/add-ub_actor_index.sql" );
			// 3) populate the new column with data
			$updater->addExtensionUpdate( [
				'runMaintenance',
				'MigrateOldUserBoardUserColumnsToActor',
				"$dir/UserBoard/maintenance/migrateOldUserBoardUserColumnsToActor.php"
			] );
			// 4) drop old columns & indexes
			$updater->dropExtensionField( 'user_board', 'ub_user_id', "$dir/UserBoard/sql/patches/actor/drop-ub_user_id.sql" );
			$updater->dropExtensionField( 'user_board', 'ub_user_name', "$dir/UserBoard/sql/patches/actor/drop-ub_user_name.sql" );
			$updater->dropExtensionField( 'user_board', 'ub_user_id_from', "$dir/UserBoard/sql/patches/actor/drop-ub_user_id_from.sql" );
			$updater->dropExtensionField( 'user_board', 'ub_user_name_from', "$dir/UserBoard/sql/patches/actor/drop-ub_user_name_from.sql" );
			$updater->dropExtensionIndex( 'user_board', 'ub_user_id', "$dir/UserBoard/sql/patches/actor/drop-ub_user_id_index.sql" );
			$updater->dropExtensionIndex( 'user_board', 'ub_user_id', "$dir/UserBoard/sql/patches/actor/drop-ub_user_id_from_index.sql" );
		}

		# UserProfile -- two affected tables, user_profile and user_fields_privacy
		if ( !$db->fieldExists( 'user_profile', 'up_actor', __METHOD__ ) ) {
			// 1) add new actor column
			$updater->addExtensionField( 'user_profile', 'up_actor', "$dir/UserProfile/sql/patches/actor/add-up_actor$dbExt.sql" );
			// 2) populate the new column with data and make some other magic happen, too,
			// like the PRIMARY KEY switchover
			$updater->addExtensionUpdate( [
				'runMaintenance',
				'MigrateOldUserProfileUserColumnToActor',
				"$dir/UserProfile/maintenance/migrateOldUserProfileUserColumnToActor.php"
			] );
			// 3) drop the old user ID column
			$updater->dropExtensionField( 'user_profile', 'up_user_id', "$dir/UserProfile/sql/patches/actor/drop-up_user_id.sql" );
		}

		// This was a bad idea and I should feel bad. Luckily it existed only for
		// like less than half a year in 2020.
		if ( $db->fieldExists( 'user_profile', 'up_id', __METHOD__ ) ) {
			$updater->dropExtensionField( 'user_profile', 'up_id', "$dir/UserProfile/sql/patches/patch-drop-column-up_id.sql" );
		}

		if ( !$db->fieldExists( 'user_fields_privacy', 'ufp_actor', __METHOD__ ) ) {
			// 1) add new actor column
			$updater->addExtensionField( 'user_fields_privacy', 'ufp_actor', "$dir/UserProfile/sql/patches/actor/add-ufp_actor$dbExt.sql" );
			// 2) populate the new column with data
			$updater->addExtensionUpdate( [
				'runMaintenance',
				'MigrateOldUserFieldPrivacyUserColumnToActor',
				"$dir/UserProfile/maintenance/migrateOldUserFieldPrivacyUserColumnToActor.php"
			] );
			// 3) drop old column
			$updater->dropExtensionField( 'user_profile', 'ufp_user_id', "$dir/UserProfile/sql/patches/actor/drop-ufp_user_id.sql" );
		}

	}

}
