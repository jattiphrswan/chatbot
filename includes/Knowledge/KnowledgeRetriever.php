<?php
/**
 * Knowledge Retriever for Gemini Chat Assistant.
 *
 * Performs deterministic lexical retrieval and relevance scoring over indexed knowledge chunks.
 *
 * @package SkyFish\GeminiChat\Knowledge
 */

namespace SkyFish\GeminiChat\Knowledge;

use SkyFish\GeminiChat\Database\KnowledgeRepository;
use SkyFish\GeminiChat\Admin\SettingsService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KnowledgeRetriever
 */
class KnowledgeRetriever {

	public const MAX_SEARCH_TERMS     = 8;
	public const MAX_TERM_LEN         = 50;
	public const MIN_RELEVANCE_SCORE  = 3; // Minimum score required to consider a chunk relevant
	public const DEFAULT_MAX_CHUNKS   = 4;
	public const DEFAULT_CONTEXT_CHARS = 6000;

	private KnowledgeRepository $knowledge_repo;

	/**
	 * Common English stopwords to ignore during query tokenization.
	 *
	 * @var string[]
	 */
	private static array $stopwords = [
		'about', 'above', 'after', 'again', 'against', 'all', 'and', 'any', 'are', 'aren',
		'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'can', 'cannot',
		'could', 'did', 'does', 'doing', 'down', 'during', 'each', 'few', 'for', 'from',
		'further', 'had', 'has', 'have', 'having', 'her', 'here', 'hers', 'herself', 'him',
		'himself', 'his', 'how', 'into', 'its', 'itself', 'just', 'more', 'most', 'myself',
		'nor', 'not', 'now', 'off', 'once', 'only', 'other', 'our', 'ours', 'ourselves',
		'out', 'over', 'own', 'same', 'should', 'some', 'such', 'than', 'that', 'the',
		'their', 'theirs', 'them', 'themselves', 'then', 'there', 'these', 'they', 'this',
		'those', 'through', 'too', 'under', 'until', 'very', 'was', 'were', 'what', 'when',
		'where', 'which', 'while', 'who', 'whom', 'why', 'with', 'would', 'you', 'your',
		'yours', 'yourself', 'yourselves',
	];

	/**
	 * KnowledgeRetriever constructor.
	 *
	 * @param KnowledgeRepository|null $knowledge_repo Optional repository.
	 */
	public function __construct( ?KnowledgeRepository $knowledge_repo = null ) {
		$this->knowledge_repo = $knowledge_repo ?? new KnowledgeRepository();
	}

	/**
	 * Normalizes a visitor question into a bounded list of significant search terms.
	 *
	 * @param string $query Visitor question text.
	 * @return string[] Array of significant query terms (up to MAX_SEARCH_TERMS).
	 */
	public static function extract_search_terms( string $query ): array {
		// Convert to lowercase and strip unwanted characters except letters, digits, and spaces.
		$cleaned = mb_strtolower( trim( $query ), 'UTF-8' );
		$cleaned = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $cleaned );
		if ( null === $cleaned || '' === trim( $cleaned ) ) {
			return [];
		}

