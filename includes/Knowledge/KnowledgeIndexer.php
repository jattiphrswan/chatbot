<?php
/**
 * Knowledge Indexer for Gemini Chat Assistant.
 *
 * Normalizes WordPress content, generates deterministic text chunks, calculates hashes,
 * and maintains the knowledge index.
 *
 * @package SkyFish\GeminiChat\Knowledge
 */

namespace SkyFish\GeminiChat\Knowledge;

use SkyFish\GeminiChat\Database\KnowledgeRepository;
use SkyFish\GeminiChat\Database\FaqRepository;
use SkyFish\GeminiChat\Admin\SettingsService;
use WP_Post;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KnowledgeIndexer
 */
class KnowledgeIndexer {

	public const TARGET_CHUNK_SIZE    = 1600;
	public const CHUNK_OVERLAP        = 200;
	public const MAX_SOURCE_TEXT_LEN  = 100000;

	private KnowledgeRepository $knowledge_repo;
	private FaqRepository $faq_repo;

	/**
	 * KnowledgeIndexer constructor.
	 *
	 * @param KnowledgeRepository|null $knowledge_repo Optional knowledge repository.
	 * @param FaqRepository|null       $faq_repo       Optional FAQ repository.
	 */
	public function __construct(
		?KnowledgeRepository $knowledge_repo = null,
		?FaqRepository $faq_repo = null
	) {
		$this->knowledge_repo = $knowledge_repo ?? new KnowledgeRepository();
		$this->faq_repo       = $faq_repo ?? new FaqRepository();
	}

	/**
	 * Registers WordPress lifecycle synchronization hooks.
	 */
	public function init_hooks(): void {
		add_action( 'save_post', [ $this, 'on_save_post' ], 10, 3 );
		add_action( 'before_delete_post', [ $this, 'on_delete_post' ], 10, 1 );
		add_action( 'transition_post_status', [ $this, 'on_transition_post_status' ], 10, 3 );
	}

	/**
	 * Normalizes arbitrary content into clean, plain-text reference material.
	 *
	 * Strips shortcodes without executing them, strips tags, comments, and scripts.
	 *
	 * @param string $raw_content Raw text or markup.
	 * @return string Clean normalized text.
	 */
	public static function normalize_content( string $raw_content ): string {
		if ( '' === trim( $raw_content ) ) {
			return '';
		}

		// 1. Remove script and style tags with their contents.
		$cleaned = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', ' ', $raw_content );
		if ( null === $cleaned ) {
			$cleaned = $raw_content;
		}

		// 2. Remove Gutenberg block comments: <!-- wp:... --> and <!-- /wp:... -->
		$cleaned = preg_replace( '/<!--\s*\/?wp:[^\>]*-->/i', ' ', $cleaned );
		if ( null === $cleaned ) {
			$cleaned = $raw_content;
		}

		// 3. Strip WordPress shortcodes safely without executing them.
		if ( function_exists( 'strip_shortcodes' ) ) {
			$cleaned = strip_shortcodes( $cleaned );
		}

		// 4. Strip all HTML tags.
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$cleaned = wp_strip_all_tags( $cleaned );
		} else {
			$cleaned = strip_tags( $cleaned );
		}

		// 5. Decode HTML entities and normalize whitespace.
		$cleaned = html_entity_decode( $cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$cleaned = preg_replace( '/[ \t\r\n\v\f]+/', ' ', $cleaned );

		$cleaned = trim( (string) $cleaned );

		// 6. Enforce safe ceiling length.
		if ( mb_strlen( $cleaned, 'UTF-8' ) > self::MAX_SOURCE_TEXT_LEN ) {
			$cleaned = mb_substr( $cleaned, 0, self::MAX_SOURCE_TEXT_LEN, 'UTF-8' );
		}

		return $cleaned;
	}

	/**
	 * Splits text into deterministic chunks with boundary awareness and overlap.
	 *
	 * @param string $text Clean text to chunk.
	 * @return string[] Array of chunk texts.
	 */
	public static function chunk_text( string $text ): array {
		$text = trim( $text );
		if ( '' === $text ) {
			return [];
		}

		$length = mb_strlen( $text, 'UTF-8' );
		if ( $length <= self::TARGET_CHUNK_SIZE ) {
			return [ $text ];
		}

		$chunks = [];
		$offset = 0;

		while ( $offset < $length ) {
			$remaining = $length - $offset;

			// If remaining fits in target chunk size, take the rest.
			if ( $remaining <= self::TARGET_CHUNK_SIZE ) {
				$chunk = trim( mb_substr( $text, $offset, $remaining, 'UTF-8' ) );
				if ( '' !== $chunk ) {
					$chunks[] = $chunk;
				}
				break;
			}

			// Slice a window of target chunk size.
			$window = mb_substr( $text, $offset, self::TARGET_CHUNK_SIZE, 'UTF-8' );

			// Look for a natural split boundary (paragraph, newline, sentence, or space) near end of window.
			$split_pos = false;
			$search_start = max( 0, self::TARGET_CHUNK_SIZE - self::CHUNK_OVERLAP );

			// Check for sentence end: ". ", "! ", "? "
			foreach ( [ ". ", "! ", "? ", "\n\n", "\n", " " ] as $delimiter ) {
				$pos = mb_strrpos( mb_substr( $window, $search_start, null, 'UTF-8' ), $delimiter, 0, 'UTF-8' );
				if ( false !== $pos ) {
					$split_pos = $search_start + $pos + mb_strlen( $delimiter, 'UTF-8' );
					break;
				}
			}

			if ( false === $split_pos || $split_pos <= 0 ) {
				// No clean boundary found; split hard at target chunk size.
				$split_pos = self::TARGET_CHUNK_SIZE;
			}

			$chunk = trim( mb_substr( $text, $offset, $split_pos, 'UTF-8' ) );
			if ( '' !== $chunk ) {
				$chunks[] = $chunk;
			}

			// Advance offset with overlap.
			$step = max( 1, $split_pos - self::CHUNK_OVERLAP );
			$offset += $step;
		}

		return $chunks;
	}

