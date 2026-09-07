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
 * Adapter for OpenAI models (GPT-4o, GPT-4o-mini).
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
	 * Returns array of supported OpenAI models with metadata.
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
				'description'       => 'High-intelligence model for vision and broad reasoning tasks.',
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
			'OpenAI API client is not configured yet in Node 1 foundation.'
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
			'OpenAI API connection test is not configured yet in Node 1 foundation.'
		);
	}
}
