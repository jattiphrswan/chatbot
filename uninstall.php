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
	$table_conversations     = $wpdb->prefix . 'gca_conversations';
	$table_messages          = $wpdb->prefix . 'gca_messages';
	$table_leads             = $wpdb->prefix . 'gca_leads';
	$table_faqs              = $wpdb->prefix . 'gca_faqs';
	$table_knowledge_sources = $wpdb->prefix . 'gca_knowledge_sources';
	$table_knowledge_chunks  = $wpdb->prefix . 'gca_knowledge_chunks';
	$table_handoffs          = $wpdb->prefix . 'gca_handoffs';
	$table_logs              = $wpdb->prefix . 'gca_logs';

	$wpdb->query( "DROP TABLE IF EXISTS `{$table_handoffs}`" );
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_knowledge_chunks}`" );
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_knowledge_sources}`" );
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_faqs}`" );
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_leads}`" );
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_messages}`" );
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_conversations}`" );
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_logs}`" );

	delete_option( 'gca_settings' );
	delete_option( 'gca_db_version' );
	delete_option( 'gca_ai_profiles' );
	delete_option( 'gca_provider_credentials' );
}
