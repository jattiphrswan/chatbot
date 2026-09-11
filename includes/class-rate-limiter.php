<?php
/**
 * Rate Limiter & Abuse Prevention Service.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

use SkyFish\GeminiChat\Admin\SettingsService;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RateLimiter
 *
 * Enforces multi-tier rate limiting using WordPress transients:
 * 1. Primary tier: Session-based rate limit (5-minute and 1-hour windows).
 * 2. Secondary tier: Privacy-safe hashed IP abuse ceiling.
 */
class RateLimiter {

	public const WINDOW_1M             = 60;   // 1 minute in seconds
	public const WINDOW_5M             = 300;  // 5 minutes in seconds
	public const WINDOW_1H             = 3600; // 1 hour in seconds
	public const DEFAULT_1M_LIMIT      = 10;
	public const DEFAULT_5M_LIMIT      = 15;
	public const DEFAULT_1H_LIMIT      = 60;
	public const DEFAULT_MIN_INTERVAL  = 2;
	public const IP_CEILING_MULTIPLIER = 3;

	public const PREFIX_COOL = 'gca_rl_cool_';
	public const PREFIX_S1M  = 'gca_rl_s1m_';
	public const PREFIX_S5   = 'gca_rl_s5_';
	public const PREFIX_S60  = 'gca_rl_s60_';
	public const PREFIX_I1M  = 'gca_rl_i1m_';
	public const PREFIX_I5   = 'gca_rl_i5_';
	public const PREFIX_I60  = 'gca_rl_i60_';

	private SettingsService $settings_service;

	/**
	 * RateLimiter constructor.
	 *
	 * @param SettingsService|null $settings_service Optional settings service.
	 */
	public function __construct( ?SettingsService $settings_service = null ) {
		$this->settings_service = $settings_service ?? SettingsService::get_instance();
	}

	/**
	 * Evaluates and consumes rate limit budget for a given session and client network address.
	 *
	 * Executes before expensive Gemini API transport.
	 *
	 * @param string      $session_id    Validated browser session token.
	 * @param string|null $client_ip     Optional explicit client IP override (for testing).
	 * @param bool        $skip_cooldown Optional flag to skip consecutive message cooldown (e.g. fast-path greetings).
	 * @return true|WP_Error Returns true if permitted, WP_Error with HTTP 429 and Retry-After if exceeded.
	 */
	public function check_and_consume( string $session_id, ?string $client_ip = null, bool $skip_cooldown = false ) {
		$is_enabled = (bool) $this->settings_service->get( 'rate_limit_enabled', true );
		if ( ! $is_enabled ) {
			return true;
		}

		// 1. Resolve limits.
		$min_interval = (float) $this->settings_service->get( 'rate_limit_min_interval', self::DEFAULT_MIN_INTERVAL );
		$limit_1m     = absint( $this->settings_service->get( 'rate_limit_1m', self::DEFAULT_1M_LIMIT ) );
		$limit_1m     = $limit_1m > 0 ? $limit_1m : self::DEFAULT_1M_LIMIT;
		$limit_5m     = absint( $this->settings_service->get( 'rate_limit_5m', 0 ) );
		$limit_1h     = absint( $this->settings_service->get( 'rate_limit_1h', self::DEFAULT_1H_LIMIT ) );
		$limit_1h     = $limit_1h > 0 ? $limit_1h : self::DEFAULT_1H_LIMIT;

		$ip_limit_1m  = $limit_1m * self::IP_CEILING_MULTIPLIER;
		$ip_limit_5m  = $limit_5m > 0 ? $limit_5m * self::IP_CEILING_MULTIPLIER : 0;
		$ip_limit_1h  = $limit_1h * self::IP_CEILING_MULTIPLIER;

		// 2. Generate privacy-safe bounded transient key hashes.
		$sess_hash = substr( hash( 'sha256', trim( $session_id ) ), 0, 24 );
		$raw_ip    = ! empty( $client_ip ) ? $client_ip : self::get_client_ip();
		$salt      = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'gca_rate_salt';
		$ip_hash   = substr( hash_hmac( 'sha256', $raw_ip, $salt ), 0, 24 );

		$key_cool = self::PREFIX_COOL . $sess_hash;
		$key_s1m  = self::PREFIX_S1M . $sess_hash;
		$key_s5   = self::PREFIX_S5 . $sess_hash;
		$key_s60  = self::PREFIX_S60 . $sess_hash;
		$key_i1m  = self::PREFIX_I1M . $ip_hash;
		$key_i5   = self::PREFIX_I5 . $ip_hash;
		$key_i60  = self::PREFIX_I60 . $ip_hash;

		// 3. Check minimum send interval cooldown between consecutive visitor messages.
		if ( ! $skip_cooldown && $min_interval > 0 ) {
			$last_sent = (float) get_transient( $key_cool );
			$now       = microtime( true );
			if ( $last_sent > 0 && ( $now - $last_sent ) < $min_interval ) {
				$cooldown_wait = max( 1, (int) ceil( $min_interval - ( $now - $last_sent ) ) );
				return new WP_Error(
					'CHAT_RATE_LIMITED',
					sprintf(
						/* translators: %d: Seconds to wait */
						__( 'You\'re sending messages too quickly. Please try again in %ds.', 'gemini-chat-assistant' ),
						$cooldown_wait
					),
					[
						'status'      => 429,
						'retry_after' => $cooldown_wait,
					]
				);
			}
		}

		// 4. Check rate limit windows.
		$check_s1m = $this->inspect_window( $key_s1m, $limit_1m );
		$check_s5  = $limit_5m > 0 ? $this->inspect_window( $key_s5, $limit_5m ) : [ 'retry_after' => 0 ];
		$check_s60 = $this->inspect_window( $key_s60, $limit_1h );
		$check_i1m = $this->inspect_window( $key_i1m, $ip_limit_1m );
		$check_i5  = $limit_5m > 0 ? $this->inspect_window( $key_i5, $ip_limit_5m ) : [ 'retry_after' => 0 ];
		$check_i60 = $this->inspect_window( $key_i60, $ip_limit_1h );

		$retry_after = max(
			$check_s1m['retry_after'],
			$check_s5['retry_after'],
			$check_s60['retry_after'],
			$check_i1m['retry_after'],
			$check_i5['retry_after'],
			$check_i60['retry_after']
		);

		if ( $retry_after > 0 ) {
			return new WP_Error(
				'CHAT_RATE_LIMITED',
				sprintf(
					/* translators: %d: Seconds to wait */
					__( 'You\'re sending messages too quickly. Please try again in %ds.', 'gemini-chat-assistant' ),
					$retry_after
				),
				[
					'status'      => 429,
					'retry_after' => $retry_after,
				]
			);
		}

		// 5. Consume budget by incrementing counters and updating cooldown (local greetings do not consume quota).
		if ( ! $skip_cooldown ) {
			if ( $min_interval > 0 ) {
				set_transient( $key_cool, microtime( true ), (int) ceil( $min_interval ) + 1 );
			}
			$this->increment_window( $key_s1m, self::WINDOW_1M );
			if ( $limit_5m > 0 ) {
				$this->increment_window( $key_s5, self::WINDOW_5M );
			}
			$this->increment_window( $key_s60, self::WINDOW_1H );
			$this->increment_window( $key_i1m, self::WINDOW_1M );
			if ( $limit_5m > 0 ) {
				$this->increment_window( $key_i5, self::WINDOW_5M );
			}
			$this->increment_window( $key_i60, self::WINDOW_1H );
		}

		return true;
	}

