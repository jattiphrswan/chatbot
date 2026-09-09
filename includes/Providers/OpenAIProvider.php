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
	 * Processes a chat interaction.
	 *
	 * In N18, live outbound calls are strictly disabled.
	 *
	 * @throws ProviderException
	 */
	public function chat( array $messages, array $options = [] ): ProviderResponse {
		throw ProviderException::not_configured(
			$this->get_id(),
			__( 'OpenAI live chat integration is scheduled for Node N19. Outbound calls are disabled in N18.', 'gemini-chat-assistant' )
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
			__( 'OpenAI live connection testing is scheduled for Node N19.', 'gemini-chat-assistant' )
		);
	}
}
