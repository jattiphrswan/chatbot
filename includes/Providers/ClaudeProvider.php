<?php
/**
 * Anthropic Claude Provider Adapter.
 *
 * @package SkyFish\GeminiChat\Providers
 */

namespace SkyFish\GeminiChat\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ClaudeProvider
 *
 * Live adapter for Anthropic Claude models via ClaudeClient and the Messages API.
 */
class ClaudeProvider extends AbstractProvider {

	public const PROVIDER_ID = 'claude';

	private ClaudeClient $client;

	/**
	 * Constructor.
	 *
	 * @param ClaudeClient|null $client Optional ClaudeClient instance.
	 */
	public function __construct( ?ClaudeClient $client = null ) {
		$this->id     = self::PROVIDER_ID;
		$this->name   = 'Anthropic Claude';
		$this->client = $client ?? new ClaudeClient();
		$this->models = ModelRegistry::get_models_for_provider( self::PROVIDER_ID );
	}

	/**
	 * Processes a chat interaction via Anthropic Messages API.
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
	 * Executes a connection test to Anthropic Messages API.
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
	 * @return ClaudeClient
	 */
	public function get_client(): ClaudeClient {
		return $this->client;
	}
}
