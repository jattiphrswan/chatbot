<?php
/**
 * Business Integration Registry.
 *
 * @package SkyFish\GeminiChat\Integrations
 */

namespace SkyFish\GeminiChat\Integrations;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IntegrationRegistry
 *
 * Centralized registry for managing approved business service integrations and their actions.
 * Enforces strict ID pattern validation, duplicate protection, and extensibility hooks.
 */
class IntegrationRegistry {

	/**
	 * Regex pattern for valid integration IDs.
	 */
	public const ID_PATTERN = '/^[a-z0-9_-]{2,50}$/';

	/**
	 * Registered integrations.
	 *
	 * @var array<string, IntegrationInterface>
	 */
	private array $integrations = [];

	/**
	 * Registered actions indexed by action ID.
	 *
	 * @var array<string, ActionInterface>
	 */
	private array $actions = [];

	/**
	 * Map of action ID to owning integration ID.
	 *
	 * @var array<string, string>
	 */
	private array $action_integration_map = [];

	/**
	 * Tracks whether the initialization hook has been dispatched.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Initializes the registry and triggers the extensible registration action.
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}
		$this->initialized = true;

		/**
		 * Fires to allow WordPress plugins to register business integrations.
		 *
		 * @param IntegrationRegistry $this The integration registry instance.
		 */
		do_action( 'gca_register_integrations', $this );
	}

	/**
	 * Registers a business integration.
	 *
	 * @param IntegrationInterface $integration The integration instance to register.
	 * @return bool True on success, false if invalid or duplicate.
	 */
	public function register( IntegrationInterface $integration ): bool {
		$id = $integration->get_id();

		// Validate ID pattern.
		if ( ! preg_match( self::ID_PATTERN, $id ) ) {
			return false;
		}

		// Prevent duplicate integrations.
		if ( isset( $this->integrations[ $id ] ) ) {
			return false;
		}

		// Validate and register all actions provided by this integration.
		$provided_actions = $integration->get_actions();
		foreach ( $provided_actions as $action_id => $action ) {
			if ( ! ( $action instanceof ActionInterface ) ) {
				return false;
			}

			// Validate action ID slug format: namespace.action_name
			if ( ! preg_match( '/^[a-z0-9_-]+\.[a-z0-9_-]+$/', $action_id ) || $action_id !== $action->get_id() ) {
				return false;
			}

			// Prevent duplicate action IDs across all integrations.
			if ( isset( $this->actions[ $action_id ] ) ) {
				return false;
			}
		}

		// Register integration.
		$this->integrations[ $id ] = $integration;

		// Register actions.
		foreach ( $provided_actions as $action_id => $action ) {
			$this->actions[ $action_id ]                = $action;
			$this->action_integration_map[ $action_id ] = $id;
		}

		return true;
	}

	/**
	 * Retrieves an integration by its ID.
	 *
	 * @param string $id Integration slug.
	 * @return IntegrationInterface|null
	 */
	public function get( string $id ): ?IntegrationInterface {
		return $this->integrations[ $id ] ?? null;
	}

	/**
	 * Checks if an integration is registered.
	 *
	 * @param string $id Integration slug.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->integrations[ $id ] );
	}

	/**
	 * Returns all registered integrations.
	 *
	 * @return array<string, IntegrationInterface>
	 */
	public function get_all(): array {
		return $this->integrations;
	}

	/**
	 * Retrieves an action by its ID.
	 *
	 * @param string $action_id Full action ID (e.g. 'woocommerce.search_products').
	 * @return ActionInterface|null
	 */
	public function get_action( string $action_id ): ?ActionInterface {
		return $this->actions[ $action_id ] ?? null;
	}

	/**
	 * Returns all registered actions.
	 *
	 * @return array<string, ActionInterface>
	 */
	public function get_all_actions(): array {
		return $this->actions;
	}

	/**
	 * Returns the ID of the integration that owns a given action.
	 *
	 * @param string $action_id Action slug.
	 * @return string|null
	 */
	public function get_integration_id_for_action( string $action_id ): ?string {
		return $this->action_integration_map[ $action_id ] ?? null;
	}
}
