<?php
/**
 * Anthropic Claude Provider Adapter (N18 Placeholder).
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
 * Class ClaudeProvider
 *
 * Adapter placeholder for Anthropic Claude models (Claude 3.5 Sonnet, Claude 3.5 Haiku).
 * In N18, live outbound HTTP calls are strictly prohibited; configuration only.
 */
class ClaudeProvider implements ProviderInterface {

	public const PROVIDER_ID = 'claude';

	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	public function get_name(): string {
		return 'Anthropic Claude';
	}

	/**
	 * Returns supported Anthropic Claude models.
	 *
	 * @return array<int, array{id: string, name: string, context_window: int, max_output_tokens: int, description: string}>
	 */
	public function get_models(): array {
		return [
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
	 * In N18, live outbound calls are strictly disabled.
	 *
	 * @throws ProviderException
	 */
	public function chat( array $messages, array $options = [] ): ProviderResponse {
		throw ProviderException::not_configured(
			$this->get_id(),
			__( 'Anthropic Claude live chat integration is scheduled for a future node. Outbound calls are disabled in N18.', 'gemini-chat-assistant' )
		);
	}

	/**
	 * Connection test placeholder.
	 *
	 * @throws ProviderException
	 */
	public function test_connection(): bool {
		throw ProviderException::not_configured(
			$this->get_id(),
			__( 'Anthropic Claude live connection testing is scheduled for a future node.', 'gemini-chat-assistant' )
		);
	}
}
