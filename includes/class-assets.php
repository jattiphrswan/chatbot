<?php
/**
 * Frontend Asset Loader & Script Localizer.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Admin\AppearanceService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 *
 * Manages frontend CSS/JS registration, enqueueing, and safe runtime configuration exposure.
 */
class Assets {

	public const CSS_HANDLE = 'gca-public-chat-css';
	public const JS_HANDLE  = 'gca-public-chat-js';

	/**
	 * Initializes asset hooks.
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_floating_widget_assets' ] );
	}

	/**
	 * Registers public CSS and JavaScript.
	 */
	public function register_assets(): void {
		$version = defined( 'GCA_VERSION' ) ? GCA_VERSION : '1.0.0';

		wp_register_style(
			self::CSS_HANDLE,
			GCA_PLUGIN_URL . 'public/css/chat.css',
			[],
			$version
		);

		// Apply scoped appearance CSS variables.
		$settings   = SettingsService::get_all();
		$inline_css = AppearanceService::generate_inline_css( $settings );
		if ( ! empty( $inline_css ) ) {
			wp_add_inline_style( self::CSS_HANDLE, $inline_css );
		}

		wp_register_script(
			self::JS_HANDLE,
			GCA_PLUGIN_URL . 'public/js/chat.js',
			[],
			$version,
			[ 'in_footer' => true ]
		);

		// Expose strictly SAFE runtime configuration (ZERO API keys or secrets).
		wp_localize_script(
			self::JS_HANDLE,
			'gcaConfig',
			self::get_localized_config()
		);
	}

	/**
	 * Conditionally enqueues assets for the global floating widget.
	 */
	public function maybe_enqueue_floating_widget_assets(): void {
		if ( is_admin() ) {
			return;
		}

		$enabled        = (bool) SettingsService::get( 'enabled', true );
		$widget_enabled = (bool) SettingsService::get( 'widget_enabled', true );

		if ( $enabled && $widget_enabled ) {
			self::enqueue_frontend_assets();
		}
	}

	/**
	 * Directly enqueues frontend CSS and JS.
	 */
	public static function enqueue_frontend_assets(): void {
		wp_enqueue_style( self::CSS_HANDLE );
		wp_enqueue_script( self::JS_HANDLE );
	}

