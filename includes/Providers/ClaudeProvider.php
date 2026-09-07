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
 * Adapter for Anthropic Claude models (Claude 3.5 Sonnet, Haiku, Opus).
 */
class ClaudeProvider implements ProviderInterface {

	public const PROVIDER_ID = 'claude';

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
		return 'Anthropic Claude';
	}

	/**
	 * Returns array of supported Anthropic Claude models with metadata.
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
				'description'       => 'High-intelligence model with strong reasoning, coding, and comprehension.',
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
				'description'       => 'Powerful model for deep analysis and nuanced tasks.',
			],
		];
	}

	/**
	 * Processes a chat interaction.
	 *
	 * In Node 1 foundation, network communication is intentionally unconfigured.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Message history.
	 * @param array<string, mixed>                            $options  Request options.
	 * @return ProviderResponse
	 * @throws ProviderException Explicitly indicates provider is not configured yet.
	 */
	public function chat( array $messages, array $options = [] ): ProviderResponse {
		throw ProviderException::not_configured(
			$this->get_id(),
			'Anthropic Claude API client is not configured yet in Node 1 foundation.'
		);
	}

	/**
	 * Tests connection to Anthropic Claude API.
	 *
	 * @return bool
	 * @throws ProviderException Explicitly indicates provider is not configured yet.
	 */
	public function test_connection(): bool {
		throw ProviderException::not_configured(
			$this->get_id(),
			'Anthropic Claude API connection test is not configured yet in Node 1 foundation.'
		);
	}
}
