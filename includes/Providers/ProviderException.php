<?php
/**
 * Normalized AI Provider Exception.
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
 * Captures provider failures while safeguarding server-side credentials and internal traces.
 */
class ProviderException extends \Exception {

	/**
	 * Error type constants.
	 */
	public const TYPE_AUTH_FAILED          = 'auth_failed';
	public const TYPE_AUTHENTICATION_ERROR = 'authentication_error';
	public const TYPE_RATE_LIMITED         = 'rate_limited';
	public const TYPE_RATE_LIMIT           = 'rate_limit';
	public const TYPE_TIMEOUT              = 'timeout';
	public const TYPE_INVALID_REQUEST      = 'invalid_request';
	public const TYPE_MODEL_UNAVAILABLE    = 'model_unavailable';
	public const TYPE_PROVIDER_UNAVAILABLE = 'provider_unavailable';
	public const TYPE_MALFORMED_RESPONSE   = 'malformed_response';
	public const TYPE_INVALID_RESPONSE     = 'invalid_response';
	public const TYPE_NOT_CONFIGURED       = 'not_configured';
	public const TYPE_CONFIGURATION_ERROR  = 'configuration_error';
	public const TYPE_GENERIC              = 'generic_error';
	public const TYPE_UNKNOWN_ERROR        = 'unknown_error';

	/**
	 * Machine-readable provider ID.
	 *
	 * @var string
	 */
	private string $provider_id;

	/**
	 * Normalized error category.
	 *
	 * @var string
	 */
	private string $error_type;

	/**
	 * Associated HTTP status code.
	 *
	 * @var int
	 */
	private int $http_status;

	/**
	 * Sanitized developer/diagnostic details.
	 *
	 * @var string
	 */
	private string $details;

	/**
	 * Constructor.
	 *
	 * @param string          $provider_id Machine-readable provider identifier.
	 * @param string          $error_type  Normalized error type category.
	 * @param string          $message     User-facing or safe error message.
	 * @param int             $http_status Associated HTTP response status code.
	 * @param string          $details     Sanitized internal details.
	 * @param \Throwable|null $previous    Previous throwable if chained.
	 */
	public function __construct(
		string $provider_id,
		string $error_type = self::TYPE_GENERIC,
		string $message = '',
		int $http_status = 500,
		string $details = '',
		?\Throwable $previous = null
	) {
		$this->provider_id = sanitize_key( $provider_id );
		$this->error_type  = sanitize_key( $error_type );
		$this->http_status = $http_status > 0 ? $http_status : 500;
		$this->details     = $this->strip_secrets( $details );

		$safe_message = $this->strip_secrets( $message );
		if ( empty( $safe_message ) ) {
			$safe_message = $this->get_default_message( $this->error_type );
		}

		parent::__construct( $safe_message, $this->http_status, $previous );
	}

	public function get_provider_id(): string {
		return $this->provider_id;
	}

	public function get_error_type(): string {
		return $this->error_type;
	}

	public function get_http_status(): int {
		return $this->http_status;
	}

	public function get_details(): string {
		return $this->details;
	}

	public function get_safe_message(): string {
		return $this->getMessage();
	}

	/**
	 * Factory helper: Unconfigured provider.
	 */
	public static function not_configured( string $provider_id, string $details = '' ): self {
		return new self(
			$provider_id,
			self::TYPE_NOT_CONFIGURED,
			__( 'AI provider is not configured or enabled.', 'gemini-chat-assistant' ),
			500,
			$details
		);
	}

	/**
	 * Factory helper: Authentication failed.
	 */
	public static function authentication_failed( string $provider_id, string $details = '' ): self {
		return new self(
			$provider_id,
			self::TYPE_AUTH_FAILED,
			__( 'AI provider authentication failed. Please check your credentials.', 'gemini-chat-assistant' ),
			401,
			$details
		);
	}

	/**
	 * Factory helper: Rate limited.
	 */
	public static function rate_limited( string $provider_id, string $details = '' ): self {
		return new self(
			$provider_id,
			self::TYPE_RATE_LIMITED,
			__( 'AI provider rate limit exceeded. Please wait a moment before trying again.', 'gemini-chat-assistant' ),
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
			__( 'AI provider request timed out. Please try again.', 'gemini-chat-assistant' ),
			504,
			$details
		);
	}

