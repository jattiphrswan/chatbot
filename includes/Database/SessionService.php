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
 * Manages conversation session identifiers, secure session hashing, transient state caching, and cleanup.
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
	 * Generates a cryptographically secure raw session token for the client.
	 *
	 * Format: gca_sess_<32-hex-chars>
	 *
	 * @return string
	 */
	public static function generate_session_token(): string {
		return 'gca_sess_' . bin2hex( wp_generate_password( 16, false, false ) );
	}

	/**
	 * Generates an anonymized SHA-256 HMAC hash of a session token for database lookup.
	 *
	 * The raw session token is NEVER stored in database tables.
	 *
	 * @param string $session_token Raw client session token.
	 * @return string 64-character SHA-256 hex string.
	 */
	public static function hash_session_token( string $session_token ): string {
		$salt = defined( 'NONCE_SALT' ) ? NONCE_SALT : 'gca_default_session_salt';
		return hash_hmac( 'sha256', trim( $session_token ), $salt );
	}

	/**
	 * Generates an anonymized HMAC hash of an IP address.
	 *
	 * @param string $ip Client IP address.
	 * @return string
	 */
	public static function hash_ip( string $ip ): string {
		$salt = defined( 'NONCE_SALT' ) ? NONCE_SALT : 'gca_default_ip_salt';
		return hash_hmac( 'sha256', trim( $ip ), $salt );
	}

	/**
	 * Validates raw session token format.
	 *
	 * @param string $token Candidate session token.
	 * @return bool
	 */
	public static function is_valid_session_token( string $token ): bool {
		return (bool) preg_match( '/^gca_sess_[a-fA-F0-9]{32,64}$/', $token );
	}

	/**
	 * Retrieves or initializes a conversation session.
	 *
	 * @param string|null $session_token Optional existing client session token.
	 * @param int         $user_id       WordPress user ID (0 for guests).
	 * @param string|null $title         Optional title for conversation.
	 * @return array{session_token: string, conversation_id: int, public_id: string, is_new: bool}
	 */
	public function get_or_create_session(
		?string $session_token = null,
		int $user_id = 0,
		?string $title = null
	): array {
		// If a valid session token is provided, check for active conversation by session hash.
		if ( ! empty( $session_token ) && self::is_valid_session_token( $session_token ) ) {
			$session_hash = self::hash_session_token( $session_token );
			$existing     = $this->conversation_repo->get_by_session_hash( $session_hash );

			if ( $existing && 'active' === $existing['status'] ) {
				return [
					'session_token'   => $session_token,
					'conversation_id' => (int) $existing['id'],
					'public_id'       => (string) $existing['public_id'],
					'is_new'          => false,
				];
			}
		}

		// Create new session token and hashed DB conversation.
		$new_token    = self::generate_session_token();
		$session_hash = self::hash_session_token( $new_token );
		$public_id    = ConversationRepository::generate_public_id();
		$conv_id      = $this->conversation_repo->create( $session_hash, $user_id, $title, null, $public_id );

		return [
			'session_token'   => $new_token,
			'conversation_id' => $conv_id,
			'public_id'       => $public_id,
			'is_new'          => true,
		];
	}

	/**
	 * Stores transient session cache data with length-safe key.
	 *
	 * @param string               $session_token Client session token.
	 * @param array<string, mixed> $data          Data to cache (zero secrets).
	 * @param int                  $ttl           Time-to-live in seconds.
	 * @return bool
	 */
	public function set_session_cache( string $session_token, array $data, int $ttl = self::DEFAULT_TTL ): bool {
		if ( ! self::is_valid_session_token( $session_token ) ) {
			return false;
		}

		$key = self::TRANSIENT_PREFIX . md5( $session_token );
		return set_transient( $key, $data, $ttl );
	}

	/**
	 * Retrieves cached session transient data.
	 *
	 * @param string $session_token Client session token.
	 * @return array<string, mixed>|null
	 */
	public function get_session_cache( string $session_token ): ?array {
		if ( ! self::is_valid_session_token( $session_token ) ) {
			return null;
		}

		$key  = self::TRANSIENT_PREFIX . md5( $session_token );
		$data = get_transient( $key );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Retrieves the active Gemini interaction identifier for a session.
	 *
	 * Checks transient cache first, falling back to database conversation.
	 *
	 * @param string $session_token  Client session token.
	 * @param int    $conversation_id Associated conversation ID.
	 * @return string|null
	 */
	public function get_interaction_id( string $session_token, int $conversation_id = 0 ): ?string {
		if ( ! self::is_valid_session_token( $session_token ) ) {
			return null;
		}

		// 1. Check transient cache.
		$cache = $this->get_session_cache( $session_token );
		if ( is_array( $cache ) && ! empty( $cache['interaction_id'] ) ) {
			return (string) $cache['interaction_id'];
		}

		// 2. Fall back to conversation repository.
		if ( $conversation_id > 0 ) {
			return $this->conversation_repo->get_interaction_id( $conversation_id );
		}

		$session_hash = self::hash_session_token( $session_token );
		$conv         = $this->conversation_repo->get_by_session_hash( $session_hash );
		if ( $conv && ! empty( $conv['interaction_id'] ) ) {
			return (string) $conv['interaction_id'];
		}

		return null;
	}

	/**
	 * Stores and synchronizes the active Gemini interaction identifier across transient and database.
	 *
	 * @param string $session_token  Client session token.
	 * @param int    $conversation_id Associated conversation ID.
	 * @param string $interaction_id Gemini interaction ID.
	 * @return bool
	 */
	public function set_interaction_id( string $session_token, int $conversation_id, string $interaction_id ): bool {
		if ( ! self::is_valid_session_token( $session_token ) || empty( $interaction_id ) ) {
			return false;
		}

		// 1. Update transient cache.
		$cache                   = $this->get_session_cache( $session_token ) ?? [];
		$cache['interaction_id'] = $interaction_id;
		$cache['updated_at']     = time();
		$this->set_session_cache( $session_token, $cache );

		// 2. Update database conversation row.
		if ( $conversation_id > 0 ) {
			$this->conversation_repo->update_interaction_id( $conversation_id, $interaction_id );
		}

		return true;
	}

	/**
	 * Clears the stored Gemini interaction identifier across transient and database.
	 *
	 * @param string $session_token  Client session token.
	 * @param int    $conversation_id Associated conversation ID.
	 * @return bool
	 */
	public function clear_interaction_id( string $session_token, int $conversation_id = 0 ): bool {
		if ( ! self::is_valid_session_token( $session_token ) ) {
			return false;
		}

		// 1. Clear in transient.
		$cache = $this->get_session_cache( $session_token );
		if ( is_array( $cache ) ) {
			unset( $cache['interaction_id'] );
			$this->set_session_cache( $session_token, $cache );
		}

		// 2. Clear in database.
		if ( $conversation_id > 0 ) {
			$this->conversation_repo->clear_interaction_id( $conversation_id );
		}

		return true;
	}

	/**
	 * Clears / resets a session.
	 *
	 * Deletes transient cache and marks conversation as closed.
	 *
	 * @param string $session_token Session token to reset.
	 * @return bool
	 */
	public function reset_session( string $session_token ): bool {
		if ( ! self::is_valid_session_token( $session_token ) ) {
			return false;
		}

		// 1. Delete transient cache.
		$transient_key = self::TRANSIENT_PREFIX . md5( $session_token );
		delete_transient( $transient_key );

		// 2. Mark conversation as closed in database and clear interaction.
		$session_hash = self::hash_session_token( $session_token );
		$conv         = $this->conversation_repo->get_by_session_hash( $session_hash );
		if ( $conv ) {
			$this->conversation_repo->clear_interaction_id( (int) $conv['id'] );
			$this->conversation_repo->update_status( (int) $conv['id'], 'closed' );
		}

		return true;
	}
}
