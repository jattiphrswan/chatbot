<?php
/**
 * Abstract Base AI Provider.
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
 * Class AbstractProvider
 *
 * Provides shared configuration, identification, and validation logic for AI providers.
 */
abstract class AbstractProvider implements ProviderInterface {

	/**
	 * Unique provider identifier slug.
	 */
	protected string $id = '';

	/**
	 * Human-readable provider brand name.
	 */
	protected string $name = '';

	/**
	 * Supported models list.
	 *
	 * @var array<int, array{id: string, name: string, context_window: int, max_output_tokens: int, description: string}>
	 */
	protected array $models = [];

	/**
	 * Returns provider unique identifier.
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Returns human-readable provider name.
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Returns supported models catalog.
	 *
	 * @return array<int, array{id: string, name: string, context_window: int, max_output_tokens: int, description: string}>
	 */
	public function get_models(): array {
		return $this->models;
	}

	/**
	 * Checks if this provider is enabled in admin settings.
	 */
	public function is_enabled(): bool {
		return SettingsService::is_provider_enabled( $this->id );
	}

	/**
	 * Checks if this provider has an API key configured (env, constant, or encrypted DB).
	 */
	public function is_configured(): bool {
		return SettingsService::is_provider_configured( $this->id );
	}

	/**
	 * Returns the configured model for this provider or an override if provided.
	 *
	 * @param string|null $override Optional model override.
	 */
	public function get_model( ?string $override = null ): string {
		if ( ! empty( $override ) ) {
			return sanitize_text_field( $override );
		}
		return SettingsService::get_provider_model( $this->id );
	}

	/**
	 * Resolves the plaintext API key for this provider server-side.
	 */
	public function get_api_key(): string {
		return SettingsService::get_provider_api_key( $this->id );
	}

	/**
	 * Validates that the provider is enabled, configured with an API key, and has a valid model.
	 *
	 * @throws ProviderException If configuration is invalid or missing.
	 */
	public function validate_configuration(): void {
		if ( ! $this->is_enabled() ) {
			throw ProviderException::not_configured(
				$this->id,
				sprintf(
					/* translators: %s: Provider name */
					__( '%s provider is disabled in settings.', 'gemini-chat-assistant' ),
					$this->get_name()
				)
			);
		}

		if ( ! $this->is_configured() ) {
			throw ProviderException::not_configured(
				$this->id,
				sprintf(
					/* translators: %s: Provider name */
					__( '%s API key is not configured on the server.', 'gemini-chat-assistant' ),
					$this->get_name()
				)
			);
		}

		$model = $this->get_model();
		if ( empty( $model ) ) {
			throw ProviderException::configuration_error(
				$this->id,
				sprintf(
					/* translators: %s: Provider name */
					__( '%s model is not configured.', 'gemini-chat-assistant' ),
					$this->get_name()
				)
			);
		}
	}
}
