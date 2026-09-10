<?php
/**
 * Business Integration Interface.
 *
 * @package SkyFish\GeminiChat\Integrations
 */

namespace SkyFish\GeminiChat\Integrations;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface IntegrationInterface
 *
 * Defines the operational contract for external and WordPress-native business service integrations.
 * This contract represents business capabilities (WooCommerce, notifications, handoff, CRM)
 * and must never be used for AI model provider abstractions.
 */
interface IntegrationInterface {

	/**
	 * Returns the unique, machine-readable identifier for this integration.
	 *
	 * Must match the pattern: /^[a-z0-9_-]{2,50}$/
	 *
	 * @return string Controlled integration slug (e.g., 'woocommerce', 'email_notifications').
	 */
	public function get_id(): string;

	/**
	 * Returns the human-readable display name of this integration.
	 *
	 * @return string Integration name.
	 */
	public function get_name(): string;

	/**
	 * Returns a concise description of what this integration provides.
	 *
	 * @return string Integration description.
	 */
	public function get_description(): string;

	/**
	 * Checks whether the environment satisfies this integration's preconditions.
	 *
	 * For example, WooCommerce is installed and active, or the mail subsystem is configured.
	 * Availability must never perform external network requests.
	 *
	 * @return bool True if available in the current WordPress environment.
	 */
	public function is_available(): bool;

	/**
	 * Checks whether this integration is administratively enabled.
	 *
	 * @return bool True if enabled by configuration.
	 */
	public function is_enabled(): bool;

	/**
	 * Returns an associative array of registered actions provided by this integration.
	 *
	 * @return array<string, ActionInterface> Array of actions keyed by action ID.
	 */
	public function get_actions(): array;
}
