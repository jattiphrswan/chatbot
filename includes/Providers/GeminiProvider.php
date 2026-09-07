<?php
/**
 * Google Gemini AI Provider Adapter.
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
 * Adapter for Google Gemini models (Flash, Pro) wrapping GeminiClient.
 */
class GeminiProvider implements ProviderInterface {

	public const PROVIDER_ID = 'gemini';

	/**
	 * GeminiClient instance.
	 *
	 * @var GeminiClient
	 */
	private GeminiClient $gemini_client;

	/**
	 * GeminiProvider constructor.
	 *
	 * @param GeminiClient|null $gemini_client Optional Gemini client instance.
	 */
	public function __construct( ?GeminiClient $gemini_client = null ) {
		$this->gemini_client = $gemini_client ?? new GeminiClient();
	}

	/**
	 * Returns machine-readable provider identifier.
	 */
	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	/**
	 * Returns human-readable provider name.
	 */
	public function get_name(): string {
		return 'Google Gemini';
	}

	/**
	 * Returns array of supported Google Gemini models with normalized metadata.
	 *
	 * @return array<int, array{
	 *     id: string,
	 *     name: string,
	 *     provider: string,
	 *     capabilities: string[],
	 *     context_window: int,
	 *     max_output_tokens: int,
	 *     supports_streaming: bool,
	 *     supports_images: bool,
	 *     supports_tools: bool,
	 *     status: string,
	 *     description: string
	 * }>
	 */
	public function get_models(): array {
		return [
			[
				'id'                 => 'gemini-3.8-flash',
				'name'               => 'Gemini 3.8 Flash',
				'provider'           => self::PROVIDER_ID,
				'capabilities'       => [ 'chat', 'multimodal' ],
				'context_window'     => 1048576,
				'max_output_tokens'  => 8192,
				'supports_streaming' => false,
				'supports_images'    => true,
				'supports_tools'     => false,
				'status'             => 'active',
				'description'        => 'High-speed, cost-effective multimodal model for Interactions API (v1).',
			],
		];
	}

	/**
	 * Sends messages to Google Gemini Interactions API and returns normalized ProviderResponse.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Message history.
	 * @param array<string, mixed>                            $options  Request options.
	 * @return ProviderResponse
	 * @throws ProviderException If API request fails or is unconfigured.
	 */
	public function chat( array $messages, array $options = [] ): ProviderResponse {
		$input = '';
		if ( ! empty( $messages ) ) {
			$last_msg = end( $messages );
			if ( is_array( $last_msg ) && isset( $last_msg['content'] ) ) {
				$input = (string) $last_msg['content'];
			} elseif ( is_string( $last_msg ) ) {
				$input = $last_msg;
			}
		}

		if ( empty( $input ) && ! empty( $options['input'] ) ) {
			$input = (string) $options['input'];
		}

		$previous_interaction_id = $options['previous_interaction_id'] ?? null;
		$model                   = $options['model'] ?? null;
		$system_instruction      = $options['system_instruction'] ?? null;

		$client_options = [];
		if ( ! empty( $model ) ) {
			$client_options['model'] = (string) $model;
		}
		if ( null !== $system_instruction ) {
			$client_options['system_instruction'] = (string) $system_instruction;
		}
		if ( isset( $options['generation_config'] ) && is_array( $options['generation_config'] ) ) {
			$client_options['generation_config'] = $options['generation_config'];
		}
		if ( isset( $options['timeout'] ) && is_numeric( $options['timeout'] ) ) {
			$client_options['timeout'] = absint( $options['timeout'] );
		}

		$result = $this->gemini_client->create_interaction(
			$input,
			$previous_interaction_id,
			$client_options
		);

		if ( is_wp_error( $result ) ) {
			throw $this->map_gemini_error( $result );
		}

		$text          = $result['text'] ?? '';
		$model_id      = $result['model'] ?? ( $model ?? 'gemini-3.8-flash' );
		$usage         = $result['usage'] ?? [];
		$input_tokens  = absint( $usage['input_tokens'] ?? 0 );
		$output_tokens = absint( $usage['output_tokens'] ?? 0 );
		$total_tokens  = absint( $usage['total_tokens'] ?? ( $input_tokens + $output_tokens ) );
		$request_id    = ! empty( $result['interaction_id'] ) ? (string) $result['interaction_id'] : null;

		return new ProviderResponse(
			$text,
			$this->get_id(),
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
		if ( ! $this->gemini_client->is_configured() ) {
			throw ProviderException::not_configured(
				$this->get_id(),
				__( 'Gemini API key is not configured on the server.', 'gemini-chat-assistant' )
			);
		}
		return true;
	}

	/**
	 * Accessor to wrapped GeminiClient instance.
	 *
	 * @return GeminiClient
	 */
	public function get_client(): GeminiClient {
		return $this->gemini_client;
	}

	/**
	 * Maps GeminiClient WP_Error to normalized ProviderException.
	 *
	 * @param WP_Error $error WordPress error from GeminiClient.
	 * @return ProviderException
	 */
	private function map_gemini_error( WP_Error $error ): ProviderException {
		$code     = $error->get_error_code();
		$err_data = $error->get_error_data();
		$status   = is_array( $err_data ) && isset( $err_data['status'] ) ? absint( $err_data['status'] ) : 500;
		$msg      = $error->get_error_message();

		switch ( $code ) {
			case 'GCA_GEMINI_NOT_CONFIGURED':
				return new ProviderException(
					self::PROVIDER_ID,
					ProviderException::TYPE_NOT_CONFIGURED,
					$msg,
					500,
					$code
				);

			case 'GCA_GEMINI_AUTH_ERROR':
				return new ProviderException(
					self::PROVIDER_ID,
					ProviderException::TYPE_AUTH_FAILED,
					$msg,
					401,
					$code
				);

			case 'GCA_GEMINI_QUOTA_ERROR':
			case 'GCA_GEMINI_RATE_LIMITED':
				return new ProviderException(
					self::PROVIDER_ID,
					ProviderException::TYPE_RATE_LIMITED,
					$msg,
					429,
					$code
				);

			case 'GCA_GEMINI_TIMEOUT':
				return new ProviderException(
					self::PROVIDER_ID,
					ProviderException::TYPE_TIMEOUT,
					$msg,
					504,
					$code
				);

			case 'GCA_GEMINI_UNAVAILABLE':
				return new ProviderException(
					self::PROVIDER_ID,
					ProviderException::TYPE_PROVIDER_UNAVAILABLE,
					$msg,
					503,
					$code
				);

			case 'GCA_GEMINI_EMPTY_RESPONSE':
			case 'GCA_GEMINI_INVALID_RESPONSE':
				return new ProviderException(
					self::PROVIDER_ID,
					ProviderException::TYPE_MALFORMED_RESPONSE,
					$msg,
					502,
					$code
				);

			case 'GCA_GEMINI_INVALID_REQUEST':
				return new ProviderException(
					self::PROVIDER_ID,
					ProviderException::TYPE_INVALID_REQUEST,
					$msg,
					400,
					$code
				);

			default:
				return new ProviderException(
					self::PROVIDER_ID,
					ProviderException::TYPE_GENERIC,
					$msg,
					$status,
					$code
				);
		}
	}
}
