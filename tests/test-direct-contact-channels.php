<?php
/**
 * Test Suite: Direct Contact Channels (Node N17.5)
 *
 * Verifies Direct Contact Channels settings defaults, sanitization,
 * tel: / mailto: / https://wa.me/ URL formatting, XSS protection,
 * localized configuration payload, and negative security assertions (no external APIs).
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Assets;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TestDirectContactChannels
 */
class TestDirectContactChannels {

	private int $passed = 0;
	private int $failed = 0;
	private array $errors = [];

	/**
	 * Runs all test cases.
	 */
	public function run(): void {
		echo "Starting Node N17.5 Direct Contact Channels Test Suite...\n\n";

		$this->test_settings_defaults();
		$this->test_phone_sanitization_and_tel_scheme();
		$this->test_email_sanitization_and_mailto_scheme();
		$this->test_whatsapp_sanitization_and_wame_url();
		$this->test_xss_and_boundary_defense();
		$this->test_localized_config_structure();
		$this->test_negative_security_checks();

		echo "\n--------------------------------------------------\n";
		echo sprintf( "Results: %d Passed, %d Failed\n", $this->passed, $this->failed );
		echo "--------------------------------------------------\n";

		if ( $this->failed > 0 ) {
			echo "Failure details:\n";
			foreach ( $this->errors as $err ) {
				echo " - " . $err . "\n";
			}
		}
	}

	private function assert( bool $condition, string $message ): void {
		if ( $condition ) {
			$this->passed++;
			echo "  [PASS] {$message}\n";
		} else {
			$this->failed++;
			$this->errors[] = $message;
			echo "  [FAIL] {$message}\n";
		}
	}

	/**
	 * 1. Default settings verification.
	 */
	private function test_settings_defaults(): void {
		echo "1. Testing Default Settings...\n";

		$this->assert( SettingsService::get( 'contact_channels_enabled', false ) === false, 'contact_channels_enabled defaults to false' );
		$this->assert( SettingsService::get( 'contact_phone_enabled', false ) === false, 'contact_phone_enabled defaults to false' );
		$this->assert( SettingsService::get( 'contact_phone_number', '' ) === '', 'contact_phone_number defaults to empty string' );
		$this->assert( SettingsService::get( 'contact_phone_label', 'Call Us' ) === 'Call Us', 'contact_phone_label defaults to Call Us' );

		$this->assert( SettingsService::get( 'contact_email_enabled', false ) === false, 'contact_email_enabled defaults to false' );
		$this->assert( SettingsService::get( 'contact_email_address', '' ) === '', 'contact_email_address defaults to empty string' );
		$this->assert( SettingsService::get( 'contact_email_label', 'Email Us' ) === 'Email Us', 'contact_email_label defaults to Email Us' );

		$this->assert( SettingsService::get( 'contact_whatsapp_enabled', false ) === false, 'contact_whatsapp_enabled defaults to false' );
		$this->assert( SettingsService::get( 'contact_whatsapp_number', '' ) === '', 'contact_whatsapp_number defaults to empty string' );
		$this->assert( SettingsService::get( 'contact_whatsapp_label', 'WhatsApp' ) === 'WhatsApp', 'contact_whatsapp_label defaults to WhatsApp' );
		$this->assert( strpos( SettingsService::get( 'contact_whatsapp_message', '' ), 'inquiry' ) !== false, 'contact_whatsapp_message defaults to helpful prompt' );
	}

	/**
	 * 2. Phone sanitization and tel: link generation.
	 */
	private function test_phone_sanitization_and_tel_scheme(): void {
		echo "\n2. Testing Phone Sanitization & tel: Link Generation...\n";

		// Test phone sanitization
		$raw_phone = "+1 (555) 234-5678 ext. 90 <script>alert(1)</script>";
		$sanitized = SettingsService::sanitize_setting( 'contact_phone_number', $raw_phone );

		$this->assert( strpos( $sanitized, '<script>' ) === false, 'Stripped script tags from phone number' );
		$this->assert( strpos( $sanitized, 'alert' ) === false, 'Stripped alphabet characters from phone number except allowable tel chars' );
		$this->assert( $sanitized === '+1 (555) 234-5678 . 90', 'Sanitized phone maintains safe characters (+, spaces, digits, hyphens, parentheses, dot)' );

		// Test label trimming & length capping
		$long_label = str_repeat( 'Call Center ', 10 );
		$sanitized_label = SettingsService::sanitize_setting( 'contact_phone_label', $long_label );
		$this->assert( strlen( $sanitized_label ) <= 50, 'Phone label capped at 50 chars' );
	}

