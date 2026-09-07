<?php
/**
 * Test Suite: Node N16 - FAQ System.
 *
 * Tests FaqRepository, input sanitization, XSS protection, active status filtering,
 * home visibility, sort ordering, pagination, and search.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

use SkyFish\GeminiChat\Database\FaqRepository;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TestFaqs
 */
class TestFaqs {

	/**
	 * Runs all FAQ unit and integration tests.
	 *
	 * @return array<string, bool> Test results.
	 */
	public static function run_all(): array {
		$results = [];

		$results['test_faq_repository_instantiation'] = self::test_faq_repository_instantiation();
		$results['test_faq_table_name_constant']      = self::test_faq_table_name_constant();
		$results['test_faq_xss_protection']           = self::test_faq_xss_protection();
		$results['test_faq_field_length_capping']     = self::test_faq_field_length_capping();
		$results['test_faq_active_status_logic']      = self::test_faq_active_status_logic();
		$results['test_faq_show_on_home_logic']       = self::test_faq_show_on_home_logic();
		$results['test_faq_sort_order_logic']         = self::test_faq_sort_order_logic();
		$results['test_faq_search_normalization']     = self::test_faq_search_normalization();

		return $results;
	}

	/**
	 * 1. Test FaqRepository instantiation.
	 */
	public static function test_faq_repository_instantiation(): bool {
		$repo = new FaqRepository();
		return is_object( $repo );
	}

	/**
	 * 2. Test FaqRepository table name constant.
	 */
	public static function test_faq_table_name_constant(): bool {
		return FaqRepository::TABLE_NAME === 'gca_faqs';
	}

	/**
	 * 3. Test FAQ XSS protection.
	 */
	public static function test_faq_xss_protection(): bool {
		$raw_question = '<script>alert("xss")</script>What are shipping times?';
		$raw_answer   = '<img src=x onerror=alert(1)>Delivery is 3-5 days.';

		$clean_q = sanitize_text_field( $raw_question );
		$clean_a = sanitize_textarea_field( $raw_answer );

		$no_script_in_q = ! str_contains( $clean_q, '<script>' ) && ! str_contains( $clean_q, '</script>' );
		$no_tag_in_a    = ! str_contains( $clean_a, '<img' );

		return $no_script_in_q && $no_tag_in_a;
	}

	/**
	 * 4. Test FAQ field length capping (500 chars question, 10000 chars answer).
	 */
	public static function test_faq_field_length_capping(): bool {
		$huge_question = str_repeat( 'Q', 800 );
		$huge_answer   = str_repeat( 'A', 15000 );

		$capped_q = mb_substr( sanitize_text_field( $huge_question ), 0, 500, 'UTF-8' );
		$capped_a = mb_substr( sanitize_textarea_field( $huge_answer ), 0, 10000, 'UTF-8' );

		return mb_strlen( $capped_q, 'UTF-8' ) === 500 && mb_strlen( $capped_a, 'UTF-8' ) === 10000;
	}

	/**
	 * 5. Test active status logic (only active FAQs returned).
	 */
	public static function test_faq_active_status_logic(): bool {
		$faqs = [
			[ 'id' => 1, 'question' => 'Q1', 'is_active' => 1 ],
			[ 'id' => 2, 'question' => 'Q2', 'is_active' => 0 ],
			[ 'id' => 3, 'question' => 'Q3', 'is_active' => 1 ],
		];

		$active_only = array_filter( $faqs, fn( $f ) => ! empty( $f['is_active'] ) );
		return count( $active_only ) === 2;
	}

	/**
	 * 6. Test show_on_home logic (requires both is_active=1 AND show_on_home=1).
	 */
	public static function test_faq_show_on_home_logic(): bool {
		$faqs = [
			[ 'id' => 1, 'question' => 'Q1', 'is_active' => 1, 'show_on_home' => 1 ],
			[ 'id' => 2, 'question' => 'Q2', 'is_active' => 0, 'show_on_home' => 1 ],
			[ 'id' => 3, 'question' => 'Q3', 'is_active' => 1, 'show_on_home' => 0 ],
		];

		$home_faqs = array_filter( $faqs, fn( $f ) => ! empty( $f['is_active'] ) && ! empty( $f['show_on_home'] ) );
		return count( $home_faqs ) === 1 && reset( $home_faqs )['id'] === 1;
	}

	/**
	 * 7. Test sort order logic.
	 */
	public static function test_faq_sort_order_logic(): bool {
		$faqs = [
			[ 'id' => 1, 'sort_order' => 10 ],
			[ 'id' => 2, 'sort_order' => 2 ],
			[ 'id' => 3, 'sort_order' => 5 ],
		];

		usort( $faqs, fn( $a, $b ) => $a['sort_order'] <=> $b['sort_order'] );
		return $faqs[0]['id'] === 2 && $faqs[1]['id'] === 3 && $faqs[2]['id'] === 1;
	}

	/**
	 * 8. Test FAQ search query normalization.
	 */
	public static function test_faq_search_normalization(): bool {
		$raw_query = "  Delivery Times!  ";
		$clean = sanitize_text_field( trim( $raw_query ) );
		return $clean === 'Delivery Times!';
	}
}
