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

	/**
	 * Returns the table name with WordPress prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gca_conversations';
	}

	/**
	 * Creates a new conversation record.
	 *
	 * @param string               $session_id Client session identifier.
	 * @param int                  $user_id    WordPress user ID (0 for guests).
	 * @param string               $ip_hash    Anonymized hash of client IP.
	 * @param array<string, mixed> $metadata   Optional contextual metadata.
	 * @return int Created conversation ID or 0 on failure.
	 */
	public function create( string $session_id, int $user_id = 0, string $ip_hash = '', array $metadata = [] ): int {
		global $wpdb;

		$table = self::get_table_name();
		$data  = [
			'session_id' => sanitize_text_field( $session_id ),
			'user_id'    => absint( $user_id ),
			'ip_hash'    => sanitize_text_field( $ip_hash ),
			'status'     => 'active',
			'metadata'   => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		];

		$formats = [ '%s', '%d', '%s', '%s', '%s', '%s', '%s' ];
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

		if ( ! $row ) {
			return null;
		}

		if ( ! empty( $row['metadata'] ) ) {
			$row['metadata'] = json_decode( $row['metadata'], true ) ?: [];
		} else {
			$row['metadata'] = [];
		}

		return $row;
	}

	/**
	 * Retrieves a conversation by session identifier.
	 *
	 * @param string $session_id Unique session ID string.
	 * @return array<string, mixed>|null
	 */
	public function get_by_session_id( string $session_id ): ?array {
		global $wpdb;

		$table = self::get_table_name();
		$query = $wpdb->prepare( "SELECT * FROM {$table} WHERE session_id = %s LIMIT 1", sanitize_text_field( $session_id ) );
		$row   = $wpdb->get_row( $query, ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		if ( ! empty( $row['metadata'] ) ) {
			$row['metadata'] = json_decode( $row['metadata'], true ) ?: [];
		} else {
			$row['metadata'] = [];
		}

		return $row;
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
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Updates conversation metadata.
	 *
	 * @param int                  $id       Conversation ID.
	 * @param array<string, mixed> $metadata New metadata payload.
	 * @return bool
	 */
	public function update_metadata( int $id, array $metadata ): bool {
		global $wpdb;

		$table  = self::get_table_name();
		$result = $wpdb->update(
			$table,
			[
				'metadata'   => wp_json_encode( $metadata ),
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => absint( $id ) ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

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
	 * Deletes a conversation by its session identifier.
	 *
	 * @param string $session_id Client session ID.
	 * @return bool
	 */
	public function delete_by_session_id( string $session_id ): bool {
		global $wpdb;

		$table  = self::get_table_name();
		$result = $wpdb->delete( $table, [ 'session_id' => sanitize_text_field( $session_id ) ], [ '%s' ] );

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
