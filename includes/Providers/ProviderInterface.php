<?php
/**
 * AI Provider Interface.
 *
 * @package SkyFish\GeminiChat\Providers
 */

namespace SkyFish\GeminiChat\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface ProviderInterface
 *
 * Contract defining standard methods for all AI provider integrations.
 */
interface ProviderInterface {

	/**
	 * Returns machine-readable provider identifier (e.g. 'gemini', 'openai', 'claude').
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Returns human-readable provider name (e.g. 'Google Gemini', 'OpenAI', 'Anthropic Claude').
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Returns array of supported models with normalized metadata.
	 *
	 * @return array<int, array{id: string, name: string, context_window?: int, max_output_tokens?: int, description?: string}>
	 */
	public function get_models(): array;

	/**
	 * Sends messages to the AI provider and returns a normalized response.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Normalized message array.
	 * @param array<string, mixed>                            $options  Runtime options (model, temperature, max_tokens, etc.).
	 * @return ProviderResponse
	 * @throws ProviderException On configuration, authentication, rate limit, or communication failure.
	 */
	public function chat( array $messages, array $options = [] ): ProviderResponse;

	/**
	 * Tests connection and authentication with the provider.
	 *
	 * @return bool
	 * @throws ProviderException If connection test encounters a fatal error.
	 */
	public function test_connection(): bool;
}
