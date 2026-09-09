<?php
/**
 * Provider & Model Selection Service.
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
 * Class ProviderSelectionService
 *
 * Resolves, validates, and exposes effective AI provider and model selections.
 */
class ProviderSelectionService {

	private ?ProviderRegistry $registry;
	private ?SettingsService $settings_service;

	/**
	 * Constructor.
	 *
	 * @param ProviderRegistry|null $registry         Optional ProviderRegistry instance.
	 * @param SettingsService|null  $settings_service Optional SettingsService instance.
	 */
	public function __construct( ?ProviderRegistry $registry = null, ?SettingsService $settings_service = null ) {
		$this->registry         = $registry;
		$this->settings_service = $settings_service;
	}

	/**
	 * Resolves ProviderRegistry instance.
	 *
	 * @return ProviderRegistry
	 */
	public function get_registry(): ProviderRegistry {
		if ( null === $this->registry ) {
			$this->registry = new ProviderRegistry();
			$this->registry->register( new GeminiProvider() );
			$this->registry->register( new OpenAIProvider() );
			$this->registry->register( new ClaudeProvider() );
		}
		return $this->registry;
	}

	/**
	 * Resolves effective provider and model based on admin configuration and optional public request.
	 *
	 * @param string|null $requested_provider Optional visitor-requested provider slug.
	 * @param string|null $requested_model    Optional visitor-requested model ID.
	 * @return array{provider_id: string, model_id: string}
	 * @throws ProviderException If effective provider is unavailable, disabled, or unconfigured.
	 */
	public function resolve_effective_selection( ?string $requested_provider = null, ?string $requested_model = null ): array {
		$allow_provider_selection = SettingsService::allow_public_provider_selection();
		$allow_model_selection    = SettingsService::allow_public_model_selection();
		$default_provider         = SettingsService::get_default_provider();

		// 1. Resolve effective provider.
		$provider_id = $default_provider;
		if ( $allow_provider_selection && ! empty( $requested_provider ) ) {
			$clean_provider = sanitize_key( trim( $requested_provider ) );
			if ( $this->is_provider_usable( $clean_provider ) ) {
				$provider_id = $clean_provider;
			}
		}

		// 2. Enforce provider availability.
		$this->assert_provider_usable( $provider_id );

		// 3. Resolve effective model.
		$model_id = SettingsService::get_provider_model( $provider_id );
		if ( $allow_model_selection && ! empty( $requested_model ) ) {
			$clean_model = sanitize_text_field( trim( $requested_model ) );
			if ( ModelRegistry::has_model( $provider_id, $clean_model ) ) {
				$model_id = $clean_model;
			}
		}

		if ( empty( $model_id ) ) {
			$model_id = ModelRegistry::get_default_model( $provider_id );
		}

		return [
			'provider_id' => $provider_id,
			'model_id'    => $model_id,
		];
	}

	/**
	 * Checks whether a given provider is registered, enabled, and configured with credentials.
	 *
	 * @param string $provider_id Provider slug.
	 * @return bool
	 */
	public function is_provider_usable( string $provider_id ): bool {
		$provider_id = sanitize_key( $provider_id );

		if ( ! in_array( $provider_id, SettingsService::ALLOWED_PROVIDERS, true ) ) {
			return false;
		}

		if ( ! SettingsService::is_provider_enabled( $provider_id ) ) {
			return false;
		}

		if ( ! SettingsService::is_provider_configured( $provider_id ) ) {
			return false;
		}

		$registry = $this->get_registry();
		return $registry->has( $provider_id );
	}

	/**
	 * Asserts that a provider is usable, throwing a ProviderException if not.
	 *
	 * @param string $provider_id Provider slug.
	 * @throws ProviderException
	 */
	public function assert_provider_usable( string $provider_id ): void {
		$provider_id = sanitize_key( $provider_id );

		if ( ! in_array( $provider_id, SettingsService::ALLOWED_PROVIDERS, true ) ) {
			throw ProviderException::provider_unavailable(
				$provider_id,
				sprintf(
					/* translators: %s: Provider slug */
					__( 'AI provider "%s" is not supported.', 'gemini-chat-assistant' ),
					$provider_id
				)
			);
		}

		$registry = $this->get_registry();
		if ( ! $registry->has( $provider_id ) ) {
			throw ProviderException::provider_unavailable(
				$provider_id,
				sprintf(
					/* translators: %s: Provider slug */
					__( 'AI provider "%s" is not registered in the system.', 'gemini-chat-assistant' ),
					$provider_id
				)
			);
		}

		if ( ! SettingsService::is_provider_enabled( $provider_id ) ) {
			throw ProviderException::not_configured(
				$provider_id,
				sprintf(
					/* translators: %s: Provider slug */
					__( 'AI provider "%s" is currently disabled in settings.', 'gemini-chat-assistant' ),
					$provider_id
				)
			);
		}

		if ( ! SettingsService::is_provider_configured( $provider_id ) ) {
			throw ProviderException::not_configured(
				$provider_id,
				sprintf(
					/* translators: %s: Provider slug */
					__( 'AI provider "%s" does not have an API key configured.', 'gemini-chat-assistant' ),
					$provider_id
				)
			);
		}
	}

	/**
	 * Generates safe public provider and model metadata for frontend consumption.
	 *
	 * Excludes all API keys, hashes, tokens, or credential headers.
	 *
	 * @return array<string, mixed>
	 */
	public function get_safe_public_providers_metadata(): array {
		$allow_provider_selection = SettingsService::allow_public_provider_selection();
		$allow_model_selection    = SettingsService::allow_public_model_selection();
		$default_provider         = SettingsService::get_default_provider();

		$registry  = $this->get_registry();
		$providers = [];

		foreach ( SettingsService::ALLOWED_PROVIDERS as $pid ) {
			if ( ! $this->is_provider_usable( $pid ) ) {
				continue;
			}

			$instance = $registry->get( $pid );
			$models   = [];
			foreach ( ModelRegistry::get_models_for_provider( $pid ) as $m ) {
				$models[] = [
					'id'          => $m['id'],
					'name'        => $m['name'],
					'description' => $m['description'],
					'recommended' => ! empty( $m['recommended'] ),
				];
			}

			$providers[] = [
				'id'            => $pid,
				'name'          => $instance->get_name(),
				'default_model' => SettingsService::get_provider_model( $pid ),
				'models'        => $models,
			];
		}

		return [
			'default_provider'         => $default_provider,
			'allow_provider_selection' => $allow_provider_selection,
			'allow_model_selection'    => $allow_model_selection,
			'providers'                => $providers,
		];
	}
}
