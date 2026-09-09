<?php
/**
 * OpenAI Provider Adapter.
 *
 * @package SkyFish\GeminiChat\Providers
 */

namespace SkyFish\GeminiChat\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OpenAIProvider
 *
 * Live adapter for OpenAI models via OpenAIClient and the Responses API.
 */
class OpenAIProvider extends AbstractProvider {

	public const PROVIDER_ID = 'openai';

	private OpenAIClient $client;

	/**
	 * Constructor.
	 *
	 * @param OpenAIClient|null $client Optional OpenAIClient instance.
	 */
	public function __construct( ?OpenAIClient $client = null ) {
		$this->id     = self::PROVIDER_ID;
		$this->name   = 'OpenAI';
		$this->client = $client ?? new OpenAIClient();
		$this->models = ModelRegistry::get_models_for_provider( self::PROVIDER_ID );
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
}