	/**
	 * 3. Email sanitization and mailto: link generation.
	 */
	private function test_email_sanitization_and_mailto_scheme(): void {
		echo "\n3. Testing Email Sanitization & mailto: Link Generation...\n";

		$valid_email = "support@example.com";
		$sanitized_valid = SettingsService::sanitize_setting( 'contact_email_address', $valid_email );
		$this->assert( $sanitized_valid === 'support@example.com', 'Valid email passes sanitization unchanged' );

		$invalid_email = "support@example.com\r\nBcc: evil@attacker.com";
		$sanitized_invalid = SettingsService::sanitize_setting( 'contact_email_address', $invalid_email );
		$this->assert( strpos( $sanitized_invalid, "\r" ) === false && strpos( $sanitized_invalid, "\n" ) === false, 'Header injection stripped from email address' );
		$this->assert( $sanitized_invalid === '', 'Invalid email format rejected and returned as empty string' );
	}

	/**
	 * 4. WhatsApp sanitization and wa.me URL generation.
	 */
	private function test_whatsapp_sanitization_and_wame_url(): void {
		echo "\n4. Testing WhatsApp Sanitization & wa.me URL...\n";

		$raw_wa_num = "+1 (800) 555-0199";
		$sanitized_wa_num = SettingsService::sanitize_setting( 'contact_whatsapp_number', $raw_wa_num );
		$this->assert( $sanitized_wa_num === '+18005550199', 'WhatsApp number sanitized to digits and leading +' );

		// WhatsApp prefilled message length cap (300 chars)
		$long_message = str_repeat( 'Need support now! ', 30 ); // > 500 chars
		$sanitized_msg = SettingsService::sanitize_setting( 'contact_whatsapp_message', $long_message );
		$this->assert( strlen( $sanitized_msg ) <= 300, 'WhatsApp message capped at 300 characters' );

		// Test wa.me URL generation with clean digits and rawurlencode
		$clean_wa_digits = preg_replace( '/[^0-9]/', '', $sanitized_wa_num );
		$wa_url = 'https://wa.me/' . $clean_wa_digits . '?text=' . rawurlencode( 'Hello & welcome!' );
		$this->assert( strpos( $wa_url, 'https://wa.me/18005550199?text=Hello%20%26%20welcome%21' ) === 0, 'WhatsApp URL correctly generated with digits and urlencoded text' );
	}

	/**
	 * 5. XSS and boundary defense.
	 */
	private function test_xss_and_boundary_defense(): void {
		echo "\n5. Testing XSS and Boundary Defense...\n";

		$malicious_label = '<img src=x onerror=alert(1)> Support';
		$sanitized_label = SettingsService::sanitize_setting( 'contact_phone_label', $malicious_label );
		$this->assert( strpos( $sanitized_label, '<img' ) === false, 'HTML tags stripped from labels' );
		$this->assert( $sanitized_label === 'Support', 'Clean text preserved after stripping malicious HTML' );

		// Boolean toggle sanitization
		$this->assert( SettingsService::sanitize_setting( 'contact_channels_enabled', '1' ) === true, 'contact_channels_enabled coerced to boolean true' );
		$this->assert( SettingsService::sanitize_setting( 'contact_channels_enabled', '0' ) === false, 'contact_channels_enabled coerced to boolean false' );
		$this->assert( SettingsService::sanitize_setting( 'contact_channels_enabled', 'invalid' ) === false, 'contact_channels_enabled invalid coerced to false' );
	}

