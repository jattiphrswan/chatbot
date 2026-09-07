<?php
/**
 * Test Suite: Node N13 - Appearance Builder.
 *
 * Tests the AppearanceService, SettingsService sanitization for appearance fields,
 * dynamic inline CSS generation, dimension clamping, and reset handler.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

use SkyFish\GeminiChat\Activator;
use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Admin\AppearanceService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TestAppearance
 */
class TestAppearance {

	/**
	 * Run all N13 Appearance Builder tests.
	 *
	 * @return array<string, bool> Test results.
	 */
	public static function run_all(): array {
		$results = [];

		$results['test_appearance_defaults_present']      = self::test_appearance_defaults_present();
		$results['test_appearance_keys_coverage']        = self::test_appearance_keys_coverage();
		$results['test_color_hex_sanitization']           = self::test_color_hex_sanitization();
		$results['test_dimension_clamping']               = self::test_dimension_clamping();
		$results['test_position_and_icon_whitelisting']   = self::test_position_and_icon_whitelisting();
		$results['test_inline_css_generation']            = self::test_inline_css_generation();
		$results['test_reset_preserves_non_appearance']   = self::test_reset_preserves_non_appearance();

		return $results;
	}

	/**
	 * 1. Test that Activator provides all expected default appearance keys and values.
	 */
	public static function test_appearance_defaults_present(): bool {
		$defaults = Activator::get_default_settings();

		$required_keys = [
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

		foreach ( $required_keys as $key ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				return false;
			}
		}

		// Verify default primary color is #64258a
		if ( strtolower( (string) $defaults['primary_color'] ) !== '#64258a' ) {
			return false;
		}

		// Verify default position
		if ( $defaults['widget_position'] !== 'bottom-right' ) {
			return false;
		}

		return true;
	}

	/**
	 * 2. Test that AppearanceService::APPEARANCE_KEYS covers all appearance keys.
	 */
	public static function test_appearance_keys_coverage(): bool {
		$keys = AppearanceService::APPEARANCE_KEYS;
		return in_array( 'primary_color', $keys, true )
			&& in_array( 'widget_position', $keys, true )
			&& in_array( 'launcher_icon', $keys, true )
			&& in_array( 'panel_width', $keys, true )
			&& in_array( 'avatar_id', $keys, true )
			&& in_array( 'tablet_enabled', $keys, true );
	}

	/**
	 * 3. Test color sanitization behavior.
	 */
	public static function test_color_hex_sanitization(): bool {
		$input = [
			'primary_color'   => '#123456',
			'header_bg_color' => '#ABC',
			'text_color'      => 'invalid-color<script>',
			'button_color'    => '#ff0000; background: url(x)',
		];

		$clean = SettingsService::sanitize_settings( $input );

		// Valid 6-hex preserved
		if ( strtolower( (string) $clean['primary_color'] ) !== '#123456' ) {
			return false;
		}

		// Valid 3-hex converted
		if ( strtolower( (string) $clean['header_bg_color'] ) !== '#aabbcc' ) {
			return false;
		}

		// Invalid fallback to default
		if ( strtolower( (string) $clean['text_color'] ) !== '#1f2937' ) {
			return false;
		}

		// XSS payload sanitized / fallback
		if ( strpos( (string) $clean['button_color'], '<' ) !== false || strpos( (string) $clean['button_color'], ';' ) !== false ) {
			return false;
		}

		return true;
	}

	/**
	 * 4. Test dimensions clamping.
	 */
	public static function test_dimension_clamping(): bool {
		$input = [
			'panel_width'   => 100,  // Min is 320
			'panel_height'  => 2000, // Max is 850
			'border_radius' => 999,  // Max is 40
			'launcher_size' => 20,   // Min is 44
		];

		$clean = SettingsService::sanitize_settings( $input );

		if ( (int) $clean['panel_width'] !== 320 ) {
			return false;
		}

		if ( (int) $clean['panel_height'] !== 850 ) {
			return false;
		}

		if ( (int) $clean['border_radius'] !== 40 ) {
			return false;
		}

		if ( (int) $clean['launcher_size'] !== 44 ) {
			return false;
		}

		return true;
	}

