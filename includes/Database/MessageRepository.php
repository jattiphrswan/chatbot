<?php
/**
 * Message Database Repository.
 *
 * @package SkyFish\GeminiChat\Database
 */

namespace SkyFish\GeminiChat\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MessageRepository
 *
 * Provides CRUD operations for gca_messages table.
 */
class MessageRepository {

	/**
	 * Returns the table name with WordPress prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gca_messages';
	}

	/**
	 * Inserts a message into the database.
	 *
	 * @param int         $conversation_id Associated conversation ID.
	 * @param string      $role            Message role ('user', 'model', 'system').
	 * @param string      $content         Message content text.
	 * @param int         $tokens          Tokens consumed if known.
	 * @param string|null $finish_reason   Finish reason code if known.
	 * @return int Created message ID or 0 on failure.
	 */
	public function create(
		int $conversation_id,
		string $role,
		string $content,
		int $tokens = 0,
		?string $finish_reason = null
	): int {
		global $wpdb;

		$table = self::get_table_name();
		$data  = [
			'conversation_id' => absint( $conversation_id ),
			'role'            => sanitize_text_field( $role ),
			'content'         => $content,
			'tokens'          => absint( $tokens ),
			'finish_reason'   => ! empty( $finish_reason ) ? sanitize_text_field( $finish_reason ) : null,
			'created_at'      => current_time( 'mysql' ),
		];

		$formats = [ '%d', '%s', '%s', '%d', '%s', '%s' ];
		$result  = $wpdb->insert( $table, $data, $formats );

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Retrieves a message by its ID.
	 *
	 * @param int $id Message primary key.
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
	 * Retrieves messages for a specific conversation.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param int    $limit           Maximum number of messages.
	 * @param string $order           Order ('ASC' or 'DESC').
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_conversation_id( int $conversation_id, int $limit = 50, string $order = 'ASC' ): array {
		global $wpdb;

		$table = self::get_table_name();
		$order = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';
		$limit = max( 1, min( 200, absint( $limit ) ) );

		$query = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY created_at {$order}, id {$order} LIMIT %d",
			absint( $conversation_id ),
			$limit
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Retrieves recent messages by session identifier.
	 *
	 * @param string $session_id Client session ID.
	 * @param int    $limit      Max messages.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_messages_for_session( string $session_id, int $limit = 20 ): array {
		global $wpdb;

		$conv_table = ConversationRepository::get_table_name();
		$msg_table  = self::get_table_name();
		$limit      = max( 1, min( 100, absint( $limit ) ) );

		$query = $wpdb->prepare(
			"SELECT m.* FROM {$msg_table} m
			INNER JOIN {$conv_table} c ON m.conversation_id = c.id
			WHERE c.session_id = %s
			ORDER BY m.created_at ASC, m.id ASC
			LIMIT %d",
			sanitize_text_field( $session_id ),
			$limit
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Deletes all messages belonging to a conversation.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return int Number of messages deleted.
	 */
	public function delete_by_conversation_id( int $conversation_id ): int {
		global $wpdb;

		$table  = self::get_table_name();
		$result = $wpdb->delete( $table, [ 'conversation_id' => absint( $conversation_id ) ], [ '%d' ] );

		return false !== $result ? (int) $result : 0;
	}

	/**
	 * Deletes a single message by ID.
	 *
	 * @param int $id Message ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$table  = self::get_table_name();
		$result = $wpdb->delete( $table, [ 'id' => absint( $id ) ], [ '%d' ] );

		return false !== $result && $result > 0;
	}
}
