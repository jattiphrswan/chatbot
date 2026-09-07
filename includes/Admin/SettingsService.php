<?php
/**
 * Settings Service & Credential Resolver for Gemini Chat Assistant.
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
 * Class SettingsService
 *
 * Centralized service to manage plugin settings and server-side credential status.
 */
class SettingsService {

	public const OPTION_KEY = 'gca_settings';

	/**
	 * Retrieves all sanitized settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		$defaults = Activator::get_default_settings();
		$saved    = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Retrieves a specific configuration value.
	 *
	 * @param string $key     Configuration key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$settings = self::get_all();
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Retrieves the configured Gemini model slug.
	 *
	 * @return string
	 */
	public static function get_model(): string {
		$model = (string) self::get( 'model', 'gemini-3.8-flash' );
		return ! empty( $model ) ? sanitize_text_field( $model ) : 'gemini-3.8-flash';
	}

	/**
	 * Checks whether the Gemini API key is configured on the server.
	 *
	 * Checks:
	 * 1. GEMINI_API_KEY environment variable.
	 * 2. GCA_GEMINI_API_KEY constant in wp-config.php.
	 *
	 * @return bool
	 */
	public static function is_api_key_configured(): bool {
		$key = self::get_api_key();
		return ! empty( $key );
	}

	/**
	 * Retrieves the server-side Gemini API key securely.
	 *
	 * NEVER output or store this value.
	 *
	 * @return string
	 */
	public static function get_api_key(): string {
		// 1. Check environment variable (preferred).
		$env_key = getenv( 'GEMINI_API_KEY' );
		if ( ! empty( $env_key ) && is_string( $env_key ) ) {
			return trim( $env_key );
		}

		if ( ! empty( $_ENV['GEMINI_API_KEY'] ) && is_string( $_ENV['GEMINI_API_KEY'] ) ) {
			return trim( $_ENV['GEMINI_API_KEY'] );
		}

		if ( ! empty( $_SERVER['GEMINI_API_KEY'] ) && is_string( $_SERVER['GEMINI_API_KEY'] ) ) {
			return trim( $_SERVER['GEMINI_API_KEY'] );
		}

		// 2. Check wp-config constant (fallback).
		if ( defined( 'GCA_GEMINI_API_KEY' ) && is_string( GCA_GEMINI_API_KEY ) && ! empty( GCA_GEMINI_API_KEY ) ) {
			return trim( GCA_GEMINI_API_KEY );
		}

		return '';
	}

