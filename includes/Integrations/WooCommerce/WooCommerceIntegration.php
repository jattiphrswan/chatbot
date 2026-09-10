<?php
/**
 * WooCommerce Business Integration Implementation.
 *
 * @package SkyFish\GeminiChat\Integrations\WooCommerce
 */

namespace SkyFish\GeminiChat\Integrations\WooCommerce;

use SkyFish\GeminiChat\Integrations\IntegrationInterface;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WooCommerceIntegration
 *
 * Provides read-only WooCommerce business integration: product search, single product lookup, and category browsing.
 * Does NOT provide shopping cart, checkout, order manipulation, or payment operations.
 */
class WooCommerceIntegration implements IntegrationInterface {

	public const ID = 'woocommerce';

	/**
	 * Map of registered action instances.
	 *
	 * @var array<string, \SkyFish\GeminiChat\Integrations\ActionInterface>
	 */
	private array $actions = [];

	/**
	 * WooCommerceIntegration constructor.
	 */
	public function __construct() {
		$this->actions = [
			SearchProductsAction::ID   => new SearchProductsAction(),
			GetProductAction::ID       => new GetProductAction(),
			SearchByCategoryAction::ID => new SearchByCategoryAction(),
		];
	}

	/**
	 * Returns the controlled integration slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * Returns the human-readable display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'WooCommerce Catalog', 'gemini-chat-assistant' );
	}

	/**
	 * Returns a concise description of this integration.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Enables safe read-only product searches, price lookups, stock availability checks, and category browsing.', 'gemini-chat-assistant' );
	}

	/**
	 * Checks whether WooCommerce is active and its core functions exist.
	 *
	 * Never performs external network requests.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return class_exists( 'WooCommerce' ) || function_exists( 'wc_get_products' );
	}

	/**
	 * Checks whether this integration is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		// Available and enabled by default; can be extended with granular option controls in future subnodes.
		return true;
	}

	/**
	 * Returns the array of registered read-only actions.
	 *
	 * @return array<string, \SkyFish\GeminiChat\Integrations\ActionInterface>
	 */
	public function get_actions(): array {
		return $this->actions;
	}
}
