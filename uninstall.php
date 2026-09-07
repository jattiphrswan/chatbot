<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package SkyFish\GeminiChat
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Cleanup tables if user configured complete data wipe on uninstall.
$options = get_option( 'gca_settings' );
if ( ! empty( $options['wipe_data_on_uninstall'] ) ) {
	$table_conversations = $wpdb->prefix . 'gca_conversations';
	$table_messages      = $wpdb->prefix . 'gca_messages';
	$table_logs          = $wpdb->prefix . 'gca_logs';

	$wpdb->query( "DROP TABLE IF EXISTS `{$table_messages}`" );
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_conversations}`" );
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_logs}`" );

	delete_option( 'gca_settings' );
	delete_option( 'gca_db_version' );
}
