<?php
/**
 * Fired during plugin activation.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation routines.
 */
class Activator {

	/**
	 * Short description of what the activate method does.
	 *
	 * Performs database table creation, default settings initialization,
	 * and version tracking.
	 */
	public static function activate(): void {
		// Set default settings if not already present.
		if ( ! get_option( 'gca_settings' ) ) {
			$default_settings = [
				'model'              => 'gemini-1.5-flash',
				'system_instruction' => 'You are a helpful customer support assistant for this website.',
				'temperature'        => 0.7,
				'top_p'              => 0.95,
				'max_tokens'         => 1024,
				'rate_limit'         => [
					'requests_per_minute' => 10,
					'daily_ip_cap'        => 100,
				],
				'ui_theme'           => [
					'primary_color'   => '#1a73e8',
					'position'        => 'bottom-right',
					'bot_title'       => 'AI Assistant',
					'welcome_message' => 'Hi! How can I help you today?',
				],
			];
			add_option( 'gca_settings', $default_settings );
		}

		if ( ! get_option( 'gca_db_version' ) ) {
			add_option( 'gca_db_version', '1.0.0' );
		}
	}
}