	/**
	 * Inspects a transient rate window counter.
	 *
	 * @param string $key          Transient cache key.
	 * @param int    $max_requests Maximum allowed requests in window.
	 * @return array{retry_after: int}
	 */
	private function inspect_window( string $key, int $max_requests ): array {
		$data = get_transient( $key );

		if ( is_array( $data ) && isset( $data['count'], $data['expires_at'] ) ) {
			$now = time();
			if ( $now < (int) $data['expires_at'] && (int) $data['count'] >= $max_requests ) {
				$retry_after = max( 1, (int) $data['expires_at'] - $now );
				return [ 'retry_after' => $retry_after ];
			}
		}

		return [ 'retry_after' => 0 ];
	}

	/**
	 * Increments a transient rate window counter atomically within its TTL.
	 *
	 * @param string $key        Transient cache key.
	 * @param int    $window_ttl Window duration in seconds.
	 */
	private function increment_window( string $key, int $window_ttl ): void {
		$data = get_transient( $key );
		$now  = time();

		if ( is_array( $data ) && isset( $data['count'], $data['expires_at'] ) && $now < (int) $data['expires_at'] ) {
			$new_count   = (int) $data['count'] + 1;
			$expires_at  = (int) $data['expires_at'];
			$time_remain = max( 1, $expires_at - $now );
			set_transient(
				$key,
				[
					'count'      => $new_count,
					'expires_at' => $expires_at,
				],
				$time_remain
			);
		} else {
			set_transient(
				$key,
				[
					'count'      => 1,
					'expires_at' => $now + $window_ttl,
				],
				$window_ttl
			);
		}
	}

	/**
	 * Resolves client IP address with proxy filter support.
	 *
	 * @return string Validated IP address.
	 */
	public static function get_client_ip(): string {
		$raw_ip = '';

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$raw_ip = sanitize_text_field( (string) $_SERVER['REMOTE_ADDR'] );
		}

		/**
		 * Filter client IP address to support reverse proxies and CDNs (Cloudflare, Fastly, AWS).
		 *
		 * @param string $raw_ip Direct REMOTE_ADDR value.
		 */
		$filtered_ip = apply_filters( 'gca_client_ip', $raw_ip );

		$valid_ip = filter_var( $filtered_ip, FILTER_VALIDATE_IP );
		return false !== $valid_ip ? $valid_ip : '127.0.0.1';
	}
}
