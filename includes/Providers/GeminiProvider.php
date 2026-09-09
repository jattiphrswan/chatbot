<?php
/**
 * Google Gemini Provider Adapter.
 *
 * @package SkyFish\GeminiChat\Providers
 */

namespace SkyFish\GeminiChat\Providers;

use SkyFish\GeminiChat\GeminiClient;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GeminiProvider
 *
 * Adapter for Google Gemini models via GeminiClient.
 */
class GeminiProvider extends AbstractProvider {

	public const PROVIDER_ID = 'gemini';

	private GeminiClient $gemini_client;

	/**
	 * Constructor.
	 *
	 * @param GeminiClient|null $gemini_client Optional GeminiClient instance.
	 */
	public function __construct( ?GeminiClient $gemini_client = null ) {
		$this->id            = self::PROVIDER_ID;
		$this->name          = 'Google Gemini';
		$this->gemini_client = $gemini_client ?? new GeminiClient();
		$this->models        = ModelRegistry::get_models_for_provider( self::PROVIDER_ID );
	}

	/**
	 * Sends messages to GeminiClient.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Normalized message array.
	 * @param array<string, mixed>                            $options  Runtime options.
	 * @return ProviderResponse
	 * @throws ProviderException On error.
	 */
	public function chat( array $messages, array $options = [] ): ProviderResponse {
		$this->validate_configuration();

		$user_message       = $this->extract_latest_user_message( $messages );
		$model_id           = $this->get_model( $options['model'] ?? null );
		$system_instruction = ! empty( $options['system_instruction'] ) ? (string) $options['system_instruction'] : '';
		$previous_id        = ! empty( $options['previous_interaction_id'] ) ? (string) $options['previous_interaction_id'] : null;

		$result = $this->gemini_client->create_interaction(
			$user_message,
			$previous_id,
			[
				'model'              => $model_id,
				'system_instruction' => $system_instruction,
			]
		);

		if ( is_wp_error( $result ) ) {
			throw $this->map_gemini_error( $result );
		}

		$text          = $result['text'] ?? '';
		$usage         = $result['usage'] ?? [];
		$input_tokens  = absint( $usage['input_tokens'] ?? 0 );
		$output_tokens = absint( $usage['output_tokens'] ?? 0 );
		$total_tokens  = absint( $usage['total_tokens'] ?? ( $input_tokens + $output_tokens ) );
		$request_id    = ! empty( $result['interaction_id'] ) ? (string) $result['interaction_id'] : null;

		return new ProviderResponse(
			$text,
			self::PROVIDER_ID,
			$model_id,
			$input_tokens,
			$output_tokens,
			$total_tokens,
			'stop',
			$request_id,
			[
				'interaction_id' => $request_id ?? '',
				'raw'            => $result['raw'] ?? [],
			]
		);
	}

	/**
	 * Tests connection to Google Gemini API.
	 *
	 * @return bool
	 * @throws ProviderException If API key is unconfigured.
	 */
	public function test_connection(): bool {
		$this->validate_configuration();
		return true;
	}

	public function get_client(): GeminiClient {
		return $this->gemini_client;
	}

	/**
	 * Extracts the most recent user message from normalized message history.
	 *
	 * @param array<int, array{role?: string, content?: string}> $messages Message array.
	 * @return string
	 */
	private function extract_latest_user_message( array $messages ): string {
		foreach ( array_reverse( $messages ) as $msg ) {
			if ( isset( $msg['role'] ) && 'user' === $msg['role'] ) {
				return $msg['content'] ?? '';
			}
		}

		if ( ! empty( $messages ) ) {
			$last_msg = end( $messages );
			return $last_msg['content'] ?? '';
		}

		return '';
	}

	/**
	 * Maps GeminiClient WP_Error to normalized ProviderException.
	 */
	private function map_gemini_error( WP_Error $error ): ProviderException {
		$code     = $error->get_error_code();
		$err_data = $error->get_error_data();
		$status   = is_array( $err_data ) && isset( $err_data['status'] ) ? absint( $err_data['status'] ) : 500;
		$msg      = $error->get_error_message();

		switch ( $code ) {
			case 'GCA_GEMINI_NOT_CONFIGURED':
				return new ProviderException( self::PROVIDER_ID, ProviderException::TYPE_NOT_CONFIGURED, $msg, 500, $code );
			case 'GCA_GEMINI_AUTH_ERROR':
				return new ProviderException( self::PROVIDER_ID, ProviderException::TYPE_AUTH_FAILED, $msg, 401, $code );
			case 'GCA_GEMINI_QUOTA_ERROR':
			case 'GCA_GEMINI_RATE_LIMITED':
				return new ProviderException( self::PROVIDER_ID, ProviderException::TYPE_RATE_LIMITED, $msg, 429, $code );
			case 'GCA_GEMINI_TIMEOUT':
				return new ProviderException( self::PROVIDER_ID, ProviderException::TYPE_TIMEOUT, $msg, 504, $code );
			case 'GCA_GEMINI_UNAVAILABLE':
				return new ProviderException( self::PROVIDER_ID, ProviderException::TYPE_PROVIDER_UNAVAILABLE, $msg, 503, $code );
			case 'GCA_GEMINI_EMPTY_RESPONSE':
			case 'GCA_GEMINI_INVALID_RESPONSE':
				return new ProviderException( self::PROVIDER_ID, ProviderException::TYPE_MALFORMED_RESPONSE, $msg, 502, $code );
			case 'GCA_GEMINI_INVALID_REQUEST':
				return new ProviderException( self::PROVIDER_ID, ProviderException::TYPE_INVALID_REQUEST, $msg, 400, $code );
			default:
				return new ProviderException( self::PROVIDER_ID, ProviderException::TYPE_GENERIC, $msg, $status, $code );
		}
	}
}
