<?php
/**
 * Anthropic Claude Provider Adapter (Placeholder).
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
 * Adapter placeholder for Anthropic Claude models (Claude 3.5 Sonnet, Claude 3.5 Haiku, Claude 3 Opus).
 * Outbound live calls remain disabled until future Node N20.
 */
class ClaudeProvider extends AbstractProvider {

	public const PROVIDER_ID = 'claude';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id     = self::PROVIDER_ID;
		$this->name   = 'Anthropic Claude';
		$this->models = [
			[
				'id'                => 'claude-3-5-sonnet-20241022',
				'name'              => 'Claude 3.5 Sonnet',
				'context_window'    => 200000,
				'max_output_tokens' => 8192,
				'description'       => 'High-intelligence model with strong reasoning and coding comprehension.',
			],
			[
				'id'                => 'claude-3-5-haiku-20241022',
				'name'              => 'Claude 3.5 Haiku',
				'context_window'    => 200000,
				'max_output_tokens' => 8192,
				'description'       => 'Ultra-fast lightweight model for low-latency chat interactions.',
			],
			[
				'id'                => 'claude-3-opus-20240229',
				'name'              => 'Claude 3 Opus',
				'context_window'    => 200000,
				'max_output_tokens' => 4096,
				'description'       => 'Deep analysis model for complex nuance.',
			],
		];
	}

	/**
	 * Processes a chat interaction.
	 *
	 * Outbound calls are disabled pending future live integration.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Message array.
	 * @param array<string, mixed>                            $options  Runtime options.
	 * @return ProviderResponse
	 * @throws ProviderException
	 */
	public function chat( array $messages, array $options = [] ): ProviderResponse {
		throw ProviderException::not_configured(
			$this->get_id(),
			__( 'Anthropic Claude live chat integration is scheduled for a future node. Outbound calls are disabled.', 'gemini-chat-assistant' )
		);
	}

	/**
	 * Connection test placeholder.
	 *
	 * @return bool
	 * @throws ProviderException
	 */
	public function test_connection(): bool {
		throw ProviderException::not_configured(
			$this->get_id(),
			__( 'Anthropic Claude live connection testing is scheduled for a future node.', 'gemini-chat-assistant' )
		);
	}
}
