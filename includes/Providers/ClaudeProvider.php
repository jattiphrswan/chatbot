<?php
/**
 * Anthropic Claude Provider Adapter (N16 Placeholder).
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
 * Adapter placeholder for Anthropic Claude models (Claude 3.5 Sonnet, Claude 3.5 Haiku).
 * In N16, live outbound HTTP calls are intentionally disabled and deferred.
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
	 * Returns array of supported Anthropic Claude models with normalized metadata.
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
				'id'                 => 'claude-3-5-sonnet-20241022',
				'name'               => 'Claude 3.5 Sonnet',
				'provider'           => self::PROVIDER_ID,
				'capabilities'       => [ 'chat', 'multimodal' ],
				'context_window'     => 200000,
				'max_output_tokens'  => 8192,
				'supports_streaming' => false,
				'supports_images'    => true,
				'supports_tools'     => false,
				'status'             => 'placeholder',
				'description'        => 'High-intelligence model with strong reasoning (placeholder in N16).',
			],
			[
				'id'                 => 'claude-3-5-haiku-20241022',
				'name'               => 'Claude 3.5 Haiku',
				'provider'           => self::PROVIDER_ID,
				'capabilities'       => [ 'chat' ],
				'context_window'     => 200000,
				'max_output_tokens'  => 8192,
				'supports_streaming' => false,
				'supports_images'    => true,
				'supports_tools'     => false,
				'status'             => 'placeholder',
				'description'        => 'Ultra-fast lightweight model for low-latency chat (placeholder in N16).',
			],
		];
	}

	/**
	 * Processes a chat interaction.
	 *
	 * In N16 foundation, network communication is intentionally unconfigured.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Message history.
	 * @param array<string, mixed>                            $options  Request options.
	 * @return ProviderResponse
	 * @throws ProviderException Explicitly indicates provider is not configured yet.
	 */
	public function chat( array $messages, array $options = [] ): ProviderResponse {
		throw ProviderException::not_configured(
			$this->get_id(),
			__( 'Anthropic Claude API client is not configured yet in N16 multi-provider foundation. Live integration is deferred.', 'gemini-chat-assistant' )
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
			__( 'Anthropic Claude connection testing is deferred to a future node.', 'gemini-chat-assistant' )
		);
	}
}
