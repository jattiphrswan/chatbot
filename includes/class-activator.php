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

			// AI & Providers (N18).
			'default_provider'      => 'gemini',
			'model'                 => 'gemini-3.5-flash-lite',
			'provider_gemini_enabled'       => true,
			'provider_gemini_fallback_enabled' => false,
			'provider_gemini_fallback_model' => 'gemini-3.8-flash',
			'provider_gemini_model'         => 'gemini-3.5-flash-lite',
			'provider_gemini_thinking_level' => 'low',
			'provider_gemini_max_tokens'     => 1000,
			'provider_openai_enabled'       => false,
			'provider_openai_model'         => 'gpt-4o-mini',
			'provider_claude_enabled'       => false,
			'provider_claude_model'         => 'claude-3-5-haiku-20241022',
			'allow_public_provider_selection' => false,
			'allow_public_model_selection'    => false,
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

			// FAQ (N16).
			'faq_enabled'                => false,
			'faq_show_home'              => false,
			'faq_home_limit'             => 6,

			// Website Knowledge / RAG (N16).
			'knowledge_enabled'          => false,
			'knowledge_pages_enabled'    => true,
			'knowledge_posts_enabled'    => true,
			'knowledge_products_enabled' => false,
			'knowledge_faqs_enabled'     => true,
			'knowledge_max_chunks'       => 4,
			'knowledge_max_context_chars'=> 6000,

			// Human Handoff Email Notifications (N17.4).
			'handoff_email_enabled'      => false,
			'handoff_email_recipients'   => '',
			'handoff_email_subject'      => 'New Chatbot Handoff Request',

			// Direct Contact Channels (N17.5).
			'contact_channels_enabled'   => false,
			'contact_phone_enabled'      => false,
			'contact_phone_number'       => '',
			'contact_phone_label'        => 'Call Us',
			'contact_email_enabled'      => false,
			'contact_email_address'      => '',
			'contact_email_label'        => 'Email Us',
			'contact_whatsapp_enabled'   => false,
			'contact_whatsapp_number'    => '',
			'contact_whatsapp_label'     => 'WhatsApp',
			'contact_whatsapp_message'   => 'Hi! I would like to speak with someone regarding my inquiry.',

			// Access.
			'guest_access'          => true,

			// Limits.
			'max_message_length'    => 2000,
			'rate_limit_min_interval' => 2,
			'rate_limit_1m'         => 10,
			'rate_limit_5m'         => 15,
			'rate_limit_1h'         => 60,

			// Appearance & Branding (N13).
			'avatar_id'               => 0,
			'primary_color'           => '#64258A',
			'header_bg_color'         => '#64258A',
			'header_text_color'       => '#FFFFFF',
			'panel_bg_color'          => '#FFFFFF',
			'text_color'              => '#222222',
			'assistant_bubble_color'  => '#F3F4F6',
			'assistant_text_color'    => '#222222',
			'user_bubble_color'       => '#64258A',
			'user_text_color'         => '#FFFFFF',
			'button_color'            => '#64258A',
			'button_text_color'       => '#FFFFFF',
			'launcher_bg_color'       => '#64258A',
			'launcher_icon_color'     => '#FFFFFF',
			'launcher_icon'           => 'chat',
			'widget_position'         => 'bottom-right',
			'panel_width'             => 390,
			'panel_height'            => 620,
			'border_radius'           => 20,
			'launcher_size'           => 56,
			'tablet_enabled'          => true,

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