	/**
	 * Factory helper: Provider unavailable.
	 */
	public static function provider_unavailable( string $provider_id, string $details = '' ): self {
		return new self(
			$provider_id,
			self::TYPE_PROVIDER_UNAVAILABLE,
			__( 'The requested AI provider is currently unavailable.', 'gemini-chat-assistant' ),
			503,
			$details
		);
	}

	/**
	 * Factory helper: Configuration error.
	 */
	public static function configuration_error( string $provider_id, string $details = '' ): self {
		return new self(
			$provider_id,
			self::TYPE_CONFIGURATION_ERROR,
			__( 'Configuration error encountered for AI provider.', 'gemini-chat-assistant' ),
			500,
			$details
		);
	}

	/**
	 * Returns machine-readable error type/code.
	 *
	 * @return string
	 */
	public function get_error_code(): string {
		return $this->error_type;
	}

	/**
	 * Factory helper: Invalid request.
	 */
	public static function invalid_request( string $provider_id, string $details = '' ): self {
		return new self(
			$provider_id,
			self::TYPE_INVALID_REQUEST,
			__( 'Invalid request sent to AI provider.', 'gemini-chat-assistant' ),
			400,
			$details
		);
	}

	/**
	 * Factory helper: Model unavailable.
	 */
	public static function model_unavailable( string $provider_id, string $details = '' ): self {
		return new self(
			$provider_id,
			self::TYPE_MODEL_UNAVAILABLE,
			__( 'The requested AI model is unavailable or not found.', 'gemini-chat-assistant' ),
			404,
			$details
		);
	}

	/**
	 * Factory helper: Invalid response.
	 */
	public static function invalid_response( string $provider_id, string $details = '' ): self {
		return new self(
			$provider_id,
			self::TYPE_INVALID_RESPONSE,
			__( 'Received an unexpected or invalid response from the AI provider.', 'gemini-chat-assistant' ),
			502,
			$details
		);
	}

	/**
	 * Factory helper: Malformed response.
	 */
	public static function malformed_response( string $provider_id, string $details = '' ): self {
		return new self(
			$provider_id,
			self::TYPE_MALFORMED_RESPONSE,
			__( 'Received an invalid or malformed response from the AI provider.', 'gemini-chat-assistant' ),
			502,
			$details
		);
	}

	/**
	 * Maps category to clean user-facing default description.
	 */
	private function get_default_message( string $error_type ): string {
		switch ( $error_type ) {
			case self::TYPE_AUTH_FAILED:
			case self::TYPE_AUTHENTICATION_ERROR:
				return __( 'AI provider authentication failed.', 'gemini-chat-assistant' );
			case self::TYPE_RATE_LIMITED:
			case self::TYPE_RATE_LIMIT:
				return __( 'AI provider rate limit reached.', 'gemini-chat-assistant' );
			case self::TYPE_TIMEOUT:
				return __( 'AI provider connection timed out.', 'gemini-chat-assistant' );
			case self::TYPE_NOT_CONFIGURED:
			case self::TYPE_CONFIGURATION_ERROR:
				return __( 'AI provider is not configured.', 'gemini-chat-assistant' );
			case self::TYPE_PROVIDER_UNAVAILABLE:
				return __( 'AI provider service is currently unavailable.', 'gemini-chat-assistant' );
			case self::TYPE_MALFORMED_RESPONSE:
			case self::TYPE_INVALID_RESPONSE:
				return __( 'Received an unexpected response from the AI provider.', 'gemini-chat-assistant' );
			default:
				return __( 'An error occurred while communicating with the AI provider.', 'gemini-chat-assistant' );
		}
	}

	/**
	 * Aggressively strips credentials from exception messages.
	 */
	public static function strip_credentials( string $text ): string {
		$patterns = [
			'/AIza[a-zA-Z0-9_\-]{20,}/'    => '[REDACTED_API_KEY]',
			'/sk-ant-[a-zA-Z0-9_\-]{10,}/' => '[REDACTED_API_KEY]',
			'/sk-[a-zA-Z0-9_\-]{10,}/'     => '[REDACTED_API_KEY]',
			'/Bearer\s+[^\s,]+/i'          => 'Bearer [REDACTED]',
		];

		return (string) preg_replace( array_keys( $patterns ), array_values( $patterns ), $text );
	}

	/**
	 * Strips secrets helper for instance usage.
	 */
	private function strip_secrets( string $text ): string {
		return self::strip_credentials( $text );
	}
}
