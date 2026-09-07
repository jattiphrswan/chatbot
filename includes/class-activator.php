<?php
/**
 * Fired during plugin activation.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

use SkyFish\GeminiChat\Database\Migrator;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation routines.
 */
class Activator {

	/**
	 * Returns default settings array for gca_settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings(): array {
		return [
			// General.
			'enabled'               => true,
			'assistant_name'        => 'AI Assistant',
			'greeting'              => 'Welcome!',
			'welcome_message'       => 'Hi! How can I help you today?',
			'placeholder'           => 'Type your message...',

			// AI.
			'model'                 => 'gemini-3.8-flash',
			'system_instruction'    => 'You are a helpful customer support assistant for this website.',

			// Widget.
			'widget_enabled'        => true,
			'embedded_chat_enabled' => true,
			'desktop_enabled'       => true,
			'mobile_enabled'        => true,

			// Pre-chat.
			'prechat_enabled'       => false,
			'collect_name'          => false,
			'require_name'          => false,
			'collect_email'         => false,
			'require_email'         => false,
			'collect_phone'         => false,
			'require_phone'         => false,
			'collect_requirement'   => false,
			'require_requirement'   => false,

			// FAQ.
			'faq_enabled'           => false,
			'faq_show_home'         => false,

			// Access.
			'guest_access'          => true,

			// Limits.
			'max_message_length'    => 2000,
			'rate_limit_5m'         => 15,
			'rate_limit_1h'         => 100,

			// Privacy.
			'store_messages'        => true,
			'store_leads'           => true,
			'retention_days'        => 30,
		];
	}

	/**
	 * Performs database table creation, default settings initialization,
	 * and version tracking.
	 */
	public static function activate(): void {
		// Run database migrations.
		require_once GCA_PLUGIN_DIR . 'includes/Database/Migrator.php';
		Migrator::migrate();

		// Set default settings if not already present.
		if ( ! get_option( 'gca_settings' ) ) {
			add_option( 'gca_settings', self::get_default_settings() );
		}
	}
}
