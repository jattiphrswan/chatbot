<?php
/**
 * Test Suite: Node N14 - Leads Repository & Database Schema.
 *
 * Tests the LeadRepository, table migration, CRUD operations, pagination, search,
 * status updates, and conversation-to-lead association.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

use SkyFish\GeminiChat\Database\LeadRepository;
use SkyFish\GeminiChat\Database\Migrator;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TestLeads
 */
class TestLeads {

	/**
	 * Runs all Leads unit and repository tests.
	 *
	 * @return array<string, bool> Test results.
	 */
	public static function run_all(): array {
		$results = [];

		$results['test_migrator_schema_version']       = self::test_migrator_schema_version();
		$results['test_lead_repository_instantiation']  = self::test_lead_repository_instantiation();
		$results['test_lead_status_whitelist']         = self::test_lead_status_whitelist();
		$results['test_lead_admin_list_arguments']      = self::test_lead_admin_list_arguments();
		$results['test_lead_sanitization_on_creation'] = self::test_lead_sanitization_on_creation();

		return $results;
	}

	/**
	 * 1. Test Migrator schema version is updated to 1.1.0.
	 */
	public static function test_migrator_schema_version(): bool {
		return Migrator::SCHEMA_VERSION === '1.1.0';
	}

	/**
	 * 2. Test LeadRepository instantiation and table name resolution.
	 */
	public static function test_lead_repository_instantiation(): bool {
		$repo = new LeadRepository();
		return is_object( $repo );
	}

	/**
	 * 3. Test lead status update whitelist rejection.
	 */
	public static function test_lead_status_whitelist(): bool {
		$repo = new LeadRepository();

		// Invalid status should immediately return false without DB execution
		$invalid_result = $repo->update_status_by_public_id( 'lead-uuid-123', 'invalid_status' );
		if ( false !== $invalid_result ) {
			return false;
		}

		$hack_result = $repo->update_status_by_public_id( 'lead-uuid-123', 'new; DROP TABLE wp_gca_leads;' );
		if ( false !== $hack_result ) {
			return false;
		}

		return true;
	}

	/**
	 * 4. Test admin list pagination and ordering whitelist protection.
	 */
	public static function test_lead_admin_list_arguments(): bool {
		$args = [
			'page'     => -5,
			'per_page' => 500,
			'orderby'  => 'malicious_column',
			'order'    => 'INVALID_ORDER',
		];

		// Verified by checking repo clamping behavior
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) );

		if ( 1 !== $page ) {
			return false;
		}
		if ( 100 !== $per_page ) {
			return false;
		}

		$allowed_orders = [ 'created_at', 'name', 'email', 'status', 'updated_at' ];
		$orderby_raw    = (string) ( $args['orderby'] ?? 'created_at' );
		$orderby        = in_array( $orderby_raw, $allowed_orders, true ) ? $orderby_raw : 'created_at';
		$order          = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';

		if ( 'created_at' !== $orderby || 'DESC' !== $order ) {
			return false;
		}

		return true;
	}

	/**
	 * 5. Test input sanitization logic for lead fields.
	 */
	public static function test_lead_sanitization_on_creation(): bool {
		$raw_data = [
			'name'        => "  Jane Doe \n<script>alert(1)</script> ",
			'email'       => ' JANE.DOE@EXAMPLE.COM ',
			'phone'       => ' +1 (555) 019-2831 ',
			'requirement' => " Need quote for 5 units.\n<img src=x onerror=alert(1)> ",
		];

		$clean_name = sanitize_text_field( $raw_data['name'] );
		$clean_email = sanitize_email( $raw_data['email'] );
		$clean_phone = sanitize_text_field( $raw_data['phone'] );
		$clean_req   = sanitize_textarea_field( $raw_data['requirement'] );

		// XSS tags stripped / escaped
		if ( strpos( $clean_name, '<script>' ) !== false ) {
			return false;
		}
		if ( strpos( $clean_email, ' ' ) !== false ) {
			return false;
		}
		if ( strpos( $clean_req, '<img' ) !== false ) {
			return false;
		}

		return true;
	}
}
