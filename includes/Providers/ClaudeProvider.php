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
		$this->models = [
			[
				'id'                => 'claude-3-5-haiku-20241022',
				'name'              => 'Claude 3.5 Haiku',
				'context_window'    => 200000,
				'max_output_tokens' => 8192,
				'description'       => 'Ultra-fast lightweight model for low-latency chat interactions.',
			],
			[
				'id'                => 'claude-3-5-sonnet-20241022',
				'name'              => 'Claude 3.5 Sonnet',
				'context_window'    => 200000,
				'max_output_tokens' => 8192,
				'description'       => 'High-intelligence model with strong reasoning and coding comprehension.',
			],
			[
				'id'                => 'claude-3-opus-20240229',
				'name'              => 'Claude 3 Opus',
				'context_window'    => 200000,
				'max_output_tokens' => 4096,
				'description'       => 'Deep analysis model for complex nuance.',
			],
			[
				'id'                => 'claude-sonnet-4-6',
				'name'              => 'Claude Sonnet 4.6',
				'context_window'    => 200000,
				'max_output_tokens' => 8192,
				'description'       => 'Next-generation Sonnet foundation model.',
			],
			[
				'id'                => 'claude-opus-4-8',
				'name'              => 'Claude Opus 4.8',
				'context_window'    => 200000,
				'max_output_tokens' => 8192,
				'description'       => 'Next-generation Opus frontier model.',
			],
			[
				'id'                => 'claude-haiku-4-5-20251001',
				'name'              => 'Claude Haiku 4.5',
				'context_window'    => 200000,
				'max_output_tokens' => 8192,
				'description'       => 'Next-generation Haiku high-efficiency model.',
			],
		];
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
