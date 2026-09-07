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

	public const ALLOWED_ROLES = [ 'user', 'assistant', 'system' ];

	/**
	 * Returns the table name with dynamic WordPress prefix.
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
	 * @param string      $role            Message role ('user', 'assistant', 'system').
	 * @param string      $content         Message content text.
	 * @param string|null $model           Model identifier.
	 * @param int         $input_tokens    Input tokens count.
	 * @param int         $output_tokens   Output tokens count.
	 * @param int         $latency_ms      API latency in milliseconds.
	 * @return int Created message ID or 0 on failure.
	 * @throws \InvalidArgumentException If role is invalid.
	 */
	public function create(
		int $conversation_id,
		string $role,
		string $content,
		?string $model = null,
		int $input_tokens = 0,
		int $output_tokens = 0,
		int $latency_ms = 0
	): int {
		global $wpdb;

		$role = strtolower( trim( $role ) );
		if ( ! in_array( $role, self::ALLOWED_ROLES, true ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid message role "%s". Allowed roles are: %s', esc_html( $role ), implode( ', ', self::ALLOWED_ROLES ) )
			);
		}

		$table = self::get_table_name();
		$data  = [
			'conversation_id' => absint( $conversation_id ),
			'role'            => $role,
			'content'         => $content,
			'model'           => ! empty( $model ) ? sanitize_text_field( $model ) : null,
			'input_tokens'    => absint( $input_tokens ),
			'output_tokens'   => absint( $output_tokens ),
			'latency_ms'      => absint( $latency_ms ),
			'created_at'      => gmdate( 'Y-m-d H:i:s' ),
		];

		$formats = [ '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s' ];
		$result  = $wpdb->insert( $table, $data, $formats );

		if ( $result ) {
			// Increment conversation message count.
			$conv_repo = new ConversationRepository();
			$conv_repo->increment_message_count( $conversation_id );
			return (int) $wpdb->insert_id;
		}

		return 0;
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
	 * Retrieves messages for a conversation formatted for context window.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $limit           Maximum message turns.
	 * @return array<int, array{role: string, content: string}>
	 */
	public function get_context_messages( int $conversation_id, int $limit = 20 ): array {
		$raw_messages = $this->get_by_conversation_id( $conversation_id, $limit, 'ASC' );
		$context      = [];

		foreach ( $raw_messages as $msg ) {
			$context[] = [
				'role'    => (string) $msg['role'],
				'content' => (string) $msg['content'],
			];
		}

		return $context;
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