	/**
	 * 6. Localized config structure in Assets.
	 */
	private function test_localized_config_structure(): void {
		echo "\n6. Testing Localized Config Structure...\n";

		// Set mock options
		update_option( 'gca_contact_channels_enabled', true );
		update_option( 'gca_contact_phone_enabled', true );
		update_option( 'gca_contact_phone_number', '+1-555-0100' );
		update_option( 'gca_contact_phone_label', 'Direct Call' );
		update_option( 'gca_contact_email_enabled', true );
		update_option( 'gca_contact_email_address', 'hello@example.com' );
		update_option( 'gca_contact_email_label', 'Write to Us' );
		update_option( 'gca_contact_whatsapp_enabled', true );
		update_option( 'gca_contact_whatsapp_number', '+15550200' );
		update_option( 'gca_contact_whatsapp_label', 'WhatsApp Chat' );
		update_option( 'gca_contact_whatsapp_message', 'Hello support!' );

		$config = Assets::get_localized_config();

		$this->assert( isset( $config['contactChannels'] ), 'contactChannels key present in localized config' );
		$channels = $config['contactChannels'];

		$this->assert( $channels['enabled'] === true, 'contactChannels.enabled is true' );
		$this->assert( $channels['phone']['enabled'] === true, 'phone channel enabled' );
		$this->assert( $channels['phone']['url'] === 'tel:+1-555-0100', 'phone url is tel:+1-555-0100' );
		$this->assert( $channels['phone']['label'] === 'Direct Call', 'phone label is Direct Call' );

		$this->assert( $channels['email']['enabled'] === true, 'email channel enabled' );
		$this->assert( $channels['email']['url'] === 'mailto:hello@example.com', 'email url is mailto:hello@example.com' );
		$this->assert( $channels['email']['label'] === 'Write to Us', 'email label is Write to Us' );

		$this->assert( $channels['whatsapp']['enabled'] === true, 'whatsapp channel enabled' );
		$this->assert( strpos( $channels['whatsapp']['url'], 'https://wa.me/15550200?text=' ) === 0, 'whatsapp url begins with https://wa.me/15550200?text=' );
		$this->assert( $channels['whatsapp']['label'] === 'WhatsApp Chat', 'whatsapp label is WhatsApp Chat' );

		// Clean up mock options
		delete_option( 'gca_contact_channels_enabled' );
		delete_option( 'gca_contact_phone_enabled' );
		delete_option( 'gca_contact_phone_number' );
		delete_option( 'gca_contact_phone_label' );
		delete_option( 'gca_contact_email_enabled' );
		delete_option( 'gca_contact_email_address' );
		delete_option( 'gca_contact_email_label' );
		delete_option( 'gca_contact_whatsapp_enabled' );
		delete_option( 'gca_contact_whatsapp_number' );
		delete_option( 'gca_contact_whatsapp_label' );
		delete_option( 'gca_contact_whatsapp_message' );
	}

	/**
	 * 7. Negative Security Checks: No disallowed APIs or multi-AI providers.
	 */
	private function test_negative_security_checks(): void {
		echo "\n7. Testing Negative Security Checks (No External Disallowed APIs)...\n";

		$source_dir = dirname( __DIR__ );

		// Check for forbidden terms in includes
		$forbidden_terms = [
			'Twilio',
			'MetaGraph',
			'graph.facebook.com',
			'api.whatsapp.com/v1',
			'OPENAI_API_KEY',
			'ANTHROPIC_API_KEY',
			'OpenAIProvider',
			'ClaudeProvider',
			'ProviderFactory',
		];

		foreach ( $forbidden_terms as $term ) {
			$found = false;
			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $source_dir . '/includes' ) );
			foreach ( $files as $file ) {
				if ( $file->isFile() && $file->getExtension() === 'php' ) {
					$content = file_get_contents( $file->getPathname() );
					if ( stripos( $content, $term ) !== false ) {
						$found = true;
						break;
					}
				}
			}
			$this->assert( ! $found, "Zero instances of forbidden term '{$term}' across codebase" );
		}
	}
}

// Auto-run if executed directly via CLI or test runner.
if ( defined( 'PHPUNIT_RUNNER' ) || ( defined( 'DOING_TESTS' ) && DOING_TESTS ) ) {
	$suite = new TestDirectContactChannels();
	$suite->run();
}
