<?php
/**
 * Appearance Service & CSS Variable Generator.
 *
 * @package SkyFish\GeminiChat\Admin
 */

namespace SkyFish\GeminiChat\Admin;

use SkyFish\GeminiChat\Activator;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AppearanceService
 *
 * Manages appearance configuration normalization, CSS variable generation, and avatar validation.
 */
class AppearanceService {

	/**
	 * Whitelist of appearance-specific setting keys.
	 */
	public const APPEARANCE_KEYS = [
		'assistant_name',
		'greeting',
		'welcome_message',
		'avatar_id',
		'primary_color',
		'header_bg_color',
		'header_text_color',
		'panel_bg_color',
		'text_color',
		'assistant_bubble_color',
		'assistant_text_color',
		'user_bubble_color',
		'user_text_color',
		'button_color',
		'button_text_color',
		'launcher_bg_color',
		'launcher_icon_color',
		'launcher_icon',
		'widget_position',
		'panel_width',
		'panel_height',
		'border_radius',
		'launcher_size',
		'desktop_enabled',
		'tablet_enabled',
		'mobile_enabled',
	];

	/**
	 * Returns default appearance settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		$all_defaults = Activator::get_default_settings();
		$appearance   = [];

		foreach ( self::APPEARANCE_KEYS as $k ) {
			if ( array_key_exists( $k, $all_defaults ) ) {
				$appearance[ $k ] = $all_defaults[ $k ];
			}
		}

		return $appearance;
	}

	/**
	 * Retrieves validated avatar image URL if attachment exists and is an image.
	 *
	 * @param int $attachment_id Media attachment ID.
	 * @return string Image URL or empty string.
	 */
	public static function get_avatar_url( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}

		if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) {
			return '';
		}

		if ( function_exists( 'wp_get_attachment_image_url' ) ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
			return $url ? esc_url_raw( $url ) : '';
		}

		return '';
	}

	/**
	 * Generates safe, scoped inline CSS variables and responsive rules for frontend delivery.
	 *
	 * @param array<string, mixed> $settings Active settings array.
	 * @return string Safe minified CSS snippet.
	 */
	public static function generate_inline_css( array $settings ): string {
		$defaults = self::get_defaults();
		$s        = wp_parse_args( $settings, $defaults );

		$primary          = sanitize_hex_color( (string) $s['primary_color'] ) ?: $defaults['primary_color'];
		$header_bg        = sanitize_hex_color( (string) $s['header_bg_color'] ) ?: $defaults['header_bg_color'];
		$header_text      = sanitize_hex_color( (string) $s['header_text_color'] ) ?: $defaults['header_text_color'];
		$panel_bg         = sanitize_hex_color( (string) $s['panel_bg_color'] ) ?: $defaults['panel_bg_color'];
		$text             = sanitize_hex_color( (string) $s['text_color'] ) ?: $defaults['text_color'];
		$assistant_bubble = sanitize_hex_color( (string) $s['assistant_bubble_color'] ) ?: $defaults['assistant_bubble_color'];
		$assistant_text   = sanitize_hex_color( (string) $s['assistant_text_color'] ) ?: $defaults['assistant_text_color'];
		$user_bubble      = sanitize_hex_color( (string) $s['user_bubble_color'] ) ?: $defaults['user_bubble_color'];
		$user_text        = sanitize_hex_color( (string) $s['user_text_color'] ) ?: $defaults['user_text_color'];
		$button_bg        = sanitize_hex_color( (string) $s['button_color'] ) ?: $defaults['button_color'];
		$button_text      = sanitize_hex_color( (string) $s['button_text_color'] ) ?: $defaults['button_text_color'];
		$launcher_bg      = sanitize_hex_color( (string) $s['launcher_bg_color'] ) ?: $defaults['launcher_bg_color'];
		$launcher_icon    = sanitize_hex_color( (string) $s['launcher_icon_color'] ) ?: $defaults['launcher_icon_color'];

		$panel_w = max( 320, min( 600, absint( $s['panel_width'] ?? 390 ) ) );
		$panel_h = max( 450, min( 850, absint( $s['panel_height'] ?? 620 ) ) );
		$radius  = max( 0, min( 40, absint( $s['border_radius'] ?? 20 ) ) );
		$size    = max( 44, min( 80, absint( $s['launcher_size'] ?? 56 ) ) );

		$css = ":root, .gca-widget, .gca-launcher {
			--gca-primary: {$primary};
			--gca-primary-hover: {$primary};
			--gca-header-bg: {$header_bg};
			--gca-header-text: {$header_text};
			--gca-surface: {$panel_bg};
			--gca-text-primary: {$text};
			--gca-ai-bubble-bg: {$assistant_bubble};
			--gca-ai-bubble-text: {$assistant_text};
			--gca-user-bubble-bg: {$user_bubble};
			--gca-user-bubble-text: {$user_text};
			--gca-button-bg: {$button_bg};
			--gca-button-text: {$button_text};
			--gca-launcher-bg: {$launcher_bg};
			--gca-launcher-icon: {$launcher_icon};
			--gca-panel-width: {$panel_w}px;
			--gca-panel-height: {$panel_h}px;
			--gca-radius: {$radius}px;
			--gca-launcher-size: {$size}px;
		}";

		// Dimensions and launcher size application
		$css .= ".gca-launcher { width: var(--gca-launcher-size); height: var(--gca-launcher-size); background-color: var(--gca-launcher-bg); color: var(--gca-launcher-icon); }";
		$css .= ".gca-widget { border-radius: var(--gca-radius); }";
		$css .= "@media (min-width: 601px) { .gca-widget--floating { width: var(--gca-panel-width); height: var(--gca-panel-height); } }";
		$css .= ".gca-header { background: var(--gca-header-bg); color: var(--gca-header-text); }";
		$css .= ".gca-header__title { color: var(--gca-header-text); }";
		$css .= ".gca-header .gca-btn-action { color: var(--gca-header-text); }";
		$css .= ".gca-start-card__icon, .gca-composer__send { background: var(--gca-button-bg); color: var(--gca-button-text); }";

		// Positioning
		$position = (string) ( $s['widget_position'] ?? 'bottom-right' );
		if ( 'bottom-left' === $position ) {
			$css .= ".gca-launcher { inset-inline-end: auto !important; inset-inline-start: 24px !important; }";
			$css .= ".gca-widget--floating { inset-inline-end: auto !important; inset-inline-start: 24px !important; }";
		}

		// Responsive visibility rules
		$desktop_enabled = ! empty( $s['desktop_enabled'] );
		$tablet_enabled  = ! empty( $s['tablet_enabled'] );
		$mobile_enabled  = ! empty( $s['mobile_enabled'] );

		if ( ! $desktop_enabled ) {
			$css .= "@media (min-width: 1025px) { .gca-launcher, .gca-widget--floating { display: none !important; } }";
		}

		if ( ! $tablet_enabled ) {
			$css .= "@media (min-width: 601px) and (max-width: 1024px) { .gca-launcher, .gca-widget--floating { display: none !important; } }";
		}

		if ( ! $mobile_enabled ) {
			$css .= "@media (max-width: 600px) { .gca-launcher, .gca-widget--floating { display: none !important; } }";
		}

		return str_replace( [ "\r\n", "\r", "\n", "\t" ], ' ', $css );
	}
}
