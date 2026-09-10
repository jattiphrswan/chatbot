<?php
/**
 * Provider Registry and Resolver.
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
 * Class ProviderRegistry
 *
 * Manages registration and retrieval of AI provider adapters.
 */
class ProviderRegistry {

	/**
	 * Map of registered providers indexed by provider ID.
	 *
	 * @var array<string, ProviderInterface>
	 */
	private array $providers = [];

	/**
	 * Registers a provider instance.
	 *
	 * @param ProviderInterface $provider Provider instance to register.
	 * @param bool              $override Whether to override if already registered.
	 * @return void
	 * @throws \InvalidArgumentException If provider ID is empty or duplicate registration is rejected.
	 */
	public function register( ProviderInterface $provider, bool $override = false ): void {
		$id = trim( strtolower( $provider->get_id() ) );

		if ( empty( $id ) ) {
			throw new \InvalidArgumentException( 'Provider ID cannot be empty.' );
		}

		if ( isset( $this->providers[ $id ] ) && ! $override ) {
			throw new \InvalidArgumentException(
				sprintf( 'Provider with ID "%s" is already registered.', esc_html( $id ) )
			);
		}

		$this->providers[ $id ] = $provider;
	}

	/**
	 * Retrieves a registered provider by its unique identifier.
	 *
	 * @param string $provider_id Machine-readable provider ID.
	 * @return ProviderInterface|null Provider instance or null if not found.
	 */
	public function get( string $provider_id ): ?ProviderInterface {
		$id = trim( strtolower( $provider_id ) );

		if ( ! $this->has( $id ) ) {
			return null;
		}

		return $this->providers[ $id ];
	}

	/**
	 * Checks if a provider with the given identifier is registered.
	 *
	 * @param string $provider_id Provider ID.
	 * @return bool
	 */
	public function has( string $provider_id ): bool {
		$id = trim( strtolower( $provider_id ) );
		return isset( $this->providers[ $id ] );
	}

	/**
	 * Alias for has() satisfying is_registered checks.
	 *
	 * @param string $provider_id Provider ID.
	 * @return bool
	 */
	public function is_registered( string $provider_id ): bool {
		return $this->has( $provider_id );
	}

	/**
	 * Checks whether a provider is enabled in SettingsService.
	 *
	 * @param string $provider_id Provider ID.
	 * @return bool
	 */
	public function is_enabled( string $provider_id ): bool {
		return SettingsService::is_provider_enabled( $provider_id );
	}

	/**
	 * Checks whether a provider is configured (has an API key or env credentials).
	 *
	 * @param string $provider_id Provider ID.
	 * @return bool
	 */
	public function is_configured( string $provider_id ): bool {
		return SettingsService::is_provider_configured( $provider_id );
	}

	/**
	 * Returns all registered provider instances.
	 *
	 * @return array<string, ProviderInterface>
	 */
	public function get_all(): array {
		return $this->providers;
	}

	/**
	 * Returns an array of all registered provider IDs.
	 *
	 * @return string[]
	 */
	public function get_registered_ids(): array {
		return array_keys( $this->providers );
	}
}
