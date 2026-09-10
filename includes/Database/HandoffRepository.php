<?php
/**
 * Handoff Repository for Gemini Chat Assistant.
 *
 * Provides database abstraction and CRUD operations for the gca_handoffs table.
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
 * Class HandoffRepository
 */
class HandoffRepository {

	public const TABLE_NAME = 'gca_handoffs';

	/**
	 * @var object|null
	 */
	private $wpdb;
	private string $table_name;

	/**
	 * HandoffRepository constructor.
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
	 * Creates a new handoff record.
	 *
	 * @param array<string, mixed> $data Handoff record data.
	 * @return array<string, mixed>|null Created handoff row or null on failure.
	 */
	public function create( array $data ): ?array {
		$public_id       = ! empty( $data['public_id'] ) ? sanitize_text_field( (string) $data['public_id'] ) : ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'handoff_', true ) );
		$conversation_id = isset( $data['conversation_id'] ) ? absint( $data['conversation_id'] ) : 0;
		$lead_id         = isset( $data['lead_id'] ) && $data['lead_id'] > 0 ? absint( $data['lead_id'] ) : null;
		$reason          = isset( $data['reason'] ) && is_string( $data['reason'] ) ? sanitize_text_field( $data['reason'] ) : 'customer_request';
		$status          = isset( $data['status'] ) && in_array( $data['status'], [ 'pending', 'assigned', 'resolved', 'cancelled' ], true ) ? $data['status'] : 'pending';
		$now             = current_time( 'mysql', true );

		if ( $conversation_id <= 0 ) {
			return null;
		}

		$insert_data = [
			'public_id'       => $public_id,
			'conversation_id' => $conversation_id,
			'lead_id'         => $lead_id,
			'reason'          => $reason,
			'status'          => $status,
			'created_at'      => $now,
			'updated_at'      => $now,
		];

		$formats = [
			'%s', // public_id
			'%d', // conversation_id
			$lead_id !== null ? '%d' : null, // lead_id
			'%s', // reason
			'%s', // status
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
	 * Retrieves a handoff by internal primary ID.
	 *
	 * @param int $id Internal ID.
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
	 * Retrieves a handoff by public UUID.
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
	 * Retrieves active (pending or assigned) handoff for a given conversation.
	 *
	 * @param int $conversation_id Internal conversation ID.
	 * @return array<string, mixed>|null
	 */
	public function get_active_by_conversation_id( int $conversation_id ): ?array {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE conversation_id = %d AND status IN ('pending', 'assigned') ORDER BY id DESC LIMIT 1",
			$conversation_id
		);

		$row = $this->wpdb->get_row( $query, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Retrieves latest handoff for a given conversation.
	 *
	 * @param int $conversation_id Internal conversation ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_conversation_id( int $conversation_id ): ?array {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE conversation_id = %d ORDER BY id DESC LIMIT 1",
			$conversation_id
		);

		$row = $this->wpdb->get_row( $query, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Retrieves a paginated and filtered list of handoffs for admin management.
	 *
	 * Joins conversation and lead tables to provide context without separate queries.
	 *
	 * @param array<string, mixed> $args Filter, sorting, and pagination arguments.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_admin_list( array $args = [] ): array {
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$status   = ! empty( $args['status'] ) ? sanitize_text_field( (string) $args['status'] ) : null;
		$reason   = ! empty( $args['reason'] ) ? sanitize_text_field( (string) $args['reason'] ) : null;
		$search   = ! empty( $args['search'] ) ? trim( (string) $args['search'] ) : null;

		$allowed_orders = [ 'created_at', 'status', 'reason', 'updated_at' ];
		$orderby_raw    = (string) ( $args['orderby'] ?? 'created_at' );
		$orderby        = in_array( $orderby_raw, $allowed_orders, true ) ? $orderby_raw : 'created_at';
		$order          = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';

		$where_clauses = [ '1=1' ];
		$where_params  = [];

		if ( ! empty( $status ) && in_array( $status, [ 'pending', 'assigned', 'resolved', 'cancelled' ], true ) ) {
			$where_clauses[] = 'h.status = %s';
			$where_params[]  = $status;
		}

		if ( ! empty( $reason ) ) {
			$where_clauses[] = 'h.reason = %s';
			$where_params[]  = $reason;
		}

		if ( ! empty( $search ) ) {
			$like            = '%' . $this->wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(h.public_id LIKE %s OR c.public_id LIKE %s OR l.name LIKE %s OR l.email LIKE %s)';
			$where_params[]  = $like;
			$where_params[]  = $like;
			$where_params[]  = $like;
			$where_params[]  = $like;
		}

		$where_sql   = implode( ' AND ', $where_clauses );
		$conv_table  = $this->wpdb->prefix . ConversationRepository::TABLE_NAME;
		$leads_table = $this->wpdb->prefix . LeadRepository::TABLE_NAME;

		$sql = "SELECT h.*,
				       c.public_id AS conversation_public_id,
				       c.title AS conversation_title,
				       c.status AS conversation_status,
				       l.public_id AS lead_public_id,
				       l.name AS lead_name,
				       l.email AS lead_email,
				       l.phone AS lead_phone
				FROM {$this->table_name} h
				LEFT JOIN {$conv_table} c ON h.conversation_id = c.id
				LEFT JOIN {$leads_table} l ON h.lead_id = l.id
				WHERE {$where_sql}
				ORDER BY h.{$orderby} {$order}
				LIMIT %d OFFSET %d";

		$where_params[] = $per_page;
		$where_params[] = $offset;

		$query = $this->wpdb->prepare( $sql, $where_params );
		$rows  = $this->wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Counts filtered handoff records for admin pagination.
	 *
	 * @param array<string, mixed> $args Filter arguments.
	 * @return int
	 */
	public function count_admin_list( array $args = [] ): int {
		$status = ! empty( $args['status'] ) ? sanitize_text_field( (string) $args['status'] ) : null;
		$reason = ! empty( $args['reason'] ) ? sanitize_text_field( (string) $args['reason'] ) : null;
		$search = ! empty( $args['search'] ) ? trim( (string) $args['search'] ) : null;

		$where_clauses = [ '1=1' ];
		$where_params  = [];

		if ( ! empty( $status ) && in_array( $status, [ 'pending', 'assigned', 'resolved', 'cancelled' ], true ) ) {
			$where_clauses[] = 'h.status = %s';
			$where_params[]  = $status;
		}

		if ( ! empty( $reason ) ) {
			$where_clauses[] = 'h.reason = %s';
			$where_params[]  = $reason;
		}

		$conv_table  = $this->wpdb->prefix . ConversationRepository::TABLE_NAME;
		$leads_table = $this->wpdb->prefix . LeadRepository::TABLE_NAME;

		if ( ! empty( $search ) ) {
			$like            = '%' . $this->wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(h.public_id LIKE %s OR c.public_id LIKE %s OR l.name LIKE %s OR l.email LIKE %s)';
			$where_params[]  = $like;
			$where_params[]  = $like;
			$where_params[]  = $like;
			$where_params[]  = $like;
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$sql       = "SELECT COUNT(*) FROM {$this->table_name} h
		              LEFT JOIN {$conv_table} c ON h.conversation_id = c.id
		              LEFT JOIN {$leads_table} l ON h.lead_id = l.id
		              WHERE {$where_sql}";

		if ( ! empty( $where_params ) ) {
			$query = $this->wpdb->prepare( $sql, $where_params );
		} else {
			$query = $sql;
		}

		return (int) $this->wpdb->get_var( $query );
	}

	/**
	 * Updates the status of a handoff by public UUID.
	 *
	 * @param string $public_id Public UUID.
	 * @param string $status    Target status ('pending', 'assigned', 'resolved', 'cancelled').
	 * @return bool
	 */
	public function update_status( string $public_id, string $status ): bool {
		if ( ! in_array( $status, [ 'pending', 'assigned', 'resolved', 'cancelled' ], true ) ) {
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
	 * Updates the lead ID associated with a handoff record.
	 *
	 * @param int $handoff_id Internal handoff ID.
	 * @param int $lead_id    Internal lead ID.
	 * @return bool
	 */
	public function update_lead_id( int $handoff_id, int $lead_id ): bool {
		$result = $this->wpdb->update(
			$this->table_name,
			[
				'lead_id'    => absint( $lead_id ),
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => absint( $handoff_id ) ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Deletes a handoff record by public UUID.
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