	/**
	 * Indexes a single WordPress post/page/product if eligible.
	 *
	 * @param WP_Post|int $post Post object or post ID.
	 * @return bool True if indexed or successfully skipped as unchanged.
	 */
	public function index_post( WP_Post|int $post ): bool {
		$post_obj = is_numeric( $post ) ? get_post( $post ) : $post;
		if ( ! $post_obj instanceof WP_Post ) {
			return false;
		}

		$post_type = $post_obj->post_type;

		// 1. Check if post type is enabled in settings.
		if ( 'page' === $post_type && ! (bool) SettingsService::get( 'knowledge_pages_enabled', true ) ) {
			return false;
		}
		if ( 'post' === $post_type && ! (bool) SettingsService::get( 'knowledge_posts_enabled', true ) ) {
			return false;
		}
		if ( 'product' === $post_type ) {
			if ( ! function_exists( 'WC' ) || ! (bool) SettingsService::get( 'knowledge_products_enabled', false ) ) {
				return false;
			}
		}

		// Only supported types
		if ( ! in_array( $post_type, [ 'page', 'post', 'product' ], true ) ) {
			return false;
		}

		// 2. Check publication and privacy status.
		if ( 'publish' !== $post_obj->post_status || ! empty( $post_obj->post_password ) ) {
			// Remove any previously indexed source
			$this->knowledge_repo->delete_source_by_object( $post_type, $post_obj->ID );
			return false;
		}

		// 3. Extract and normalize content.
		$title = sanitize_text_field( $post_obj->post_title );
		$body  = self::normalize_content( $post_obj->post_content );
		if ( ! empty( $post_obj->post_excerpt ) ) {
			$excerpt = self::normalize_content( $post_obj->post_excerpt );
			$body    = $excerpt . "\n\n" . $body;
		}

		// Skip empty content
		if ( '' === trim( $body ) ) {
			$this->knowledge_repo->delete_source_by_object( $post_type, $post_obj->ID );
			return false;
		}

		$full_text    = "Title: {$title}\n\n{$body}";
		$content_hash = hash( 'sha256', $full_text );
		$permalink    = get_permalink( $post_obj->ID );
		$url          = is_string( $permalink ) ? $permalink : null;

		// 4. Check for existing source with same content hash.
		$existing = $this->knowledge_repo->get_source_by_object( $post_type, $post_obj->ID );
		if ( $existing && $existing['content_hash'] === $content_hash && $existing['status'] === 'indexed' ) {
			// Unchanged: skip re-chunking
			return true;
		}

		// 5. Chunk text.
		$text_chunks = self::chunk_text( $full_text );
		if ( empty( $text_chunks ) ) {
			return false;
		}

		// 6. Upsert source record.
		$source = $this->knowledge_repo->upsert_source( [
			'source_type'      => $post_type,
			'source_object_id' => $post_obj->ID,
			'title'            => $title,
			'url'              => $url,
			'content_hash'     => $content_hash,
			'status'           => 'indexed',
		] );

		if ( ! $source || empty( $source['id'] ) ) {
			return false;
		}

		$source_id = (int) $source['id'];

		// 7. Delete previous chunks and insert fresh chunks.
		$this->knowledge_repo->delete_chunks_by_source_id( $source_id );

		$chunks_to_insert = [];
		foreach ( $text_chunks as $chunk_text ) {
			$chunks_to_insert[] = [
				'content'      => $chunk_text,
				'content_hash' => hash( 'sha256', $chunk_text ),
			];
		}

		return $this->knowledge_repo->insert_chunks( $source_id, $chunks_to_insert );
	}

