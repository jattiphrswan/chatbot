<?php
/**
 * Lead Repository for Gemini Chat Assistant.
 *
 * Provides database abstraction and CRUD operations for the gca_leads table.
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
 * Class LeadRepository
 */
class LeadRepository {

	public const TABLE_NAME = 'gca_leads';

	/**
	 * @var object|null
	 */
	private $wpdb;
	private string $table_name;

	/**
	 * LeadRepository constructor.
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
	 * Creates a new lead record.
	 *
	 * @param array<string, mixed> $data Lead record data.
	 * @return array<string, mixed>|null Created lead row or null on failure.
	 */
	public function create( array $data ): ?array {
		$public_id       = ! empty( $data['public_id'] ) ? sanitize_text_field( (string) $data['public_id'] ) : ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'lead_', true ) );
		$conversation_id = isset( $data['conversation_id'] ) && $data['conversation_id'] > 0 ? absint( $data['conversation_id'] ) : null;
		$user_id         = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0;
		$name            = isset( $data['name'] ) && is_string( $data['name'] ) ? sanitize_text_field( $data['name'] ) : null;
		$email           = isset( $data['email'] ) && is_string( $data['email'] ) ? sanitize_email( $data['email'] ) : null;
		$phone           = isset( $data['phone'] ) && is_string( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : null;
		$requirement     = isset( $data['requirement'] ) && is_string( $data['requirement'] ) ? sanitize_textarea_field( $data['requirement'] ) : null;
		$status          = isset( $data['status'] ) && in_array( $data['status'], [ 'new', 'contacted', 'closed' ], true ) ? $data['status'] : 'new';
		$now             = current_time( 'mysql', true );

		$insert_data = [
			'public_id'       => $public_id,
			'conversation_id' => $conversation_id,
			'user_id'         => $user_id,
			'name'            => $name,
			'email'           => $email,
			'phone'           => $phone,
			'requirement'     => $requirement,
			'status'          => $status,
			'created_at'      => $now,
			'updated_at'      => $now,
		];

		$formats = [
			'%s', // public_id
			$conversation_id !== null ? '%d' : null, // conversation_id
			'%d', // user_id
			$name !== null ? '%s' : null,
			$email !== null ? '%s' : null,
			$phone !== null ? '%s' : null,
			$requirement !== null ? '%s' : null,
			'%s', // status
			'%s', // created_at
			'%s', // updated_at
		];

		// Filter out null format placeholders while maintaining alignment
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
	 * Retrieves a lead by its internal primary ID.
	 *
	 * @param int $id Internal primary ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( int $id ): ?array {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1",
			$id
		);

		$row = $this->wpdb->get_row( $query, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Retrieves a lead by its public UUID.
	 *
	 * @param string $public_id Public UUID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_public_id( string $public_id ): ?array {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE public_id = %s LIMIT 1",
			$public_id
		);

		$row = $this->wpdb->get_row( $query, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Retrieves a lead by associated internal conversation ID.
	 *
	 * @param int $conversation_id Internal conversation ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_conversation_id( int $conversation_id ): ?array {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE conversation_id = %d LIMIT 1",
			$conversation_id
		);

		$row = $this->wpdb->get_row( $query, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Retrieves a paginated and filtered list of leads for the admin panel.
	 *
	 * @param array<string, mixed> $args Filter, sorting, and pagination arguments.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_admin_list( array $args = [] ): array {
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$status   = ! empty( $args['status'] ) ? sanitize_text_field( (string) $args['status'] ) : null;
		$search   = ! empty( $args['search'] ) ? trim( (string) $args['search'] ) : null;

		$allowed_orders = [ 'created_at', 'name', 'email', 'status', 'updated_at' ];
		$orderby_raw    = (string) ( $args['orderby'] ?? 'created_at' );
		$orderby        = in_array( $orderby_raw, $allowed_orders, true ) ? $orderby_raw : 'created_at';
		$order          = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';

		$where_clauses = [ '1=1' ];
		$where_params  = [];

		if ( ! empty( $status ) && in_array( $status, [ 'new', 'contacted', 'closed' ], true ) ) {
			$where_clauses[] = 'l.status = %s';
			$where_params[]  = $status;
		}

		if ( ! empty( $search ) ) {
			$like            = '%' . $this->wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(l.name LIKE %s OR l.email LIKE %s OR l.phone LIKE %s OR l.public_id LIKE %s)';
			$where_params[]  = $like;
			$where_params[]  = $like;
			$where_params[]  = $like;
			$where_params[]  = $like;
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$conv_table = $this->wpdb->prefix . ConversationRepository::TABLE_NAME;

		$sql = "SELECT l.*, c.public_id AS conversation_public_id, c.status AS conversation_status
				FROM {$this->table_name} l
				LEFT JOIN {$conv_table} c ON l.conversation_id = c.id
				WHERE {$where_sql}
				ORDER BY l.{$orderby} {$order}
				LIMIT %d OFFSET %d";

		$where_params[] = $per_page;
		$where_params[] = $offset;

		$query = $this->wpdb->prepare( $sql, $where_params );
		$rows  = $this->wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Counts total filtered leads for admin pagination.
	 *
	 * @param array<string, mixed> $args Filter arguments.
	 * @return int Total number of matching rows.
	 */
	public function count_admin_list( array $args = [] ): int {
		$status = ! empty( $args['status'] ) ? sanitize_text_field( (string) $args['status'] ) : null;
		$search = ! empty( $args['search'] ) ? trim( (string) $args['search'] ) : null;

		$where_clauses = [ '1=1' ];
		$where_params  = [];

		if ( ! empty( $status ) && in_array( $status, [ 'new', 'contacted', 'closed' ], true ) ) {
			$where_clauses[] = 'status = %s';
			$where_params[]  = $status;
		}

		if ( ! empty( $search ) ) {
			$like            = '%' . $this->wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR public_id LIKE %s)';
			$where_params[]  = $like;
			$where_params[]  = $like;
			$where_params[]  = $like;
			$where_params[]  = $like;
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$sql       = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_sql}";

		if ( ! empty( $where_params ) ) {
			$query = $this->wpdb->prepare( $sql, $where_params );
		} else {
			$query = $sql;
		}

		return (int) $this->wpdb->get_var( $query );
	}

	/**
	 * Counts total leads across all statuses.
	 *
	 * @return int
	 */
	public function count_all(): int {
		$query = "SELECT COUNT(*) FROM {$this->table_name}";
		return (int) $this->wpdb->get_var( $query );
	}

	/**
	 * Updates the status of a lead by public UUID.
	 *
	 * @param string $public_id Public UUID.
	 * @param string $status    Target status ('new', 'contacted', 'closed').
	 * @return bool
	 */
	public function update_status_by_public_id( string $public_id, string $status ): bool {
		if ( ! in_array( $status, [ 'new', 'contacted', 'closed' ], true ) ) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->table_name,
			[
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'public_id' => $public_id ],
			[ '%s', '%s' ],
			[ '%s' ]
		);

		return false !== $result;
	}

	/**
	 * Updates the associated conversation ID of a lead.
	 *
	 * @param int $lead_id         Internal lead ID.
	 * @param int $conversation_id Internal conversation ID.
	 * @return bool
	 */
	public function update_conversation_id( int $lead_id, int $conversation_id ): bool {
		$result = $this->wpdb->update(
			$this->table_name,
			[
				'conversation_id' => $conversation_id,
				'updated_at'      => current_time( 'mysql', true ),
			],
			[ 'id' => $lead_id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Deletes a lead record by public UUID.
	 *
	 * @param string $public_id Public UUID.
	 * @return bool
	 */
	public function delete_by_public_id( string $public_id ): bool {
		$result = $this->wpdb->delete(
			$this->table_name,
			[ 'public_id' => $public_id ],
			[ '%s' ]
		);

		return false !== $result;
	}
}
