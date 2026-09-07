<?php
/**
 * Test Suite: Node N16 - Knowledge Retriever.
 *
 * Tests lexical query normalization, stopword filtering, candidate scoring,
 * relevance thresholds, top-K clamping, and context budget enforcement.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

use SkyFish\GeminiChat\Knowledge\KnowledgeRetriever;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TestKnowledgeRetriever
 */
class TestKnowledgeRetriever {

	/**
	 * Runs all Knowledge Retriever tests.
	 *
	 * @return array<string, bool> Test results.
	 */
	public static function run_all(): array {
		$results = [];

		$results['test_extract_search_terms_strips_punctuation'] = self::test_extract_search_terms_strips_punctuation();
		$results['test_extract_search_terms_filters_stopwords']   = self::test_extract_search_terms_filters_stopwords();
		$results['test_extract_search_terms_max_limit']          = self::test_extract_search_terms_max_limit();
		$results['test_score_candidate_relevant_vs_irrelevant']  = self::test_score_candidate_relevant_vs_irrelevant();
		$results['test_score_candidate_faq_boost']               = self::test_score_candidate_faq_boost();
		$results['test_score_candidate_exact_phrase_boost']      = self::test_score_candidate_exact_phrase_boost();
		$results['test_irrelevant_query_zero_score']             = self::test_irrelevant_query_zero_score();
		$results['test_min_relevance_threshold_constant']        = self::test_min_relevance_threshold_constant();

		return $results;
	}

	/**
	 * 1. Test extract_search_terms strips punctuation and symbols.
	 */
	public static function test_extract_search_terms_strips_punctuation(): bool {
		$query = "What is your Return-Policy?!";
		$terms = KnowledgeRetriever::extract_search_terms( $query );

		// "what" and "is" and "your" are stopwords. "return" and "policy" should remain.
		return in_array( 'return', $terms, true ) && in_array( 'policy', $terms, true );
	}

	/**
	 * 2. Test extract_search_terms filters common English stopwords.
	 */
	public static function test_extract_search_terms_filters_stopwords(): bool {
		$query = "the and because about return policy";
		$terms = KnowledgeRetriever::extract_search_terms( $query );

		return ! in_array( 'the', $terms, true )
			&& ! in_array( 'and', $terms, true )
			&& ! in_array( 'because', $terms, true )
			&& in_array( 'return', $terms, true )
			&& in_array( 'policy', $terms, true );
	}

	/**
	 * 3. Test extract_search_terms enforces MAX_SEARCH_TERMS (8 terms max).
	 */
	public static function test_extract_search_terms_max_limit(): bool {
		$query = "one two three four five six seven eight nine ten eleven twelve";
		$terms = KnowledgeRetriever::extract_search_terms( $query );

		return count( $terms ) <= KnowledgeRetriever::MAX_SEARCH_TERMS;
	}

	/**
	 * 4. Test candidate scoring: relevant chunk scores significantly higher than irrelevant chunk.
	 */
	public static function test_score_candidate_relevant_vs_irrelevant(): bool {
		$query = "How do I return an item?";
		$terms = KnowledgeRetriever::extract_search_terms( $query ); // ['return', 'item']

		$relevant_chunk = [
			'content'     => 'Returns are allowed within 30 days of purchase for any item.',
			'title'       => 'Return Policy',
			'source_type' => 'page',
		];

		$irrelevant_chunk = [
			'content'     => 'We offer weekend gardening courses for beginners.',
			'title'       => 'Gardening Courses',
			'source_type' => 'page',
		];

		$score_rel   = KnowledgeRetriever::score_candidate( $relevant_chunk, $query, $terms );
		$score_irrel = KnowledgeRetriever::score_candidate( $irrelevant_chunk, $query, $terms );

		return $score_rel > 10 && $score_irrel === 0;
	}

	/**
	 * 5. Test FAQ source receives a scoring boost.
	 */
	public static function test_score_candidate_faq_boost(): bool {
		$query = "delivery times";
		$terms = [ 'delivery', 'times' ];

		$page_chunk = [
			'content'     => 'Orders usually arrive within 3-5 business days for delivery.',
			'title'       => 'Shipping Page',
			'source_type' => 'page',
		];

		$faq_chunk = [
			'content'     => 'FAQ Question: What are delivery times?\nFAQ Answer: Delivery takes 3-5 days.',
			'title'       => 'What are delivery times?',
			'source_type' => 'faq',
		];

		$score_page = KnowledgeRetriever::score_candidate( $page_chunk, $query, $terms );
		$score_faq  = KnowledgeRetriever::score_candidate( $faq_chunk, $query, $terms );

		return $score_faq > $score_page;
	}

	/**
	 * 6. Test exact phrase match boost.
	 */
	public static function test_score_candidate_exact_phrase_boost(): bool {
		$query = "return policy";
		$terms = [ 'return', 'policy' ];

		$chunk_with_exact_phrase = [
			'content'     => 'Please check our return policy before shipping products back.',
			'title'       => 'Help',
			'source_type' => 'page',
		];

		$chunk_scattered = [
			'content'     => 'We will return your call tomorrow per company policy.',
			'title'       => 'Help',
			'source_type' => 'page',
		];

		$score_exact     = KnowledgeRetriever::score_candidate( $chunk_with_exact_phrase, $query, $terms );
		$score_scattered = KnowledgeRetriever::score_candidate( $chunk_scattered, $query, $terms );

		return $score_exact > $score_scattered;
	}

	/**
	 * 7. Test completely irrelevant query produces score 0.
	 */
	public static function test_irrelevant_query_zero_score(): bool {
		$query = "Who discovered gravity?";
		$terms = KnowledgeRetriever::extract_search_terms( $query ); // ['discovered', 'gravity']

		$ecommerce_chunk = [
			'content'     => 'We accept Visa, MasterCard, PayPal, and Apple Pay.',
			'title'       => 'Payment Methods',
			'source_type' => 'page',
		];

		$score = KnowledgeRetriever::score_candidate( $ecommerce_chunk, $query, $terms );
		return $score === 0;
	}

	/**
	 * 8. Test minimum relevance threshold constant.
	 */
	public static function test_min_relevance_threshold_constant(): bool {
		return KnowledgeRetriever::MIN_RELEVANCE_SCORE >= 3;
	}
}