	/**
	 * Prepares sanitized, safe runtime configuration for frontend JavaScript.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_localized_config(): array {
		$settings   = SettingsService::get_all();
		$avatar_id  = absint( $settings['avatar_id'] ?? 0 );
		$avatar_url = AppearanceService::get_avatar_url( $avatar_id );

		return [
			'restUrl'          => esc_url_raw( rest_url( 'gca/v1' ) ),
			'assistantName'    => esc_html( (string) ( $settings['assistant_name'] ?? 'AI Assistant' ) ),
			'greeting'         => esc_html( (string) ( $settings['greeting'] ?? 'Welcome!' ) ),
			'welcomeMessage'   => esc_html( (string) ( $settings['welcome_message'] ?? 'Hi! How can I help you today?' ) ),
			'placeholder'      => esc_attr( (string) ( $settings['placeholder'] ?? 'Type your message...' ) ),
			'avatarUrl'        => esc_url( $avatar_url ),
			'maxMessageLength' => absint( $settings['max_message_length'] ?? 1000 ),
			'widgetEnabled'    => (bool) ( $settings['widget_enabled'] ?? true ),
			'enabled'          => (bool) ( $settings['enabled'] ?? true ),
			'desktopEnabled'   => (bool) ( $settings['desktop_enabled'] ?? true ),
			'mobileEnabled'    => (bool) ( $settings['mobile_enabled'] ?? true ),
			'prechat'          => [
				'enabled'            => (bool) ( $settings['prechat_enabled'] ?? false ),
				'collectName'        => (bool) ( $settings['collect_name'] ?? false ),
				'requireName'        => (bool) ( $settings['require_name'] ?? false ),
				'collectEmail'       => (bool) ( $settings['collect_email'] ?? false ),
				'requireEmail'       => (bool) ( $settings['require_email'] ?? false ),
				'collectPhone'       => (bool) ( $settings['collect_phone'] ?? false ),
				'requirePhone'       => (bool) ( $settings['require_phone'] ?? false ),
				'collectRequirement' => (bool) ( $settings['collect_requirement'] ?? false ),
				'requireRequirement' => (bool) ( $settings['require_requirement'] ?? false ),
			],
			'contactChannels'  => [
				'enabled'  => (bool) ( $settings['contact_channels_enabled'] ?? false ),
				'phone'    => [
					'enabled' => (bool) ( $settings['contact_phone_enabled'] ?? false ) && ! empty( $settings['contact_phone_number'] ),
					'number'  => esc_attr( (string) ( $settings['contact_phone_number'] ?? '' ) ),
					'label'   => esc_html( (string) ( $settings['contact_phone_label'] ?? __( 'Call Us', 'gemini-chat-assistant' ) ) ),
					'url'     => ! empty( $settings['contact_phone_number'] ) ? 'tel:' . preg_replace( '/[^0-9+]/', '', (string) $settings['contact_phone_number'] ) : '',
				],
				'email'    => [
					'enabled' => (bool) ( $settings['contact_email_enabled'] ?? false ) && ! empty( $settings['contact_email_address'] ),
					'address' => sanitize_email( (string) ( $settings['contact_email_address'] ?? '' ) ),
					'label'   => esc_html( (string) ( $settings['contact_email_label'] ?? __( 'Email Us', 'gemini-chat-assistant' ) ) ),
					'url'     => ! empty( $settings['contact_email_address'] ) ? 'mailto:' . rawurlencode( sanitize_email( (string) $settings['contact_email_address'] ) ) : '',
				],
				'whatsapp' => [
					'enabled' => (bool) ( $settings['contact_whatsapp_enabled'] ?? false ) && ! empty( $settings['contact_whatsapp_number'] ),
					'number'  => preg_replace( '/[^0-9]/', '', (string) ( $settings['contact_whatsapp_number'] ?? '' ) ),
					'label'   => esc_html( (string) ( $settings['contact_whatsapp_label'] ?? __( 'WhatsApp', 'gemini-chat-assistant' ) ) ),
					'message' => esc_attr( (string) ( $settings['contact_whatsapp_message'] ?? '' ) ),
					'url'     => ! empty( $settings['contact_whatsapp_number'] )
						? 'https://wa.me/' . preg_replace( '/[^0-9]/', '', (string) $settings['contact_whatsapp_number'] ) . ( ! empty( $settings['contact_whatsapp_message'] ) ? '?text=' . rawurlencode( (string) $settings['contact_whatsapp_message'] ) : '' )
						: '',
				],
			],
			'i18n'             => [
				'startConversation' => esc_html__( 'Start a Conversation', 'gemini-chat-assistant' ),
				'startDesc'         => esc_html__( 'Ask us about products, services or anything else you need help with.', 'gemini-chat-assistant' ),
				'startChat'         => esc_html__( 'Start Chat', 'gemini-chat-assistant' ),
				'prechatTitle'      => esc_html__( 'Start a Conversation', 'gemini-chat-assistant' ),
				'prechatSubtitle'   => esc_html__( 'Please share a few details so we can best assist you.', 'gemini-chat-assistant' ),
				'nameLabel'         => esc_html__( 'Name', 'gemini-chat-assistant' ),
				'namePlaceholder'   => esc_attr__( 'Your name', 'gemini-chat-assistant' ),
				'emailLabel'        => esc_html__( 'Email', 'gemini-chat-assistant' ),
				'emailPlaceholder'  => esc_attr__( 'your.email@example.com', 'gemini-chat-assistant' ),
				'phoneLabel'        => esc_html__( 'Phone', 'gemini-chat-assistant' ),
				'phonePlaceholder'  => esc_attr__( '+1 (555) 000-0000', 'gemini-chat-assistant' ),
				'reqLabel'          => esc_html__( 'What can we help you with?', 'gemini-chat-assistant' ),
				'reqPlaceholder'    => esc_attr__( 'Describe your question or requirement...', 'gemini-chat-assistant' ),
				'submitting'        => esc_html__( 'Starting chat...', 'gemini-chat-assistant' ),
				'back'              => esc_html__( 'Back', 'gemini-chat-assistant' ),
				'home'              => esc_html__( 'Home', 'gemini-chat-assistant' ),
				'chat'              => esc_html__( 'Chat', 'gemini-chat-assistant' ),
				'send'              => esc_html__( 'Send', 'gemini-chat-assistant' ),
				'newChat'           => esc_html__( 'New Chat', 'gemini-chat-assistant' ),
				'close'             => esc_html__( 'Close chat', 'gemini-chat-assistant' ),
				'openChat'          => esc_html__( 'Open chat assistant', 'gemini-chat-assistant' ),
				'typing'            => esc_html__( 'Assistant is responding...', 'gemini-chat-assistant' ),
				'errorGeneric'      => esc_html__( 'Something went wrong. Please try again.', 'gemini-chat-assistant' ),
				'errorTimeout'      => esc_html__( 'The assistant took too long to respond. Please try again.', 'gemini-chat-assistant' ),
				'errorTooLong'      => esc_html__( 'Your message exceeds the maximum allowed length.', 'gemini-chat-assistant' ),
				'errorUnavailable'  => esc_html__( 'The assistant is temporarily unavailable.', 'gemini-chat-assistant' ),
				'errorDisabled'     => esc_html__( 'The chat assistant is currently disabled.', 'gemini-chat-assistant' ),
				'errorDenied'       => esc_html__( 'Chat is currently unavailable.', 'gemini-chat-assistant' ),
				'networkError'      => esc_html__( 'Network connection failed. Please check your connection and try again.', 'gemini-chat-assistant' ),
				'offlineNotice'     => esc_html__( 'You appear to be offline. Please check your connection.', 'gemini-chat-assistant' ),
				'tryAgain'          => esc_html__( 'Try Again', 'gemini-chat-assistant' ),
				'confirmReset'      => esc_html__( 'Start a new conversation? This will clear the current chat from this screen.', 'gemini-chat-assistant' ),
				'rateLimited'       => esc_html__( 'Too many messages. Please try again in {seconds}s.', 'gemini-chat-assistant' ),
				'scrollToBottom'    => esc_html__( 'New messages', 'gemini-chat-assistant' ),
				'unreadCount'       => esc_html__( 'unread messages', 'gemini-chat-assistant' ),
			],
		];
	}
}