	/**
	 * Indexes an FAQ record.
	 *
	 * @param array<string, mixed> $faq FAQ data array.
	 * @return bool True if indexed.
	 */
	public function index_faq( array $faq ): bool {
		if ( ! (bool) SettingsService::get( 'knowledge_faqs_enabled', true ) ) {
			return false;
		}

		$public_id = (string) ( $faq['public_id'] ?? '' );
		if ( empty( $public_id ) ) {
			return false;
		}

		// If FAQ is inactive, remove from index.
		if ( empty( $faq['is_active'] ) ) {
			$this->knowledge_repo->delete_source_by_faq( $public_id );
			return false;
		}

		$question = sanitize_text_field( (string) ( $faq['question'] ?? '' ) );
		$answer   = self::normalize_content( (string) ( $faq['answer'] ?? '' ) );
		$category = ! empty( $faq['category'] ) ? sanitize_text_field( (string) $faq['category'] ) : '';

		if ( '' === $question || '' === $answer ) {
			$this->knowledge_repo->delete_source_by_faq( $public_id );
			return false;
		}

		$body = "FAQ Question: {$question}\nFAQ Answer: {$answer}";
		if ( '' !== $category ) {
			$body .= "\nCategory: {$category}";
		}

		$content_hash = hash( 'sha256', $body );

		// Check if unchanged
		$existing = $this->knowledge_repo->get_source_by_faq( $public_id );
		if ( $existing && $existing['content_hash'] === $content_hash && $existing['status'] === 'indexed' ) {
			return true;
		}

		// Upsert source
		$source = $this->knowledge_repo->upsert_source( [
			'source_type'      => 'faq',
			'source_public_id' => $public_id,
			'title'            => $question,
			'url'              => null,
			'content_hash'     => $content_hash,
			'status'           => 'indexed',
		] );

		if ( ! $source || empty( $source['id'] ) ) {
			return false;
		}

		$source_id   = (int) $source['id'];
		$text_chunks = self::chunk_text( $body );

		$this->knowledge_repo->delete_chunks_by_source_id( $source_id );

		$chunks_to_insert = [];
		foreach ( $text_chunks as $chunk_text ) {
			$chunks_to_insert[] = [
				'content'      => $chunk_text,
				'content_hash' => hash( 'sha256', $chunk_text ),
			];
		}

		return $this->knowledge_repo->insert_chunks( $source_id, $chunks_to_insert );
	}

	/**
	 * Synchronizes knowledge index on WordPress save_post hook.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 */
	public function on_save_post( int $post_id, WP_Post $post, bool $update ): void {
		// Ignore revisions and autosaves.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// If knowledge indexing is globally disabled, skip sync.
		if ( ! (bool) SettingsService::get( 'knowledge_enabled', false ) ) {
			return;
		}

		$this->index_post( $post );
	}

	/**
	 * Cleans up knowledge index on before_delete_post hook.
	 *
	 * @param int $post_id Post ID being deleted.
	 */
	public function on_delete_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( $post instanceof WP_Post ) {
			$this->knowledge_repo->delete_source_by_object( $post->post_type, $post_id );
		}
	}

	/**
	 * Handles post status transitions (e.g. publish -> draft or trash).
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public function on_transition_post_status( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'publish' === $old_status && 'publish' !== $new_status ) {
			$this->knowledge_repo->delete_source_by_object( $post->post_type, $post->ID );
		}
	}

	/**
	 * Executes a complete manual batch sync across all enabled WordPress content and FAQs.
	 *
	 * Safe for admin execution: bounded queries, memory protection, non-destructive.
	 *
	 * @return array{indexed: int, skipped: int, errors: int, total_chunks: int}
	 */
	public function sync_all(): array {
		$indexed = 0;
		$skipped = 0;
		$errors  = 0;

		$post_types = [];
		if ( (bool) SettingsService::get( 'knowledge_pages_enabled', true ) ) {
			$post_types[] = 'page';
		}
		if ( (bool) SettingsService::get( 'knowledge_posts_enabled', true ) ) {
			$post_types[] = 'post';
		}
		if ( function_exists( 'WC' ) && (bool) SettingsService::get( 'knowledge_products_enabled', false ) ) {
			$post_types[] = 'product';
		}

		// 1. Index WordPress posts/pages/products
		if ( ! empty( $post_types ) ) {
			$posts = get_posts( [
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 200, // Safe batch limit
				'has_password'   => false,
			] );

			foreach ( $posts as $p ) {
				$res = $this->index_post( $p );
				if ( $res ) {
					$indexed++;
				} else {
					$skipped++;
				}
			}
		}

		// 2. Index active FAQs
		if ( (bool) SettingsService::get( 'knowledge_faqs_enabled', true ) ) {
			$faqs = $this->faq_repo->get_active_faqs( 500 );
			foreach ( $faqs as $faq ) {
				$res = $this->index_faq( $faq );
				if ( $res ) {
					$indexed++;
				} else {
					$skipped++;
				}
			}
		}

		$counts = $this->knowledge_repo->get_counts();

		return [
			'indexed'      => $indexed,
			'skipped'      => $skipped,
			'errors'       => $errors,
			'total_chunks' => $counts['chunks'],
		];
	}
}
