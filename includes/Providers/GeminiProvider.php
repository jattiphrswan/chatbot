<?php
/**
 * Google Gemini AI Provider Adapter.
 *
 * @package SkyFish\GeminiChat\Providers
 */

namespace SkyFish\GeminiChat\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GeminiProvider
 *
 * Adapter for Google Gemini models (Flash, Pro).
 */
class GeminiProvider implements ProviderInterface {

	public const PROVIDER_ID = 'gemini';

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
		return 'Google Gemini';
	}

	/**
	 * Returns array of supported Google Gemini models with metadata.
	 *
	 * @return array<int, array{id: string, name: string, context_window: int, max_output_tokens: int, description: string}>
	 */
	public function get_models(): array {
		return [
			[
				'id'                => 'gemini-1.5-flash',
				'name'              => 'Gemini 1.5 Flash',
				'context_window'    => 1048576,
				'max_output_tokens' => 8192,
				'description'       => 'Fast and versatile multimodal model for general conversational tasks.',
			],
			[
				'id'                => 'gemini-1.5-pro',
				'name'              => 'Gemini 1.5 Pro',
				'context_window'    => 2097152,
				'max_output_tokens' => 8192,
				'description'       => 'Highly capable model for complex reasoning, analysis, and large context.',
			],
			[
				'id'                => 'gemini-2.0-flash',
				'name'              => 'Gemini 2.0 Flash',
				'context_window'    => 1048576,
				'max_output_tokens' => 8192,
				'description'       => 'Next-generation low-latency model optimized for speed and multimodal tasks.',
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
			'Google Gemini API client is not configured yet in Node 1 foundation.'
		);
	}

	/**
	 * Tests connection to Google Gemini API.
	 *
	 * @return bool
	 * @throws ProviderException Explicitly indicates provider is not configured yet.
	 */
	public function test_connection(): bool {
		throw ProviderException::not_configured(
			$this->get_id(),
			'Google Gemini API connection test is not configured yet in Node 1 foundation.'
		);
	}
}