		$words = preg_split( '/\s+/u', $cleaned, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) ) {
			return [];
		}

		$terms = [];
		foreach ( $words as $word ) {
			$word = trim( $word );
			$len  = mb_strlen( $word, 'UTF-8' );

			// Filter out terms that are too short, too long, or in stopword list.
			if ( $len < 3 || $len > self::MAX_TERM_LEN ) {
				continue;
			}

			if ( in_array( $word, self::$stopwords, true ) ) {
				continue;
			}

			if ( ! in_array( $word, $terms, true ) ) {
				$terms[] = $word;
			}

			if ( count( $terms ) >= self::MAX_SEARCH_TERMS ) {
				break;
			}
		}

		return $terms;
	}

	/**
	 * Scores a candidate chunk against the visitor query and extracted terms.
	 *
	 * Potential scoring signals:
	 * 1. Term frequency in chunk content (+2 pts per hit, capped at 10 pts per term)
	 * 2. Exact full query phrase match in chunk (+15 pts boost)
	 * 3. Search term match in source title (+5 pts per term)
	 * 4. FAQ source boost (+8 pts for direct FAQ grounding)
	 *
	 * @param array<string, mixed> $candidate Candidate chunk row from search_candidate_chunks.
	 * @param string               $raw_query Original visitor query.
	 * @param string[]             $terms     Extracted significant terms.
	 * @return int Total relevance score.
	 */
	public static function score_candidate( array $candidate, string $raw_query, array $terms ): int {
		$score   = 0;
		$content = mb_strtolower( (string) ( $candidate['content'] ?? '' ), 'UTF-8' );
		$title   = mb_strtolower( (string) ( $candidate['title'] ?? '' ), 'UTF-8' );
		$type    = (string) ( $candidate['source_type'] ?? '' );

		$normalized_query = mb_strtolower( trim( $raw_query ), 'UTF-8' );

		// 1. Term frequency in chunk content
		$term_matches = 0;
		foreach ( $terms as $term ) {
			$count = substr_count( $content, $term );
			if ( $count > 0 ) {
				$term_matches++;
				$score += min( 10, $count * 2 );
			}
		}

		// If no significant terms matched the content, check title
		foreach ( $terms as $term ) {
			if ( str_contains( $title, $term ) ) {
				$score += 5;
				$term_matches++;
			}
		}

		// If zero terms matched anywhere, candidate gets 0
		if ( 0 === $term_matches ) {
			return 0;
		}

		// 2. Exact phrase match boost (for queries with at least 2 words)
		if ( mb_strlen( $normalized_query, 'UTF-8' ) >= 6 && str_contains( $content, $normalized_query ) ) {
			$score += 15;
		}

		// 3. FAQ boost: FAQs are authored directly as Q&A answers
		if ( 'faq' === $type ) {
			$score += 8;
		}

		return $score;
	}

	/**
	 * Retrieves and ranks top relevant chunks for a visitor question.
	 *
	 * Enforces:
	 * - Minimum relevance score threshold
	 * - Configured knowledge_max_chunks (top-K)
	 * - Configured knowledge_max_context_chars (character budget)
	 *
	 * @param string $query Visitor message.
	 * @return array<int, array<string, mixed>> Ranked relevant chunks.
	 */
	public function retrieve( string $query ): array {
		$terms = self::extract_search_terms( $query );
		if ( empty( $terms ) ) {
			return [];
		}

		// 1. Fetch candidate chunks (up to 30 candidates from DB)
		$candidates = $this->knowledge_repo->search_candidate_chunks( $terms, 30 );
		if ( empty( $candidates ) ) {
			return [];
		}

		// 2. Score and rank candidates
		$scored = [];
		foreach ( $candidates as $candidate ) {
			$score = self::score_candidate( $candidate, $query, $terms );
			if ( $score >= self::MIN_RELEVANCE_SCORE ) {
				$candidate['relevance_score'] = $score;
				$scored[]                     = $candidate;
			}
		}

		if ( empty( $scored ) ) {
			return [];
		}

		// Sort by score descending
		usort( $scored, function ( array $a, array $b ): int {
			return $b['relevance_score'] <=> $a['relevance_score'];
		} );

		// 3. Apply Top-K ceiling
		$max_chunks = (int) SettingsService::get( 'knowledge_max_chunks', self::DEFAULT_MAX_CHUNKS );
		$max_chunks = max( 1, min( 8, $max_chunks ) );

		$top_chunks = array_slice( $scored, 0, $max_chunks );

		// 4. Apply maximum context character budget
		$max_chars = (int) SettingsService::get( 'knowledge_max_context_chars', self::DEFAULT_CONTEXT_CHARS );
		$max_chars = max( 500, min( 12000, $max_chars ) );

		$selected_chunks = [];
		$current_chars   = 0;

		foreach ( $top_chunks as $chunk ) {
			$chunk_len = mb_strlen( (string) $chunk['content'], 'UTF-8' );
			if ( $current_chars + $chunk_len > $max_chars && ! empty( $selected_chunks ) ) {
				// Stop adding chunks if budget exceeded
				break;
			}

			$selected_chunks[] = $chunk;
			$current_chars    += $chunk_len;
		}

		return $selected_chunks;
	}
}
