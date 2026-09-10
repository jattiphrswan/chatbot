<?php
/**
 * Conversation Database Repository.
 *
 * @package SkyFish\GeminiChat\Database
 */

namespace SkyFish\GeminiChat\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConversationRepository
 *
 * Provides CRUD operations for gca_conversations table.
 */
class ConversationRepository {

	public const TABLE_NAME = 'gca_conversations';

	/**
	 * Returns the table name with dynamic WordPress prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return ( $wpdb && isset( $wpdb->prefix ) ) ? $wpdb->prefix . self::TABLE_NAME : 'wp_' . self::TABLE_NAME;
	}

	/**
	 * Generates a cryptographically secure public identifier.
	 *
	 * @return string
	 */
	public static function generate_public_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff )
		);
	}

	/**
	 * Creates a new conversation record.
	 *
	 * @param string      $session_hash   Anonymized SHA-256 hash of client session token.
	 * @param int         $user_id        WordPress user ID (0 for guests).
	 * @param string|null $title          Optional conversation title.
	 * @param string|null $interaction_id Optional interaction identifier.
	 * @param string|null $public_id      Optional explicit public ID.
	 * @return int Created conversation ID or 0 on failure.
	 */
	public function create(
		string $session_hash,
		int $user_id = 0,
		?string $title = null,
		?string $interaction_id = null,
		?string $public_id = null
	): int {
		global $wpdb;

		$table      = self::get_table_name();
		$utc_now    = gmdate( 'Y-m-d H:i:s' );
		$pub_id     = ! empty( $public_id ) ? sanitize_text_field( $public_id ) : self::generate_public_id();

		$data = [
			'public_id'       => $pub_id,
			'user_id'         => absint( $user_id ),
			'session_hash'    => sanitize_text_field( $session_hash ),
			'title'           => ! empty( $title ) ? sanitize_text_field( $title ) : null,
			'status'          => 'active',
			'interaction_id'  => ! empty( $interaction_id ) ? sanitize_text_field( $interaction_id ) : null,
			'message_count'   => 0,
			'created_at'      => $utc_now,
			'updated_at'      => $utc_now,
			'last_message_at' => null,
		];

		$formats = [ '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ];
		$result  = $wpdb->insert( $table, $data, $formats );

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Retrieves a conversation by its primary ID.
	 *
	 * @param int $id Conversation primary key.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( int $id ): ?array {
		global $wpdb;

		$table = self::get_table_name();
		$query = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $id ) );
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return $row ?: null;
	}

	/**
	 * Retrieves a conversation by its public UUID.
	 *
	 * @param string $public_id Public UUID string.
	 * @return array<string, mixed>|null
	 */
	public function get_by_public_id( string $public_id ): ?array {
		global $wpdb;

		$table = self::get_table_name();
		$query = $wpdb->prepare( "SELECT * FROM {$table} WHERE public_id = %s LIMIT 1", sanitize_text_field( $public_id ) );
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return $row ?: null;
	}

	/**
	 * Retrieves the active conversation by session hash.
	 *
	 * @param string $session_hash Anonymized session hash.
	 * @return array<string, mixed>|null
	 */
	public function get_by_session_hash( string $session_hash ): ?array {
		global $wpdb;

		$table = self::get_table_name();
		$query = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE session_hash = %s AND status = 'active' ORDER BY updated_at DESC LIMIT 1",
			sanitize_text_field( $session_hash )
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return $row ?: null;
	}

	/**
	 * Updates the status of a conversation.
	 *
	 * @param int    $id     Conversation ID.
	 * @param string $status New status ('active', 'closed', 'archived').
	 * @return bool
	 */
	public function update_status( int $id, string $status ): bool {
		global $wpdb;

		$table  = self::get_table_name();
		$result = $wpdb->update(
			$table,
			[
				'status'     => sanitize_text_field( $status ),
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => absint( $id ) ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Updates the Gemini interaction identifier for conversation memory.
	 *
	 * @param int    $id             Conversation ID.
	 * @param string $interaction_id Gemini interaction ID.
	 * @return bool
	 */
	public function update_interaction_id( int $id, string $interaction_id ): bool {
		global $wpdb;

		$table  = self::get_table_name();
		$result = $wpdb->update(
			$table,
			[
				'interaction_id' => sanitize_text_field( $interaction_id ),
				'updated_at'     => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => absint( $id ) ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Retrieves the stored Gemini interaction identifier for a conversation.
	 *
	 * @param int $id Conversation ID.
	 * @return string|null
	 */
	public function get_interaction_id( int $id ): ?string {
		global $wpdb;

		$table = self::get_table_name();
		$query = $wpdb->prepare( "SELECT interaction_id FROM {$table} WHERE id = %d LIMIT 1", absint( $id ) );
		$val   = $wpdb->get_var( $query );

		return ! empty( $val ) ? (string) $val : null;
	}

	/**
	 * Clears the Gemini interaction identifier for a conversation.
	 *
	 * @param int $id Conversation ID.
	 * @return bool
	 */
	public function clear_interaction_id( int $id ): bool {
		global $wpdb;

		$table  = self::get_table_name();
		$result = $wpdb->update(
			$table,
			[
				'interaction_id' => null,
				'updated_at'     => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => absint( $id ) ],
			[ null, '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Increments message count and updates last_message_at timestamp.
	 *
	 * @param int $id Conversation ID.
	 * @return bool
	 */
	public function increment_message_count( int $id ): bool {
		global $wpdb;

		$table   = self::get_table_name();
		$utc_now = gmdate( 'Y-m-d H:i:s' );
		$query   = $wpdb->prepare(
			"UPDATE {$table} SET message_count = message_count + 1, updated_at = %s, last_message_at = %s WHERE id = %d",
			$utc_now,
			$utc_now,
			absint( $id )
		);

		$result = $wpdb->query( $query );
		return false !== $result;
	}

	/**
	 * Deletes a conversation by ID.
	 *
	 * @param int $id Conversation ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$table  = self::get_table_name();
		$result = $wpdb->delete( $table, [ 'id' => absint( $id ) ], [ '%d' ] );

		return false !== $result && $result > 0;
	}

	/**
	 * Whitelisted orderby columns for admin queries.
	 */
	public const ALLOWED_ORDERBY = [ 'updated_at', 'created_at', 'last_message_at', 'message_count', 'title', 'status' ];

	/**
	 * Whitelisted status values.
	 */
	public const ALLOWED_STATUSES = [ 'active', 'closed' ];

	/**
	 * Retrieves paginated, filtered conversations for admin view.
	 *
	 * @param array<string, mixed> $args Filter and pagination arguments.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_admin_list( array $args = [] ): array {
		global $wpdb;

		$table    = self::get_table_name();
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$status   = ! empty( $args['status'] ) && in_array( strtolower( (string) $args['status'] ), [ 'active', 'closed' ], true ) ? strtolower( (string) $args['status'] ) : null;
		$search   = ! empty( $args['search'] ) ? trim( (string) $args['search'] ) : null;
		$orderby  = ! empty( $args['orderby'] ) && in_array( strtolower( (string) $args['orderby'] ), self::ALLOWED_ORDERBY, true ) ? strtolower( (string) $args['orderby'] ) : 'updated_at';
		$order    = ! empty( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$where_clauses = [];
		$params        = [];

		if ( $status ) {
			$where_clauses[] = 'status = %s';
			$params[]        = $status;
		}

		if ( $search ) {
			$search_like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(public_id LIKE %s OR title LIKE %s)';
			$params[]        = $search_like;
			$params[]        = $search_like;
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		$sql = "SELECT id, public_id, user_id, title, status, message_count, created_at, updated_at, last_message_at FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		$query = $wpdb->prepare( $sql, ...$params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Counts matching conversations for admin pagination.
	 *
	 * @param array<string, mixed> $args Filter arguments.
	 * @return int
	 */
	public function count_admin_list( array $args = [] ): int {
		global $wpdb;

		$table  = self::get_table_name();
		$status = ! empty( $args['status'] ) && in_array( strtolower( (string) $args['status'] ), [ 'active', 'closed' ], true ) ? strtolower( (string) $args['status'] ) : null;
		$search = ! empty( $args['search'] ) ? trim( (string) $args['search'] ) : null;

		$where_clauses = [];
		$params        = [];

		if ( $status ) {
			$where_clauses[] = 'status = %s';
			$params[]        = $status;
		}

		if ( $search ) {
			$search_like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(public_id LIKE %s OR title LIKE %s)';
			$params[]        = $search_like;
			$params[]        = $search_like;
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		$sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";

		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( $sql, ...$params );
			$count = $wpdb->get_var( $query );
		} else {
			$count = $wpdb->get_var( $sql );
		}

		return absint( $count );
	}

	/**
	 * Returns total count of all conversations in the database.
	 *
	 * @return int
	 */
	public function count_all(): int {
		global $wpdb;
		$table = self::get_table_name();
		return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );
	}

	/**
	 * Updates status by public UUID.
	 *
	 * @param string $public_id Public UUID.
	 * @param string $status    New status.
	 * @return bool
	 */
	public function update_status_by_public_id( string $public_id, string $status ): bool {
		if ( ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			return false;
		}

		global $wpdb;

		$table  = self::get_table_name();
		$result = $wpdb->update(
			$table,
			[
				'status'     => sanitize_text_field( $status ),
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'public_id' => sanitize_text_field( $public_id ) ],
			[ '%s', '%s' ],
			[ '%s' ]
		);

		return false !== $result;
	}

	/**
	 * Deletes a conversation and all its associated messages by public UUID.
	 *
	 * @param string $public_id Public UUID.
	 * @return bool
	 */
	public function delete_by_public_id( string $public_id ): bool {
		global $wpdb;

		$conv = $this->get_by_public_id( $public_id );
		if ( ! $conv ) {
			return false;
		}

		$conv_id = (int) $conv['id'];

		// Delete child messages first (Application cascade).
		$msg_repo = new MessageRepository();
		$msg_repo->delete_by_conversation_id( $conv_id );

		// Delete parent conversation.
		$table  = self::get_table_name();
		$result = $wpdb->delete( $table, [ 'id' => $conv_id ], [ '%d' ] );

		return false !== $result && $result > 0;
	}

	/**
	 * Prunes conversations older than a specified number of days.
	 *
	 * @param int $days Retention threshold in days.
	 * @return int Number of rows pruned.
	 */
	public function prune_older_than( int $days ): int {
		global $wpdb;

		if ( $days < 1 ) {
			return 0;
		}

		$table        = self::get_table_name();
		$cutoff_date  = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$query        = $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff_date );
		$rows_deleted = $wpdb->query( $query );

		return false !== $rows_deleted ? (int) $rows_deleted : 0;
	}
}
