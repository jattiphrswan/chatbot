<?php
/**
 * Normalized exception class for AI provider failures.
 *
 * @package SkyFish\GeminiChat\Providers
 */

namespace SkyFish\GeminiChat\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProviderException
 *
 * Provides normalized, safe error encapsulation for AI provider operations.
 */
class ProviderException extends \Exception {

	/**
	 * Error type constants.
	 */
	public const TYPE_AUTH_FAILED         = 'auth_failed';
	public const TYPE_RATE_LIMITED        = 'rate_limited';
	public const TYPE_TIMEOUT             = 'timeout';
	public const TYPE_INVALID_REQUEST     = 'invalid_request';
	public const TYPE_MODEL_UNAVAILABLE   = 'model_unavailable';
	public const TYPE_PROVIDER_UNAVAILABLE= 'provider_unavailable';
	public const TYPE_MALFORMED_RESPONSE  = 'malformed_response';
	public const TYPE_NOT_CONFIGURED      = 'not_configured';
	public const TYPE_GENERIC             = 'generic_error';

	/**
	 * Machine-readable provider ID associated with this exception.
	 */
	private string $provider_id;

	/**
	 * Normalized error type / category code.
	 */
	private string $error_type;

	/**
	 * Safe user-facing message free of secrets or internal endpoints.
	 */
	private string $safe_message;

	/**
	 * Associated HTTP status code if applicable.
	 */
	private int $http_status;

	/**
	 * ProviderException constructor.
	 *
	 * @param string          $provider_id   Provider identifier.
	 * @param string          $error_type    Error classification code.
	 * @param string          $safe_message  Sanitized message safe for logging or display.
	 * @param int             $http_status   HTTP status code.
	 * @param string          $message       Internal diagnostic message.
	 * @param int             $code          Exception code.
	 * @param \Throwable|null $previous      Previous exception.
	 */
	public function __construct(
		string $provider_id,
		string $error_type,
		string $safe_message,
		int $http_status = 500,
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null
	) {
		$internal_message = ! empty( $message ) ? $message : $safe_message;
		parent::__construct( $internal_message, $code, $previous );

		$this->provider_id  = $provider_id;
		$this->error_type   = $error_type;
		$this->safe_message = $safe_message;
		$this->http_status  = $http_status;
	}

	/**
	 * Returns the provider ID.
	 */
	public function get_provider_id(): string {
		return $this->provider_id;
	}

	/**
	 * Returns the normalized error type.
	 */
	public function get_error_type(): string {
		return $this->error_type;
	}

	/**
	 * Returns the safe message.
	 */
	public function get_safe_message(): string {
		return $this->safe_message;
	}

	/**
	 * Returns the HTTP status code.
	 */
	public function get_http_status(): int {
		return $this->http_status;
	}

	/**
	 * Factory helper: Not configured.
	 */
	public static function not_configured( string $provider_id, string $details = '' ): self {
		$msg = ! empty( $details ) ? $details : sprintf( 'Provider "%s" is not configured yet.', $provider_id );
		return new self(
			$provider_id,
			self::TYPE_NOT_CONFIGURED,
			$msg,
			503,
			$msg
		);
	}

	/**
	 * Factory helper: Authentication failure.
	 */
	public static function authentication_failed( string $provider_id, string $details = '' ): self {
		return new self(
			$provider_id,
			self::TYPE_AUTH_FAILED,
			'AI provider authentication failed. Please verify API key settings.',
			401,
			$details
		);
	}

	/**
	 * Factory helper: Rate limit.
	 */
	public static function rate_limited( string $provider_id, string $details = '' ): self {
		return new self(
			$provider_id,
			self::TYPE_RATE_LIMITED,
			'AI provider rate limit reached. Please try again later.',
			429,
			$details
		);
	}

	/**
	 * Factory helper: Timeout.
	 */
	public static function timeout( string $provider_id, string $details = '' ): self {
		return new self(
			$provider_id,
			self::TYPE_TIMEOUT,
			'The request to the AI provider timed out.',
			504,
			$details
		);
	}

	/**
	 * Factory helper: Invalid request.
	 */
	public static function invalid_request( string $provider_id, string $details = '' ): self {
		return new self(
			$provider_id,
			self::TYPE_INVALID_REQUEST,
			'Invalid request sent to AI provider.',
			400,
			$details
		);
	}
}