	/**
	 * Sanitizes and validates settings input before saving to database.
	 *
	 * Note: API keys are NEVER handled here.
	 *
	 * @param array<string, mixed> $input Raw submitted POST input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( array $input ): array {
		$defaults  = Activator::get_default_settings();
		$sanitized = [];

		// General.
		$sanitized['enabled']         = ! empty( $input['enabled'] );
		$sanitized['assistant_name']  = isset( $input['assistant_name'] ) ? sanitize_text_field( $input['assistant_name'] ) : $defaults['assistant_name'];
		$sanitized['greeting']        = isset( $input['greeting'] ) ? sanitize_text_field( $input['greeting'] ) : $defaults['greeting'];
		$sanitized['welcome_message'] = isset( $input['welcome_message'] ) ? sanitize_textarea_field( $input['welcome_message'] ) : $defaults['welcome_message'];
		$sanitized['placeholder']     = isset( $input['placeholder'] ) ? sanitize_text_field( $input['placeholder'] ) : $defaults['placeholder'];

		// AI.
		$sanitized['model']              = isset( $input['model'] ) ? sanitize_text_field( trim( $input['model'] ) ) : $defaults['model'];
		$sanitized['system_instruction'] = isset( $input['system_instruction'] ) ? sanitize_textarea_field( $input['system_instruction'] ) : $defaults['system_instruction'];

		if ( empty( $sanitized['model'] ) ) {
			$sanitized['model'] = 'gemini-3.8-flash';
		}

		// Widget.
		$sanitized['widget_enabled']        = ! empty( $input['widget_enabled'] );
		$sanitized['embedded_chat_enabled'] = ! empty( $input['embedded_chat_enabled'] );
		$sanitized['desktop_enabled']       = ! empty( $input['desktop_enabled'] );
		$sanitized['mobile_enabled']        = ! empty( $input['mobile_enabled'] );

		// Pre-chat.
		$sanitized['prechat_enabled']     = ! empty( $input['prechat_enabled'] );
		$sanitized['collect_name']        = ! empty( $input['collect_name'] );
		$sanitized['require_name']        = ! empty( $input['require_name'] );
		$sanitized['collect_email']       = ! empty( $input['collect_email'] );
		$sanitized['require_email']       = ! empty( $input['require_email'] );
		$sanitized['collect_phone']       = ! empty( $input['collect_phone'] );
		$sanitized['require_phone']       = ! empty( $input['require_phone'] );
		$sanitized['collect_requirement'] = ! empty( $input['collect_requirement'] );
		$sanitized['require_requirement'] = ! empty( $input['require_requirement'] );

		// FAQ.
		$sanitized['faq_enabled']   = ! empty( $input['faq_enabled'] );
		$sanitized['faq_show_home'] = ! empty( $input['faq_show_home'] );

		// Access.
		$sanitized['guest_access'] = ! empty( $input['guest_access'] );

		// Limits.
		$max_len = isset( $input['max_message_length'] ) ? absint( $input['max_message_length'] ) : $defaults['max_message_length'];
		$sanitized['max_message_length'] = ( $max_len >= 100 && $max_len <= 10000 ) ? $max_len : 2000;

		$rate_5m = isset( $input['rate_limit_5m'] ) ? absint( $input['rate_limit_5m'] ) : $defaults['rate_limit_5m'];
		$sanitized['rate_limit_5m'] = ( $rate_5m >= 1 && $rate_5m <= 500 ) ? $rate_5m : 15;

		$rate_1h = isset( $input['rate_limit_1h'] ) ? absint( $input['rate_limit_1h'] ) : $defaults['rate_limit_1h'];
		$sanitized['rate_limit_1h'] = ( $rate_1h >= 5 && $rate_1h <= 5000 ) ? $rate_1h : 100;

		// Privacy.
		$sanitized['store_messages'] = ! empty( $input['store_messages'] );
		$sanitized['store_leads']    = ! empty( $input['store_leads'] );

		$retention = isset( $input['retention_days'] ) ? absint( $input['retention_days'] ) : $defaults['retention_days'];
		$sanitized['retention_days'] = ( $retention >= 1 && $retention <= 365 ) ? $retention : 30;

		// Appearance & Branding (N13).
		$sanitized['avatar_id'] = isset( $input['avatar_id'] ) ? absint( $input['avatar_id'] ) : $defaults['avatar_id'];

		$color_keys = [
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
		];

		foreach ( $color_keys as $ck ) {
			if ( isset( $input[ $ck ] ) ) {
				$hex = function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( (string) $input[ $ck ] ) : null;
				if ( empty( $hex ) && preg_match( '/^#([a-fA-F0-9]{3}){1,2}$/', (string) $input[ $ck ] ) ) {
					$hex = (string) $input[ $ck ];
				}
				$sanitized[ $ck ] = ! empty( $hex ) ? strtoupper( $hex ) : $defaults[ $ck ];
			} else {
				$sanitized[ $ck ] = $defaults[ $ck ];
			}
		}

		// Position whitelist.
		$allowed_positions = [ 'bottom-right', 'bottom-left' ];
		$pos = isset( $input['widget_position'] ) ? strtolower( trim( (string) $input['widget_position'] ) ) : $defaults['widget_position'];
		$sanitized['widget_position'] = in_array( $pos, $allowed_positions, true ) ? $pos : 'bottom-right';

		// Launcher icon whitelist.
		$allowed_icons = [ 'chat', 'message', 'headset', 'sparkle' ];
		$icon = isset( $input['launcher_icon'] ) ? strtolower( trim( (string) $input['launcher_icon'] ) ) : $defaults['launcher_icon'];
		$sanitized['launcher_icon'] = in_array( $icon, $allowed_icons, true ) ? $icon : 'chat';

		// Panel Width (320 - 600px).
		$width = isset( $input['panel_width'] ) ? absint( $input['panel_width'] ) : $defaults['panel_width'];
		$sanitized['panel_width'] = max( 320, min( 600, $width ) );

		// Panel Height (450 - 850px).
		$height = isset( $input['panel_height'] ) ? absint( $input['panel_height'] ) : $defaults['panel_height'];
		$sanitized['panel_height'] = max( 450, min( 850, $height ) );

		// Border Radius (0 - 40px).
		$radius = isset( $input['border_radius'] ) ? absint( $input['border_radius'] ) : $defaults['border_radius'];
		$sanitized['border_radius'] = max( 0, min( 40, $radius ) );

		// Launcher Size (44 - 80px).
		$size = isset( $input['launcher_size'] ) ? absint( $input['launcher_size'] ) : $defaults['launcher_size'];
		$sanitized['launcher_size'] = max( 44, min( 80, $size ) );

		// Responsive visibility.
		$sanitized['tablet_enabled'] = ! empty( $input['tablet_enabled'] );

		return $sanitized;
	}
}
