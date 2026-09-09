<?php
/**
 * OpenAI Provider Adapter (N18 Placeholder).
 *
 * @package SkyFish\GeminiChat\Providers
 */

namespace SkyFish\GeminiChat\Providers;

use SkyFish\GeminiChat\Admin\SettingsService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OpenAIProvider
 *
 * Adapter placeholder for OpenAI models (GPT-4o, GPT-4o-mini).
 * In N18, live outbound HTTP calls are strictly prohibited; configuration only.
 */
class OpenAIProvider implements ProviderInterface {

	public const PROVIDER_ID = 'openai';

	private OpenAIClient $client;

	/**
	 * Constructor.
	 *
	 * @param OpenAIClient|null $client Optional OpenAIClient instance.
	 */
	public function __construct( ?OpenAIClient $client = null ) {
		$this->client = $client ?? new OpenAIClient();
	}

	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	public function get_name(): string {
		return 'OpenAI';
	}

	/**
	 * Returns supported OpenAI models.
	 *
	 * @return array<int, array{id: string, name: string, context_window: int, max_output_tokens: int, description: string}>
	 */
	public function get_models(): array {
		return [
			[
				'id'                => 'gpt-4o',
				'name'              => 'GPT-4o',
				'context_window'    => 128000,
				'max_output_tokens' => 4096,
				'description'       => 'Flagship omni-model for complex tasks and fast general intelligence.',
			],
			[
				'id'                => 'gpt-4o-mini',
				'name'              => 'GPT-4o mini',
				'context_window'    => 128000,
				'max_output_tokens' => 4096,
				'description'       => 'Cost-effective small model for lightweight conversational workflows.',
			],
			[
				'id'                => 'gpt-4-turbo',
				'name'              => 'GPT-4 Turbo',
				'context_window'    => 128000,
				'max_output_tokens' => 4096,
				'description'       => 'High-intelligence model for broad reasoning tasks.',
			],
		];
	}

	/**
	 * Processes a chat interaction via OpenAI Responses API.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Normalized message array.
	 * @param array<string, mixed>                            $options  Runtime options.
	 * @return ProviderResponse
	 * @throws ProviderException
	 */
	public function chat( array $messages, array $options = [] ): ProviderResponse {
		$this->validate_configuration();

		return $this->client->create_response( $messages, $options );
	}

	/**
	 * Executes a connection test to OpenAI.
	 *
	 * @return bool
	 * @throws ProviderException
	 */
	public function test_connection(): bool {
		$this->validate_configuration();

		return $this->client->test_connection();
	}

	/**
	 * Returns the underlying HTTP client.
	 *
	 * @return OpenAIClient
	 */
	public function get_client(): OpenAIClient {
		return $this->client;
	}

	/**
	 * Validates that the provider is enabled, configured with an API key, and has a valid model.
	 *
	 * @throws ProviderException
	 */
	private function validate_configuration(): void {
		if ( ! SettingsService::is_provider_enabled( self::PROVIDER_ID ) ) {
			throw ProviderException::not_configured(
				self::PROVIDER_ID,
				__( 'OpenAI provider is disabled in settings.', 'gemini-chat-assistant' )
			);
		}

		if ( ! SettingsService::is_provider_configured( self::PROVIDER_ID ) ) {
			throw ProviderException::not_configured(
				self::PROVIDER_ID,
				__( 'OpenAI API key is not configured on the server.', 'gemini-chat-assistant' )
			);
		}

		$model = SettingsService::get_provider_model( self::PROVIDER_ID );
		if ( empty( $model ) ) {
			throw ProviderException::configuration_error(
				self::PROVIDER_ID,
				__( 'OpenAI model is not configured.', 'gemini-chat-assistant' )
			);
		}
	}
}
