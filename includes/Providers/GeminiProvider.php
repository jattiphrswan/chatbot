<?php
/**
 * Google Gemini Provider Adapter.
 *
 * @package SkyFish\GeminiChat\Providers
 */

namespace SkyFish\GeminiChat\Providers;

use SkyFish\GeminiChat\GeminiClient;
use SkyFish\GeminiChat\Admin\SettingsService;
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
class GeminiProvider implements ProviderInterface {

	public const PROVIDER_ID = 'gemini';

	private GeminiClient $gemini_client;

	/**
	 * Constructor.
	 *
	 * @param GeminiClient|null $gemini_client Optional GeminiClient instance.
	 */
	public function __construct( ?GeminiClient $gemini_client = null ) {
		$this->gemini_client = $gemini_client ?? new GeminiClient();
	}

	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	public function get_name(): string {
		return 'Google Gemini';
	}

	/**
	 * Returns supported Gemini models.
	 *
	 * @return array<int, array{id: string, name: string, context_window: int, max_output_tokens: int, description: string}>
	 */
	public function get_models(): array {
		return [
			[
				'id'                => 'gemini-3.8-flash',
				'name'              => 'Gemini 3.8 Flash',
				'context_window'    => 1048576,
				'max_output_tokens' => 8192,
				'description'       => 'High-speed, cost-effective multimodal model for real-time chat interactions.',
			],
			[
				'id'                => 'gemini-3.7-flash',
				'name'              => 'Gemini 3.7 Flash',
				'context_window'    => 1048576,
				'max_output_tokens' => 8192,
				'description'       => 'Previous generation versatile fast model.',
			],
			[
				'id'                => 'gemini-2.5-flash',
				'name'              => 'Gemini 2.5 Flash',
				'context_window'    => 1048576,
				'max_output_tokens' => 8192,
				'description'       => 'Stable production model for conversational tasks.',
			],
		];
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
		if ( ! SettingsService::is_provider_configured( self::PROVIDER_ID ) ) {
			throw ProviderException::not_configured(
				self::PROVIDER_ID,
				__( 'Gemini API key is not configured on the server.', 'gemini-chat-assistant' )
			);
		}

		$user_message = '';
		foreach ( array_reverse( $messages ) as $msg ) {
			if ( isset( $msg['role'] ) && 'user' === $msg['role'] ) {
				$user_message = $msg['content'] ?? '';
				break;
			}
		}

		if ( empty( $user_message ) && ! empty( $messages ) ) {
			$last_msg     = end( $messages );
			$user_message = $last_msg['content'] ?? '';
		}

		$model_id           = ! empty( $options['model'] ) ? sanitize_text_field( $options['model'] ) : SettingsService::get_provider_model( self::PROVIDER_ID );
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
		if ( ! SettingsService::is_provider_configured( self::PROVIDER_ID ) ) {
			throw ProviderException::not_configured(
				self::PROVIDER_ID,
				__( 'Gemini API key is not configured on the server.', 'gemini-chat-assistant' )
			);
		}
		return true;
	}

	public function get_client(): GeminiClient {
		return $this->gemini_client;
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
