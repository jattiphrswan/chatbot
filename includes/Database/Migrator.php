<?php
/**
 * Database Migration Engine for Gemini Chat Assistant.
 *
 * @package SkyFish\GeminiChat\Database
 */

namespace SkyFish\GeminiChat\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migrator
 *
 * Handles creation and schema versioning for custom plugin tables using dbDelta().
 */
class Migrator {

	/**
	 * Target database schema version.
	 */
	public const SCHEMA_VERSION = '1.3.0';

	/**
	 * Option key storing installed schema version.
	 */
	public const VERSION_OPTION = 'gca_db_version';

	/**
	 * Executes schema migrations if schema is new or outdated.
	 *
	 * @return bool True if migration executed successfully.
	 */
	public static function migrate(): bool {
		global $wpdb;

		$installed_version = get_option( self::VERSION_OPTION, '0.0.0' );

		// Only run migration if installed version is lower than target schema version.
		if ( version_compare( $installed_version, self::SCHEMA_VERSION, '>=' ) ) {
			return true;
		}

		$charset_collate   = $wpdb->get_charset_collate();
		$conversations     = $wpdb->prefix . 'gca_conversations';
		$messages          = $wpdb->prefix . 'gca_messages';
		$leads             = $wpdb->prefix . 'gca_leads';
		$faqs              = $wpdb->prefix . 'gca_faqs';
		$knowledge_sources = $wpdb->prefix . 'gca_knowledge_sources';
		$knowledge_chunks  = $wpdb->prefix . 'gca_knowledge_chunks';
		$handoffs          = $wpdb->prefix . 'gca_handoffs';

		// Schema definitions formatted strictly according to dbDelta specifications.
		$sql = "CREATE TABLE {$conversations} (
id bigint(20) unsigned not null auto_increment,
public_id varchar(64) not null,
user_id bigint(20) unsigned default 0,
session_hash varchar(64) not null,
title varchar(255) default null,
status varchar(20) default 'active' not null,
interaction_id varchar(64) default null,
message_count int(10) unsigned default 0,
created_at datetime default current_timestamp not null,
updated_at datetime default current_timestamp not null,
last_message_at datetime default null,
PRIMARY KEY  (id),
UNIQUE KEY uk_public_id (public_id),
KEY idx_user_id (user_id),
KEY idx_session_hash (session_hash),
KEY idx_status (status),
KEY idx_updated_at (updated_at)
) {$charset_collate};
CREATE TABLE {$messages} (
id bigint(20) unsigned not null auto_increment,
conversation_id bigint(20) unsigned not null,
role varchar(20) not null,
content longtext not null,
model varchar(64) default null,
input_tokens int(10) unsigned default 0,
output_tokens int(10) unsigned default 0,
latency_ms int(10) unsigned default 0,
created_at datetime default current_timestamp not null,
PRIMARY KEY  (id),
KEY idx_conversation_id (conversation_id),
KEY idx_created_at (created_at)
) {$charset_collate};
CREATE TABLE {$leads} (
id bigint(20) unsigned not null auto_increment,
public_id varchar(64) not null,
conversation_id bigint(20) unsigned default null,
user_id bigint(20) unsigned default 0,
name varchar(191) default null,
email varchar(254) default null,
phone varchar(100) default null,
requirement text default null,
status varchar(30) default 'new' not null,
created_at datetime default current_timestamp not null,
updated_at datetime default current_timestamp not null,
PRIMARY KEY  (id),
UNIQUE KEY uk_public_id (public_id),
KEY idx_conversation_id (conversation_id),
KEY idx_user_id (user_id),
KEY idx_email (email(191)),
KEY idx_status (status),
KEY idx_created_at (created_at)
) {$charset_collate};
CREATE TABLE {$faqs} (
id bigint(20) unsigned not null auto_increment,
public_id varchar(64) not null,
question text not null,
answer longtext not null,
category varchar(100) default null,
is_active tinyint(1) default 1 not null,
show_on_home tinyint(1) default 0 not null,
sort_order int(11) default 0 not null,
created_at datetime default current_timestamp not null,
updated_at datetime default current_timestamp not null,
PRIMARY KEY  (id),
UNIQUE KEY uk_public_id (public_id),
KEY idx_is_active (is_active),
KEY idx_show_on_home (show_on_home),
KEY idx_category (category(100)),
KEY idx_sort_order (sort_order)
) {$charset_collate};
CREATE TABLE {$knowledge_sources} (
id bigint(20) unsigned not null auto_increment,
public_id varchar(64) not null,
source_type varchar(30) not null,
source_object_id bigint(20) unsigned default null,
source_public_id varchar(64) default null,
title text not null,
url text default null,
content_hash char(64) not null,
status varchar(20) default 'indexed' not null,
indexed_at datetime default null,
updated_at datetime default current_timestamp not null,
PRIMARY KEY  (id),
UNIQUE KEY uk_public_id (public_id),
KEY idx_source_type (source_type),
KEY idx_source_object_id (source_object_id),
KEY idx_source_public_id (source_public_id),
KEY idx_content_hash (content_hash),
KEY idx_status (status)
) {$charset_collate};
CREATE TABLE {$knowledge_chunks} (
id bigint(20) unsigned not null auto_increment,
source_id bigint(20) unsigned not null,
chunk_index int(10) unsigned not null,
content longtext not null,
content_hash char(64) not null,
created_at datetime default current_timestamp not null,
PRIMARY KEY  (id),
KEY idx_source_id (source_id),
KEY idx_chunk_index (chunk_index)
) {$charset_collate};
CREATE TABLE {$handoffs} (
id bigint(20) unsigned not null auto_increment,
public_id varchar(64) not null,
conversation_id bigint(20) unsigned not null,
lead_id bigint(20) unsigned default null,
reason varchar(50) not null,
status varchar(20) default 'pending' not null,
created_at datetime default current_timestamp not null,
updated_at datetime default current_timestamp not null,
PRIMARY KEY  (id),
UNIQUE KEY uk_public_id (public_id),
KEY idx_conversation_id (conversation_id),
KEY idx_lead_id (lead_id),
KEY idx_reason (reason),
KEY idx_status (status),
KEY idx_created_at (created_at)
) {$charset_collate};";

		if ( ! function_exists( 'dbDelta' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		if ( function_exists( 'dbDelta' ) ) {
			dbDelta( $sql );
		}

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );

		return true;
	}

	/**
	 * Checks if migration is needed during runtime.
	 */
	public static function check_updates(): void {
		$installed_version = get_option( self::VERSION_OPTION, '0.0.0' );
		if ( version_compare( $installed_version, self::SCHEMA_VERSION, '<' ) ) {
			self::migrate();
		}
	}
}
