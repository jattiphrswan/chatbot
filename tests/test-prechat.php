<?php
/**
 * Test Suite: Node N14 - Pre-Chat Form & Validation Service.
 *
 * Tests the field validation rules, enabled vs required field permutations,
 * international phone formatting, honeypot detection, and PII minimization.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

use SkyFish\GeminiChat\Validator;
use SkyFish\GeminiChat\LeadService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TestPrechat
 */
class TestPrechat {

	/**
	 * Runs all Pre-Chat validation and service tests.
	 *
	 * @return array<string, bool> Test results.
	 */
	public static function run_all(): array {
		$results = [];

		$results['test_name_validation_unicode']         = self::test_name_validation_unicode();
		$results['test_email_validation_matrix']          = self::test_email_validation_matrix();
		$results['test_phone_validation_international']   = self::test_phone_validation_international();
		$results['test_requirement_validation_length']   = self::test_requirement_validation_length();
		$results['test_prechat_enabled_required_matrix'] = self::test_prechat_enabled_required_matrix();
		$results['test_honeypot_spam_rejection']         = self::test_honeypot_spam_rejection();
		$results['test_disabled_fields_not_required']    = self::test_disabled_fields_not_required();

		return $results;
	}

	/**
	 * 1. Test Unicode name support (non-English names, accents, Asian characters).
	 */
	public static function test_name_validation_unicode(): bool {
		$valid_names = [
			'John Smith',
			'José María García',
			'Александр Иванов',
			'王小明',
			'Fatima Al-Zahra',
		];

		foreach ( $valid_names as $name ) {
			$res = Validator::validate_name( $name, true );
			if ( is_wp_error( $res ) || empty( $res ) ) {
				return false;
			}
		}

		// Empty required name should fail
		$empty_res = Validator::validate_name( '   ', true );
		if ( ! is_wp_error( $empty_res ) ) {
			return false;
		}

		// Empty optional name should return empty string
		$opt_res = Validator::validate_name( '   ', false );
		if ( is_wp_error( $opt_res ) || '' !== $opt_res ) {
			return false;
		}

		return true;
	}

	/**
	 * 2. Test email validation matrix (valid, invalid, required, optional).
	 */
	public static function test_email_validation_matrix(): bool {
		$valid_emails = [
			'user@example.com',
			'first.last+tag@sub.domain.co.uk',
		];

		foreach ( $valid_emails as $email ) {
			$res = Validator::validate_email( $email, true );
			if ( is_wp_error( $res ) || empty( $res ) ) {
				return false;
			}
		}

		$invalid_emails = [
			'notanemail',
			'missing@domain',
			'<script>@x.com',
			'user@.com',
		];

		foreach ( $invalid_emails as $bad_email ) {
			$res = Validator::validate_email( $bad_email, true );
			if ( ! is_wp_error( $res ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * 3. Test international phone validation matrix.
	 */
	public static function test_phone_validation_international(): bool {
		$valid_phones = [
			'+91 98765 43210',    // India
			'+971 50 123 4567',   // UAE
			'(661) 607-3885',     // USA
			'020 7946 0958',      // UK
			'+49 (0)30 1234567',  // Germany
			'090-1234-5678',      // Japan
		];

		foreach ( $valid_phones as $phone ) {
			$res = Validator::validate_phone( $phone, true );
			if ( is_wp_error( $res ) || empty( $res ) ) {
				return false;
			}
		}

		$invalid_phones = [
			'CALL-ME-NOW',
			'123', // less than 6 digits
			'<script>alert(1)</script>',
			'abc123def456',
		];

		foreach ( $invalid_phones as $bad_phone ) {
			$res = Validator::validate_phone( $bad_phone, true );
			if ( ! is_wp_error( $res ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * 4. Test requirement / inquiry validation and character limits.
	 */
	public static function test_requirement_validation_length(): bool {
		$valid_req = 'I would like to inquire about bulk enterprise licensing and support options.';
		$res = Validator::validate_requirement( $valid_req, true );
		if ( is_wp_error( $res ) || $res !== $valid_req ) {
			return false;
		}

		// Exceeding 2000 characters
		$too_long = str_repeat( 'a', 2001 );
		$bad_res  = Validator::validate_requirement( $too_long, false );
		if ( ! is_wp_error( $bad_res ) ) {
			return false;
		}

		return true;
	}

	/**
	 * 5. Test complete validate_prechat matrix with field combinations.
	 */
	public static function test_prechat_enabled_required_matrix(): bool {
		$settings = [
			'collect_name'        => true,
			'require_name'        => true,
			'collect_email'       => true,
			'require_email'       => true,
			'collect_phone'       => true,
			'require_phone'       => false, // optional
			'collect_requirement' => true,
			'require_requirement' => false, // optional
		];

		// Missing required email
		$payload_missing_email = [
			'name'  => 'Alice Wonder',
			'email' => '',
			'phone' => '+1 555 123 4567',
		];

		$res = Validator::validate_prechat( $payload_missing_email, $settings );
		if ( ! is_wp_error( $res ) ) {
			return false;
		}

		$err_data = $res->get_error_data();
		if ( ! is_array( $err_data ) || ! isset( $err_data['fields']['email'] ) ) {
			return false;
		}

		// Valid payload with optional fields omitted
		$payload_valid = [
			'name'  => 'Alice Wonder',
			'email' => 'alice@example.com',
		];

		$valid_res = Validator::validate_prechat( $payload_valid, $settings );
		if ( is_wp_error( $valid_res ) ) {
			return false;
		}

		if ( $valid_res['name'] !== 'Alice Wonder' || $valid_res['email'] !== 'alice@example.com' || $valid_res['phone'] !== null ) {
			return false;
		}

		return true;
	}

	/**
	 * 6. Test honeypot spam detection.
	 */
	public static function test_honeypot_spam_rejection(): bool {
		$service = new LeadService();
		$payload = [
			'name'        => 'Spam Bot',
			'email'       => 'spambot@example.com',
			'website_url' => 'http://spam-link.com',
		];

		$res = $service->handle_prechat_submission( $payload, 'gca_sess_test12345678' );
		if ( ! is_wp_error( $res ) || $res->get_error_code() !== 'SPAM_DETECTED' ) {
			return false;
		}

		return true;
	}

	/**
	 * 7. Test that disabled fields are not validated or required.
	 */
	public static function test_disabled_fields_not_required(): bool {
		$settings = [
			'collect_name'        => true,
			'require_name'        => true,
			'collect_email'       => false,
			'require_email'       => true, // must be ignored because collect_email = false
			'collect_phone'       => false,
			'require_phone'       => false,
			'collect_requirement' => false,
			'require_requirement' => false,
		];

		$payload = [
			'name'  => 'Bob Builder',
			'email' => '', // disabled
			'phone' => 'invalid-phone-that-is-ignored',
		];

		$res = Validator::validate_prechat( $payload, $settings );
		if ( is_wp_error( $res ) ) {
			return false;
		}

		if ( $res['name'] !== 'Bob Builder' || $res['email'] !== null || $res['phone'] !== null ) {
			return false;
		}

		return true;
	}
}
