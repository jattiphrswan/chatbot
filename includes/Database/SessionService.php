<?php
/**
 * Session Service & State Management.
 *
 * @package SkyFish\GeminiChat\Database
 */

namespace SkyFish\GeminiChat\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SessionService
 *
 * Manages conversation session identifiers, transient state caching, and cleanup.
 */
class SessionService {

	public const TRANSIENT_PREFIX = 'gca_sess_';
	public const DEFAULT_TTL      = 3600; // 1 hour

	private ConversationRepository $conversation_repo;
	private MessageRepository $message_repo;

	/**
	 * SessionService constructor.
	 *
	 * @param ConversationRepository|null $conversation_repo Optional repository.
	 * @param MessageRepository|null      $message_repo      Optional repository.
	 */
	public function __construct(
		?ConversationRepository $conversation_repo = null,
		?MessageRepository $message_repo = null
	) {
		$this->conversation_repo = $conversation_repo ?? new ConversationRepository();
		$this->message_repo      = $message_repo ?? new MessageRepository();
	}

	/**
	 * Generates a cryptographically secure session identifier.
	 *
	 * Format: gca_sess_<32-hex-chars>
	 *
	 * @return string
	 */
	public static function generate_session_id(): string {
		return 'gca_sess_' . bin2hex( wp_generate_password( 16, false, false ) );
	}

	/**
	 * Generates an anonymized HMAC hash of an IP address.
	 *
	 * @param string $ip Client IP address.
	 * @return string
	 */
	public static function hash_ip( string $ip ): string {
		$salt = defined( 'NONCE_SALT' ) ? NONCE_SALT : 'gca_default_salt';
		return hash_hmac( 'sha256', trim( $ip ), $salt );
	}

	/**
	 * Validates session ID format.
	 *
	 * @param string $session_id Candidate session ID.
	 * @return bool
	 */
	public static function is_valid_session_id( string $session_id ): bool {
		return (bool) preg_match( '/^gca_sess_[a-fA-F0-9]{32,64}$/', $session_id );
	}

	/**
	 * Retrieves or initializes a conversation session.
	 *
	 * @param string|null          $session_id Optional existing session ID.
	 * @param int                  $user_id    WordPress user ID (0 for guests).
	 * @param string               $ip_address Client IP address.
	 * @param array<string, mixed> $metadata   Optional initial metadata.
	 * @return array{session_id: string, conversation_id: int, is_new: bool}
	 */
	public function get_or_create_session(
		?string $session_id = null,
		int $user_id = 0,
		string $ip_address = '',
		array $metadata = []
	): array {
		$ip_hash = ! empty( $ip_address ) ? self::hash_ip( $ip_address ) : '';

		// If a valid session ID is provided, look up existing conversation.
		if ( ! empty( $session_id ) && self::is_valid_session_id( $session_id ) ) {
			$existing = $this->conversation_repo->get_by_session_id( $session_id );
			if ( $existing && 'active' === $existing['status'] ) {
				return [
					'session_id'      => $session_id,
					'conversation_id' => (int) $existing['id'],
					'is_new'          => false,
				];
			}
		}

		// Create a new session.
		$new_session_id = self::generate_session_id();
		$conv_id        = $this->conversation_repo->create( $new_session_id, $user_id, $ip_hash, $metadata );

		return [
			'session_id'      => $new_session_id,
			'conversation_id' => $conv_id,
			'is_new'          => true,
		];
	}

	/**
	 * Stores transient session cache data.
	 *
	 * @param string               $session_id Client session ID.
	 * @param array<string, mixed> $data       Data to cache.
	 * @param int                  $ttl        Time-to-live in seconds.
	 * @return bool
	 */
	public function set_session_cache( string $session_id, array $data, int $ttl = self::DEFAULT_TTL ): bool {
		if ( ! self::is_valid_session_id( $session_id ) ) {
			return false;
		}

		$key = self::TRANSIENT_PREFIX . md5( $session_id );
		return set_transient( $key, $data, $ttl );
	}

	/**
	 * Retrieves cached session transient data.
	 *
	 * @param string $session_id Client session ID.
	 * @return array<string, mixed>|null
	 */
	public function get_session_cache( string $session_id ): ?array {
		if ( ! self::is_valid_session_id( $session_id ) ) {
			return null;
		}

		$key  = self::TRANSIENT_PREFIX . md5( $session_id );
		$data = get_transient( $key );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Clears / resets a session.
	 *
	 * Deletes transient cache and marks conversation as closed or archived.
	 *
	 * @param string $session_id Session to reset.
	 * @return bool
	 */
	public function reset_session( string $session_id ): bool {
		if ( ! self::is_valid_session_id( $session_id ) ) {
			return false;
		}

		// 1. Delete transient cache.
		$transient_key = self::TRANSIENT_PREFIX . md5( $session_id );
		delete_transient( $transient_key );

		// 2. Mark conversation as closed.
		$conv = $this->conversation_repo->get_by_session_id( $session_id );
		if ( $conv ) {
			$this->conversation_repo->update_status( (int) $conv['id'], 'closed' );
		}

		return true;
	}
}
