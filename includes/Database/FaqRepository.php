<?php
/**
 * FAQ Repository for Gemini Chat Assistant.
 *
 * Provides database abstraction and CRUD operations for the gca_faqs table.
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
 * Class FaqRepository
 *
 * Encapsulates all database interactions with the gca_faqs table.
 */
class FaqRepository {

	public const TABLE_NAME = 'gca_faqs';

	/**
	 * @var object|null
	 */
	private $wpdb;
	private string $table_name;

	/**
	 * FaqRepository constructor.
	 *
	 * @param object|null $wpdb Optional WordPress database abstraction instance.
	 */
	public function __construct( $wpdb = null ) {
		if ( null === $wpdb ) {
			global $wpdb;
			$this->wpdb = $wpdb;
		} else {
			$this->wpdb = $wpdb;
		}
		$this->table_name = ( $this->wpdb && isset( $this->wpdb->prefix ) ) ? $this->wpdb->prefix . self::TABLE_NAME : 'wp_' . self::TABLE_NAME;
	}

	/**
	 * Creates a new FAQ record.
	 *
	 * @param array<string, mixed> $data FAQ record data.
	 * @return array<string, mixed>|null Created FAQ row or null on failure.
	 */
	public function create( array $data ): ?array {
		$public_id    = ! empty( $data['public_id'] ) ? sanitize_text_field( (string) $data['public_id'] ) : ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'faq_', true ) );
		$question     = isset( $data['question'] ) ? sanitize_text_field( (string) $data['question'] ) : '';
		$answer       = isset( $data['answer'] ) ? sanitize_textarea_field( (string) $data['answer'] ) : '';
		$category     = isset( $data['category'] ) && is_string( $data['category'] ) && '' !== trim( $data['category'] ) ? sanitize_text_field( trim( $data['category'] ) ) : null;
		$is_active    = ! empty( $data['is_active'] ) ? 1 : 0;
		$show_on_home = ! empty( $data['show_on_home'] ) ? 1 : 0;
		$sort_order   = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;
		$now          = current_time( 'mysql', true );

		if ( '' === $question || '' === $answer ) {
			return null;
		}

		$insert_data = [
			'public_id'    => $public_id,
			'question'     => $question,
			'answer'       => $answer,
			'category'     => $category,
			'is_active'    => $is_active,
			'show_on_home' => $show_on_home,
			'sort_order'   => $sort_order,
			'created_at'   => $now,
			'updated_at'   => $now,
		];

		$formats = [
			'%s', // public_id
			'%s', // question
			'%s', // answer
			$category !== null ? '%s' : null,
			'%d', // is_active
			'%d', // show_on_home
			'%d', // sort_order
			'%s', // created_at
			'%s', // updated_at
		];

		$filtered_data    = [];
		$filtered_formats = [];
		$format_keys      = array_keys( $insert_data );

		foreach ( $format_keys as $idx => $key ) {
			$val = $insert_data[ $key ];
			if ( null !== $val ) {
				$filtered_data[ $key ] = $val;
				$filtered_formats[]    = $formats[ $idx ] ?? '%s';
			}
		}

		$result = $this->wpdb->insert( $this->table_name, $filtered_data, $filtered_formats );

		if ( false === $result ) {
			return null;
		}

		$insert_id = (int) $this->wpdb->insert_id;
		return $this->get_by_id( $insert_id );
	}

	/**
	 * Updates an existing FAQ record.
	 *
	 * @param int                  $id   Database ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool True if successfully updated.
	 */
	public function update( int $id, array $data ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		$update_data = [];
		$formats     = [];

		if ( isset( $data['question'] ) ) {
			$update_data['question'] = sanitize_text_field( (string) $data['question'] );
			$formats[]               = '%s';
		}

		if ( isset( $data['answer'] ) ) {
			$update_data['answer'] = sanitize_textarea_field( (string) $data['answer'] );
			$formats[]             = '%s';
		}

		if ( array_key_exists( 'category', $data ) ) {
			$update_data['category'] = ! empty( $data['category'] ) ? sanitize_text_field( (string) $data['category'] ) : null;
			$formats[]               = $update_data['category'] !== null ? '%s' : null;
		}

		if ( isset( $data['is_active'] ) ) {
			$update_data['is_active'] = ! empty( $data['is_active'] ) ? 1 : 0;
			$formats[]                = '%d';
		}

		if ( isset( $data['show_on_home'] ) ) {
			$update_data['show_on_home'] = ! empty( $data['show_on_home'] ) ? 1 : 0;
			$formats[]                   = '%d';
		}

		if ( isset( $data['sort_order'] ) ) {
			$update_data['sort_order'] = (int) $data['sort_order'];
			$formats[]                 = '%d';
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$update_data['updated_at'] = current_time( 'mysql', true );
		$formats[]                 = '%s';

		// Clean nulls in formats
		$filtered_data    = [];
		$filtered_formats = [];
		$keys             = array_keys( $update_data );

		foreach ( $keys as $idx => $key ) {
			$val = $update_data[ $key ];
			$filtered_data[ $key ] = $val;
			$filtered_formats[]    = $formats[ $idx ] ?? '%s';
		}

		$result = $this->wpdb->update(
			$this->table_name,
			$filtered_data,
			[ 'id' => $id ],
			$filtered_formats,
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Deletes an FAQ by numeric ID.
	 *
	 * @param int $id Database ID.
	 * @return bool True if deleted.
	 */
	public function delete( int $id ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		$result = $this->wpdb->delete(
			$this->table_name,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Deletes an FAQ by public ID.
	 *
	 * @param string $public_id Public UUID identifier.
	 * @return bool True if deleted.
	 */
	public function delete_by_public_id( string $public_id ): bool {
		$sanitized = sanitize_text_field( $public_id );
		if ( empty( $sanitized ) ) {
			return false;
		}

		$result = $this->wpdb->delete(
			$this->table_name,
			[ 'public_id' => $sanitized ],
			[ '%s' ]
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Retrieves an FAQ by database ID.
	 *
	 * @param int $id Database ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1",
			$id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Retrieves an FAQ by public UUID.
	 *
	 * @param string $public_id Public UUID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_public_id( string $public_id ): ?array {
		$sanitized = sanitize_text_field( $public_id );
		if ( empty( $sanitized ) ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE public_id = %s LIMIT 1",
			$sanitized
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Retrieves active FAQs configured to show on Chatbot Home.
	 *
	 * @param int $limit Maximum number to fetch.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_home_faqs( int $limit = 6 ): array {
		$limit = max( 1, min( 20, $limit ) );

		$sql = $this->wpdb->prepare(
			"SELECT id, public_id, question, answer, category, sort_order
			 FROM {$this->table_name}
			 WHERE is_active = 1 AND show_on_home = 1
			 ORDER BY sort_order ASC, id ASC
			 LIMIT %d",
			$limit
		);

		$results = $this->wpdb->get_results( $sql, ARRAY_A );
		return is_array( $results ) ? $results : [];
	}

	/**
	 * Retrieves all active FAQs (e.g. for knowledge indexer).
	 *
	 * @param int $limit Maximum records to fetch.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_active_faqs( int $limit = 500 ): array {
		$limit = max( 1, min( 1000, $limit ) );

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name}
			 WHERE is_active = 1
			 ORDER BY sort_order ASC, id ASC
			 LIMIT %d",
			$limit
		);

		$results = $this->wpdb->get_results( $sql, ARRAY_A );
		return is_array( $results ) ? $results : [];
	}

	/**
	 * Paginated retrieval with optional search and category filters.
	 *
	 * @param int                  $page     Page number (1-based).
	 * @param int                  $per_page Items per page.
	 * @param array<string, mixed> $args     Filter arguments (search, category, is_active, show_on_home).
	 * @return array{items: array<int, array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
	 */
	public function paginate( int $page = 1, int $per_page = 20, array $args = [] ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where_clauses = [ '1=1' ];
		$params        = [];

		if ( ! empty( $args['search'] ) && is_string( $args['search'] ) ) {
			$search_like     = '%' . $this->wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where_clauses[] = '(question LIKE %s OR answer LIKE %s OR category LIKE %s)';
			$params[]        = $search_like;
			$params[]        = $search_like;
			$params[]        = $search_like;
		}

		if ( ! empty( $args['category'] ) && is_string( $args['category'] ) ) {
			$where_clauses[] = 'category = %s';
			$params[]        = sanitize_text_field( $args['category'] );
		}

		if ( isset( $args['is_active'] ) && '' !== $args['is_active'] ) {
			$where_clauses[] = 'is_active = %d';
			$params[]        = ! empty( $args['is_active'] ) ? 1 : 0;
		}

		if ( isset( $args['show_on_home'] ) && '' !== $args['show_on_home'] ) {
			$where_clauses[] = 'show_on_home = %d';
			$params[]        = ! empty( $args['show_on_home'] ) ? 1 : 0;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Count query
		$count_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_sql}";
		$total     = ! empty( $params )
			? (int) $this->wpdb->get_var( $this->wpdb->prepare( $count_sql, ...$params ) )
			: (int) $this->wpdb->get_var( $count_sql );

		// Items query
		$items_sql = "SELECT * FROM {$this->table_name} WHERE {$where_sql} ORDER BY sort_order ASC, id DESC LIMIT %d OFFSET %d";
		$item_params = array_merge( $params, [ $per_page, $offset ] );

		$items = $this->wpdb->get_results(
			$this->wpdb->prepare( $items_sql, ...$item_params ),
			ARRAY_A
		);

		$pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;

		return [
			'items'    => is_array( $items ) ? $items : [],
			'total'    => $total,
			'pages'    => $pages,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}

	/**
	 * Searches FAQs for matching keywords.
	 *
	 * @param string $query Search query.
	 * @param int    $limit Max results.
	 * @return array<int, array<string, mixed>>
	 */
	public function search( string $query, int $limit = 20 ): array {
		$sanitized = sanitize_text_field( $query );
		if ( empty( $sanitized ) ) {
			return [];
		}

		$limit       = max( 1, min( 100, $limit ) );
		$search_like = '%' . $this->wpdb->esc_like( $sanitized ) . '%';

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name}
			 WHERE (question LIKE %s OR answer LIKE %s OR category LIKE %s)
			 ORDER BY sort_order ASC, id DESC
			 LIMIT %d",
			$search_like,
			$search_like,
			$search_like,
			$limit
		);

		$results = $this->wpdb->get_results( $sql, ARRAY_A );
		return is_array( $results ) ? $results : [];
	}

	/**
	 * Returns total count of FAQ records matching criteria.
	 *
	 * @param array<string, mixed> $args Criteria.
	 * @return int
	 */
	public function count( array $args = [] ): int {
		$where_clauses = [ '1=1' ];
		$params        = [];

		if ( isset( $args['is_active'] ) ) {
			$where_clauses[] = 'is_active = %d';
			$params[]        = ! empty( $args['is_active'] ) ? 1 : 0;
		}

		if ( isset( $args['show_on_home'] ) ) {
			$where_clauses[] = 'show_on_home = %d';
			$params[]        = ! empty( $args['show_on_home'] ) ? 1 : 0;
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$sql       = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_sql}";

		return ! empty( $params )
			? (int) $this->wpdb->get_var( $this->wpdb->prepare( $sql, ...$params ) )
			: (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Retrieves distinct categories across all FAQs.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		$sql     = "SELECT DISTINCT category FROM {$this->table_name} WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
		$results = $this->wpdb->get_col( $sql );
		return is_array( $results ) ? array_map( 'strval', $results ) : [];
	}
}