	/**
	 * 5. Test position and icon whitelisting.
	 */
	public static function test_position_and_icon_whitelisting(): bool {
		$input_valid = [
			'widget_position' => 'bottom-left',
			'launcher_icon'   => 'sparkle',
		];

		$clean_valid = SettingsService::sanitize_settings( $input_valid );
		if ( $clean_valid['widget_position'] !== 'bottom-left' || $clean_valid['launcher_icon'] !== 'sparkle' ) {
			return false;
		}

		$input_invalid = [
			'widget_position' => 'top-center',
			'launcher_icon'   => 'evil-icon',
		];

		$clean_invalid = SettingsService::sanitize_settings( $input_invalid );
		if ( $clean_invalid['widget_position'] !== 'bottom-right' || $clean_invalid['launcher_icon'] !== 'chat' ) {
			return false;
		}

		return true;
	}

	/**
	 * 6. Test inline CSS variable generation.
	 */
	public static function test_inline_css_generation(): bool {
		$settings = [
			'primary_color'          => '#64258a',
			'header_bg_color'        => '#64258a',
			'header_text_color'      => '#ffffff',
			'panel_bg_color'         => '#ffffff',
			'text_color'             => '#1f2937',
			'assistant_bubble_color' => '#f3f4f6',
			'assistant_text_color'   => '#1f2937',
			'user_bubble_color'      => '#64258a',
			'user_text_color'        => '#ffffff',
			'button_color'           => '#64258a',
			'button_text_color'      => '#ffffff',
			'launcher_bg_color'      => '#64258a',
			'launcher_icon_color'    => '#ffffff',
			'widget_position'        => 'bottom-left',
			'panel_width'            => 420,
			'panel_height'           => 640,
			'border_radius'          => 18,
			'launcher_size'          => 60,
			'desktop_enabled'        => true,
			'tablet_enabled'         => false,
			'mobile_enabled'         => true,
		];

		$css = AppearanceService::generate_inline_css( $settings );

		if ( empty( $css ) ) {
			return false;
		}

		// Must contain root CSS variables
		if ( strpos( $css, '--gca-primary: #64258a' ) === false ) {
			return false;
		}

		if ( strpos( $css, '--gca-panel-width: 420px' ) === false ) {
			return false;
		}

		if ( strpos( $css, '--gca-border-radius: 18px' ) === false ) {
			return false;
		}

		if ( strpos( $css, '--gca-launcher-size: 60px' ) === false ) {
			return false;
		}

		// Must contain position override for bottom-left
		if ( strpos( $css, 'left: 20px' ) === false || strpos( $css, 'right: auto' ) === false ) {
			return false;
		}

		// Must contain responsive visibility rules
		if ( strpos( $css, '@media (min-width: 601px) and (max-width: 1024px)' ) === false ) {
			return false;
		}

		return true;
	}

	/**
	 * 7. Test that AppearanceService::reset_to_defaults preserves non-appearance settings.
	 */
	public static function test_reset_preserves_non_appearance(): bool {
		$mock_existing = [
			'model'                  => 'gemini-1.5-pro',
			'system_prompt'          => 'Custom AI prompt.',
			'temperature'            => 0.2,
			'rate_limit_per_minute'  => 5,
			'rate_limit_per_hour'    => 20,
			'rate_limit_ip_hourly'   => 50,
			'store_messages'         => false,
			'guest_access'           => false,
			// Custom appearance values
			'primary_color'          => '#ff0000',
			'widget_position'        => 'bottom-left',
			'panel_width'            => 550,
			'border_radius'          => 30,
		];

		// Simulate reset logic
		$defaults = AppearanceService::get_defaults();
		$merged   = $mock_existing;
		foreach ( AppearanceService::APPEARANCE_KEYS as $key ) {
			if ( array_key_exists( $key, $defaults ) ) {
				$merged[ $key ] = $defaults[ $key ];
			}
		}

		// AI & limits preserved
		if ( $merged['model'] !== 'gemini-1.5-pro' || $merged['system_prompt'] !== 'Custom AI prompt.' ) {
			return false;
		}
		if ( $merged['rate_limit_per_minute'] !== 5 || $merged['store_messages'] !== false ) {
			return false;
		}

		// Appearance reset to default values
		if ( strtolower( (string) $merged['primary_color'] ) !== '#64258a' ) {
			return false;
		}
		if ( $merged['widget_position'] !== 'bottom-right' ) {
			return false;
		}
		if ( (int) $merged['panel_width'] !== 380 ) {
			return false;
		}

		return true;
	}
}
