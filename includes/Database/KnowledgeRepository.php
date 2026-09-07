<?php
/**
 * Knowledge Repository for Gemini Chat Assistant.
 *
 * Provides database abstraction for gca_knowledge_sources and gca_knowledge_chunks.
 *
 * @package SkyFish\GeminiChat\Database
 */

namespace SkyFish\GeminiChat\Database;

use wpdb;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KnowledgeRepository
 *
 * Encapsulates all database operations for knowledge sources and chunks.
 */
class KnowledgeRepository {

	public const TABLE_SOURCES = 'gca_knowledge_sources';
	public const TABLE_CHUNKS  = 'gca_knowledge_chunks';

	private wpdb $wpdb;
	private string $sources_table;
	private string $chunks_table;

	/**
	 * KnowledgeRepository constructor.
	 *
	 * @param wpdb|null $wpdb Optional WordPress database abstraction instance.
	 */
	public function __construct( ?wpdb $wpdb = null ) {
		global $wpdb;
		$this->wpdb          = $wpdb;
		$this->sources_table = $this->wpdb->prefix . self::TABLE_SOURCES;
		$this->chunks_table  = $this->wpdb->prefix . self::TABLE_CHUNKS;
	}

	/**
	 * Upserts a knowledge source record.
	 *
	 * If source exists by (source_type, source_object_id) or (source_type, source_public_id), updates it.
	 * Otherwise creates a new source record.
	 *
	 * @param array<string, mixed> $data Source data.
	 * @return array<string, mixed>|null Upserted source row.
	 */
	public function upsert_source( array $data ): ?array {
		$source_type      = sanitize_key( (string) ( $data['source_type'] ?? 'page' ) );
		$source_object_id = isset( $data['source_object_id'] ) && $data['source_object_id'] > 0 ? absint( $data['source_object_id'] ) : null;
		$source_public_id = ! empty( $data['source_public_id'] ) ? sanitize_text_field( (string) $data['source_public_id'] ) : null;
		$title            = sanitize_text_field( (string) ( $data['title'] ?? '' ) );
		$url              = ! empty( $data['url'] ) ? esc_url_raw( (string) $data['url'] ) : null;
		$content_hash     = sanitize_text_field( (string) ( $data['content_hash'] ?? '' ) );
		$status           = in_array( $data['status'] ?? '', [ 'indexed', 'pending', 'error' ], true ) ? $data['status'] : 'indexed';
		$now              = current_time( 'mysql', true );

		// Validate allowed source type.
		if ( ! in_array( $source_type, [ 'page', 'post', 'product', 'faq' ], true ) ) {
			return null;
		}

		// Look for existing source.
		$existing = null;
		if ( null !== $source_object_id ) {
			$existing = $this->get_source_by_object( $source_type, $source_object_id );
		} elseif ( null !== $source_public_id ) {
			$existing = $this->get_source_by_faq( $source_public_id );
		}

		if ( $existing ) {
			$source_id = (int) $existing['id'];
			$update_data = [
				'title'        => $title,
				'url'          => $url,
				'content_hash' => $content_hash,
				'status'       => $status,
				'indexed_at'   => $now,
				'updated_at'   => $now,
			];

			$formats = [
				'%s', // title
				$url !== null ? '%s' : null,
				'%s', // content_hash
				'%s', // status
				'%s', // indexed_at
				'%s', // updated_at
			];

			// Clean formats
			$filtered_data    = [];
			$filtered_formats = [];
			$keys             = array_keys( $update_data );
			foreach ( $keys as $idx => $key ) {
				$val = $update_data[ $key ];
				$filtered_data[ $key ] = $val;
				$filtered_formats[]    = $formats[ $idx ] ?? '%s';
			}

			$this->wpdb->update(
				$this->sources_table,
				$filtered_data,
				[ 'id' => $source_id ],
				$filtered_formats,
				[ '%d' ]
			);

			return $this->get_source_by_id( $source_id );
		}

		// Insert new source
		$public_id = ! empty( $data['public_id'] ) ? sanitize_text_field( (string) $data['public_id'] ) : ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'src_', true ) );

		$insert_data = [
			'public_id'        => $public_id,
			'source_type'      => $source_type,
			'source_object_id' => $source_object_id,
			'source_public_id' => $source_public_id,
			'title'            => $title,
			'url'              => $url,
			'content_hash'     => $content_hash,
			'status'           => $status,
			'indexed_at'       => $now,
			'updated_at'       => $now,
		];

		$formats = [
			'%s', // public_id
			'%s', // source_type
			$source_object_id !== null ? '%d' : null,
			$source_public_id !== null ? '%s' : null,
			'%s', // title
			$url !== null ? '%s' : null,
			'%s', // content_hash
			'%s', // status
			'%s', // indexed_at
			'%s', // updated_at
		];

		$filtered_data    = [];
		$filtered_formats = [];
		$keys             = array_keys( $insert_data );
		foreach ( $keys as $idx => $key ) {
			$val = $insert_data[ $key ];
			if ( null !== $val ) {
				$filtered_data[ $key ] = $val;
				$filtered_formats[]    = $formats[ $idx ] ?? '%s';
			}
		}

		$result = $this->wpdb->insert( $this->sources_table, $filtered_data, $filtered_formats );
		if ( false === $result ) {
			return null;
		}

		$source_id = (int) $this->wpdb->insert_id;
		return $this->get_by_id( $source_id );
	}

	/**
	 * Retrieves source by numeric ID.
	 *
	 * @param int $id Database ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->sources_table} WHERE id = %d LIMIT 1",
			$id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Alias for get_by_id.
	 *
	 * @param int $id Database ID.
	 * @return array<string, mixed>|null
	 */
	public function get_source_by_id( int $id ): ?array {
		return $this->get_by_id( $id );
	}

	/**
	 * Retrieves source by public UUID.
	 *
	 * @param string $public_id Public UUID.
	 * @return array<string, mixed>|null
	 */
	public function get_source_by_public_id( string $public_id ): ?array {
		$sanitized = sanitize_text_field( $public_id );
		if ( empty( $sanitized ) ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->sources_table} WHERE public_id = %s LIMIT 1",
			$sanitized
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Retrieves source by WordPress post/page/product ID.
	 *
	 * @param string $source_type      Source type (page, post, product).
	 * @param int    $source_object_id WordPress post ID.
	 * @return array<string, mixed>|null
	 */
	public function get_source_by_object( string $source_type, int $source_object_id ): ?array {
		if ( $source_object_id <= 0 ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->sources_table} WHERE source_type = %s AND source_object_id = %d LIMIT 1",
			$source_type,
			$source_object_id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Retrieves source by FAQ public ID.
	 *
	 * @param string $source_public_id FAQ public UUID.
	 * @return array<string, mixed>|null
	 */
	public function get_source_by_faq( string $source_public_id ): ?array {
		$sanitized = sanitize_text_field( $source_public_id );
		if ( empty( $sanitized ) ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->sources_table} WHERE source_type = 'faq' AND source_public_id = %s LIMIT 1",
			$sanitized
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Deletes a source and all associated chunks.
	 *
	 * @param int $source_id Source database ID.
	 * @return bool True if deleted.
	 */
	public function delete_source( int $source_id ): bool {
		if ( $source_id <= 0 ) {
			return false;
		}

		// Delete all chunks for this source first.
		$this->delete_chunks_by_source_id( $source_id );

		// Delete source record.
		$result = $this->wpdb->delete(
			$this->sources_table,
			[ 'id' => $source_id ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Deletes a source by WordPress object ID and cleans up its chunks.
	 *
	 * @param string $source_type      Source type.
	 * @param int    $source_object_id Object ID.
	 * @return bool True if deleted or source did not exist.
	 */
	public function delete_source_by_object( string $source_type, int $source_object_id ): bool {
		$source = $this->get_source_by_object( $source_type, $source_object_id );
		if ( ! $source ) {
			return true;
		}

		return $this->delete_source( (int) $source['id'] );
	}

	/**
	 * Deletes a source by FAQ public ID and cleans up its chunks.
	 *
	 * @param string $source_public_id FAQ public UUID.
	 * @return bool True if deleted or source did not exist.
	 */
	public function delete_source_by_faq( string $source_public_id ): bool {
		$source = $this->get_source_by_faq( $source_public_id );
		if ( ! $source ) {
			return true;
		}

		return $this->delete_source( (int) $source['id'] );
	}

	/**
	 * Inserts a batch of chunks for a given source.
	 *
	 * @param int                           $source_id Source database ID.
	 * @param array<int, array{content: string, content_hash: string}> $chunks Array of chunks.
	 * @return bool True if all chunks inserted.
	 */
	public function insert_chunks( int $source_id, array $chunks ): bool {
		if ( $source_id <= 0 || empty( $chunks ) ) {
			return false;
		}

		$now = current_time( 'mysql', true );

		foreach ( $chunks as $index => $chunk ) {
			$content      = (string) ( $chunk['content'] ?? '' );
			$content_hash = (string) ( $chunk['content_hash'] ?? hash( 'sha256', $content ) );

			if ( '' === trim( $content ) ) {
				continue;
			}

			$result = $this->wpdb->insert(
				$this->chunks_table,
				[
					'source_id'    => $source_id,
					'chunk_index'  => (int) $index,
					'content'      => $content,
					'content_hash' => $content_hash,
					'created_at'   => $now,
				],
				[ '%d', '%d', '%s', '%s', '%s' ]
			);

			if ( false === $result ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Deletes all chunks belonging to a specific source.
	 *
	 * @param int $source_id Source database ID.
	 * @return bool True on success.
	 */
	public function delete_chunks_by_source_id( int $source_id ): bool {
		if ( $source_id <= 0 ) {
			return false;
		}

		$result = $this->wpdb->delete(
			$this->chunks_table,
			[ 'source_id' => $source_id ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Retrieves all chunks for a source.
	 *
	 * @param int $source_id Source database ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_chunks_by_source_id( int $source_id ): array {
		if ( $source_id <= 0 ) {
			return [];
		}

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->chunks_table} WHERE source_id = %d ORDER BY chunk_index ASC",
			$source_id
		);

		$results = $this->wpdb->get_results( $sql, ARRAY_A );
		return is_array( $results ) ? $results : [];
	}

	/**
	 * Searches candidate chunks matching lexical search terms.
	 *
	 * Returns chunks joining source metadata where source status is 'indexed'.
	 *
	 * @param string[] $terms Array of sanitized significant search terms.
	 * @param int      $limit Maximum candidate chunks to return.
	 * @return array<int, array<string, mixed>>
	 */
	public function search_candidate_chunks( array $terms, int $limit = 30 ): array {
		if ( empty( $terms ) ) {
			return [];
		}

		$limit = max( 1, min( 100, $limit ) );

		$like_clauses = [];
		$params       = [];

		foreach ( $terms as $term ) {
			$term = trim( $term );
			if ( strlen( $term ) < 2 ) {
				continue;
			}

			$like = '%' . $this->wpdb->esc_like( $term ) . '%';
			// Match in chunk content OR source title
			$like_clauses[] = '(c.content LIKE %s OR s.title LIKE %s)';
			$params[]       = $like;
			$params[]       = $like;
		}

		if ( empty( $like_clauses ) ) {
			return [];
		}

		$where_like = implode( ' OR ', $like_clauses );

		$sql = "SELECT c.id, c.source_id, c.chunk_index, c.content,
		               s.source_type, s.title, s.url, s.source_object_id, s.source_public_id
		        FROM {$this->chunks_table} c
		        INNER JOIN {$this->sources_table} s ON c.source_id = s.id
		        WHERE s.status = 'indexed' AND ({$where_like})
		        ORDER BY c.id DESC
		        LIMIT %d";

		$params[] = $limit;

		$results = $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, ...$params ),
			ARRAY_A
		);

		return is_array( $results ) ? $results : [];
	}

	/**
	 * Retrieves real statistics for the knowledge base.
	 *
	 * @return array{sources: int, chunks: int, last_indexed: ?string}
	 */
	public function get_counts(): array {
		$sources_count = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->sources_table} WHERE status = 'indexed'" );
		$chunks_count  = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->chunks_table}" );
		$last_indexed  = $this->wpdb->get_var( "SELECT MAX(indexed_at) FROM {$this->sources_table}" );

		return [
			'sources'      => $sources_count,
			'chunks'       => $chunks_count,
			'last_indexed' => ! empty( $last_indexed ) ? (string) $last_indexed : null,
		];
	}

	/**
	 * Clears the entire knowledge index (chunks and sources).
	 *
	 * Does NOT delete WordPress posts, pages, products, or FAQs.
	 *
	 * @return bool True if cleared.
	 */
	public function clear_index(): bool {
		// Delete all chunks first
		$this->wpdb->query( "TRUNCATE TABLE {$this->chunks_table}" );
		$this->wpdb->query( "TRUNCATE TABLE {$this->sources_table}" );

		return true;
	}

	/**
	 * Retrieves all indexed sources for batch operations or inspections.
	 *
	 * @param int $limit  Max records.
	 * @param int $offset Offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_sources( int $limit = 500, int $offset = 0 ): array {
		$limit  = max( 1, min( 1000, $limit ) );
		$offset = max( 0, $offset );

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->sources_table} ORDER BY id ASC LIMIT %d OFFSET %d",
			$limit,
			$offset
		);

		$results = $this->wpdb->get_results( $sql, ARRAY_A );
		return is_array( $results ) ? $results : [];
	}
}
