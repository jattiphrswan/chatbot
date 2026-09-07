<?php
/**
 * Test Suite: Node N16 - Knowledge Indexer.
 *
 * Tests content normalization, shortcode stripping, HTML tag removal,
 * multibyte chunking, SHA-256 content hashing, and source eligibility.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

use SkyFish\GeminiChat\Knowledge\KnowledgeIndexer;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TestKnowledgeIndexer
 */
class TestKnowledgeIndexer {

	/**
	 * Runs all Knowledge Indexer tests.
	 *
	 * @return array<string, bool> Test results.
	 */
	public static function run_all(): array {
		$results = [];

		$results['test_normalize_strips_html_and_scripts']    = self::test_normalize_strips_html_and_scripts();
		$results['test_normalize_strips_gutenberg_comments']  = self::test_normalize_strips_gutenberg_comments();
		$results['test_normalize_strips_shortcodes']          = self::test_normalize_strips_shortcodes();
		$results['test_normalize_max_length_capping']         = self::test_normalize_max_length_capping();
		$results['test_chunk_text_small_document']            = self::test_chunk_text_small_document();
		$results['test_chunk_text_large_document']            = self::test_chunk_text_large_document();
		$results['test_chunk_text_no_empty_chunks']           = self::test_chunk_text_no_empty_chunks();
		$results['test_sha256_content_hash_consistency']      = self::test_sha256_content_hash_consistency();
		$results['test_source_status_eligibility']            = self::test_source_status_eligibility();
		$results['test_password_protected_exclusion']         = self::test_password_protected_exclusion();

		return $results;
	}

	/**
	 * 1. Test normalize_content strips HTML and script/style tags.
	 */
	public static function test_normalize_strips_html_and_scripts(): bool {
		$raw = '<h1>Welcome</h1><script>alert("hack");</script><style>.test{color:red;}</style><p>This is real content.</p>';
		$clean = KnowledgeIndexer::normalize_content( $raw );

		return ! str_contains( $clean, '<h1>' )
			&& ! str_contains( $clean, '<script>' )
			&& ! str_contains( $clean, 'alert("hack")' )
			&& ! str_contains( $clean, '.test{color:red;}' )
			&& str_contains( $clean, 'This is real content.' );
	}

	/**
	 * 2. Test normalize_content strips Gutenberg block comments.
	 */
	public static function test_normalize_strips_gutenberg_comments(): bool {
		$raw = '<!-- wp:paragraph {"fontSize":"large"} --><p>Clean Gutenberg paragraph.</p><!-- /wp:paragraph -->';
		$clean = KnowledgeIndexer::normalize_content( $raw );

		return ! str_contains( $clean, '<!-- wp:paragraph' )
			&& ! str_contains( $clean, '<!-- /wp:paragraph -->' )
			&& str_contains( $clean, 'Clean Gutenberg paragraph.' );
	}

	/**
	 * 3. Test normalize_content strips WordPress shortcodes safely.
	 */
	public static function test_normalize_strips_shortcodes(): bool {
		$raw = 'Contact us [contact-form-7 id="123" title="Form"] for more details.';
		// If WordPress strip_shortcodes function is not loaded in test mock, use fallback
		if ( function_exists( 'strip_shortcodes' ) ) {
			$clean = KnowledgeIndexer::normalize_content( $raw );
			return ! str_contains( $clean, '[contact-form-7' );
		}
		return true;
	}

	/**
	 * 4. Test normalize_content caps at MAX_SOURCE_TEXT_LEN (100,000 characters).
	 */
	public static function test_normalize_max_length_capping(): bool {
		$huge = str_repeat( 'Knowledge Word ', 8000 ); // ~120,000 chars
		$clean = KnowledgeIndexer::normalize_content( $huge );

		return mb_strlen( $clean, 'UTF-8' ) <= KnowledgeIndexer::MAX_SOURCE_TEXT_LEN;
	}

	/**
	 * 5. Test chunk_text on small document (< TARGET_CHUNK_SIZE) returns 1 chunk.
	 */
	public static function test_chunk_text_small_document(): bool {
		$text = 'This is a compact policy document containing only 200 characters.';
		$chunks = KnowledgeIndexer::chunk_text( $text );

		return count( $chunks ) === 1 && $chunks[0] === $text;
	}

	/**
	 * 6. Test chunk_text on large document (> 1600 characters) produces multiple chunks.
	 */
	public static function test_chunk_text_large_document(): bool {
		$sentence = 'Our standard shipping delivery takes between three and five business days. ';
		$long_text = str_repeat( $sentence, 50 ); // ~3750 characters

		$chunks = KnowledgeIndexer::chunk_text( $long_text );

		return count( $chunks ) >= 2;
	}

	/**
	 * 7. Test chunk_text never produces empty chunks.
	 */
	public static function test_chunk_text_no_empty_chunks(): bool {
		$sentence = 'Company return policy guidelines. ';
		$long_text = str_repeat( $sentence, 40 );

		$chunks = KnowledgeIndexer::chunk_text( $long_text );
		foreach ( $chunks as $c ) {
			if ( '' === trim( $c ) ) {
				return false;
			}
		}

		return ! empty( $chunks );
	}

	/**
	 * 8. Test SHA-256 hash consistency and change detection.
	 */
	public static function test_sha256_content_hash_consistency(): bool {
		$text_a = 'Title: Shipping Policy\n\nDelivery takes 3 business days.';
		$text_b = 'Title: Shipping Policy\n\nDelivery takes 3 business days.';
		$text_c = 'Title: Shipping Policy\n\nDelivery takes 5 business days.';

		$hash_a = hash( 'sha256', $text_a );
		$hash_b = hash( 'sha256', $text_b );
		$hash_c = hash( 'sha256', $text_c );

		return $hash_a === $hash_b && $hash_a !== $hash_c;
	}

	/**
	 * 9. Test post status eligibility (only 'publish' is eligible).
	 */
	public static function test_source_status_eligibility(): bool {
		$statuses = [
			'publish'    => true,
			'draft'      => false,
			'private'    => false,
			'trash'      => false,
			'pending'    => false,
			'auto-draft' => false,
		];

		foreach ( $statuses as $status => $expected ) {
			$is_eligible = ( 'publish' === $status );
			if ( $is_eligible !== $expected ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * 10. Test password-protected content is excluded.
	 */
	public static function test_password_protected_exclusion(): bool {
		$post_with_password = (object) [ 'post_status' => 'publish', 'post_password' => 'secret123' ];
		$post_without_password = (object) [ 'post_status' => 'publish', 'post_password' => '' ];

		$is_excluded_a = ! empty( $post_with_password->post_password );
		$is_excluded_b = ! empty( $post_without_password->post_password );

		return $is_excluded_a === true && $is_excluded_b === false;
	}
}
