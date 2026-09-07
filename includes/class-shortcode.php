<?php
/**
 * Shortcode Handler for Embedded Chat.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

use SkyFish\GeminiChat\Admin\SettingsService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shortcode
 *
 * Registers and renders the [gemini_chat] shortcode for Gutenberg, Elementor, and standard WordPress content.
 */
class Shortcode {

	public const TAG = 'gemini_chat';

	/**
	 * Initializes shortcode hook.
	 */
	public function init(): void {
		add_shortcode( self::TAG, [ $this, 'render' ] );
	}

	/**
	 * Renders the [gemini_chat] embedded chatbot widget.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( $atts = [] ): string {
		$enabled          = (bool) SettingsService::get( 'enabled', true );
		$embedded_enabled = (bool) SettingsService::get( 'embedded_chat_enabled', true );

		if ( ! $enabled || ! $embedded_enabled ) {
			return '';
		}

		// Enqueue required CSS and JS for frontend rendering.
		Assets::enqueue_frontend_assets();

		$instance_id = function_exists( 'wp_unique_id' )
			? wp_unique_id( 'gca-embedded-widget-' )
			: 'gca-embedded-widget-' . uniqid();
		$mode        = 'embedded';

		ob_start();
		$template_path = GCA_PLUGIN_DIR . 'templates/chat-widget.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
		return (string) ob_get_clean();
	}
}
