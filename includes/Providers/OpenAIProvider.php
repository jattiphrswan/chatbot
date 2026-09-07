<?php
/**
 * OpenAI Provider Adapter (N16 Placeholder).
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
 * Adapter placeholder for OpenAI models (GPT-4o, GPT-4o-mini).
 * In N16, live outbound HTTP calls are intentionally disabled and deferred.
 */
class OpenAIProvider implements ProviderInterface {

	public const PROVIDER_ID = 'openai';

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
		return 'OpenAI';
	}

	/**
	 * Returns array of supported OpenAI models with normalized metadata.
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
				'id'                 => 'gpt-4o',
				'name'               => 'GPT-4o',
				'provider'           => self::PROVIDER_ID,
				'capabilities'       => [ 'chat', 'multimodal' ],
				'context_window'     => 128000,
				'max_output_tokens'  => 4096,
				'supports_streaming' => false,
				'supports_images'    => true,
				'supports_tools'     => false,
				'status'             => 'placeholder',
				'description'        => 'Flagship omni-model for general intelligence (placeholder in N16).',
			],
			[
				'id'                 => 'gpt-4o-mini',
				'name'               => 'GPT-4o mini',
				'provider'           => self::PROVIDER_ID,
				'capabilities'       => [ 'chat' ],
				'context_window'     => 128000,
				'max_output_tokens'  => 4096,
				'supports_streaming' => false,
				'supports_images'    => true,
				'supports_tools'     => false,
				'status'             => 'placeholder',
				'description'        => 'Cost-effective small model for lightweight workflows (placeholder in N16).',
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
			__( 'OpenAI API client is not configured yet in N16 multi-provider foundation. Live integration is deferred.', 'gemini-chat-assistant' )
		);
	}

	/**
	 * Tests connection to OpenAI API.
	 *
	 * @return bool
	 * @throws ProviderException Explicitly indicates provider is not configured yet.
	 */
	public function test_connection(): bool {
		throw ProviderException::not_configured(
			$this->get_id(),
			__( 'OpenAI connection testing is deferred to a future node.', 'gemini-chat-assistant' )
		);
	}
}
