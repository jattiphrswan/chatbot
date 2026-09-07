<?php
/**
 * Business Integration Action Interface.
 *
 * @package SkyFish\GeminiChat\Integrations
 */

namespace SkyFish\GeminiChat\Integrations;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface ActionInterface
 *
 * Defines the contract for an executable action provided by a business integration.
 * Actions are concrete PHP application classes with declarative input schemas and risk levels.
 */
interface ActionInterface {

	/**
	 * Action risk classifications.
	 */
	public const RISK_READ     = 'read';
	public const RISK_WRITE    = 'write';
	public const RISK_EXTERNAL = 'external';

	/**
	 * Returns the unique action identifier.
	 *
	 * Must match the controlled pattern: /^[a-z0-9_-]+\.[a-z0-9_-]+$/
	 * Example: 'woocommerce.search_products', 'handoff.email'
	 *
	 * @return string Action identifier slug.
	 */
	public function get_id(): string;

	/**
	 * Returns the human-readable action name.
	 *
	 * @return string Action name.
	 */
	public function get_name(): string;

	/**
	 * Returns a description of what this action does.
	 *
	 * @return string Action description.
	 */
	public function get_description(): string;

	/**
	 * Returns the risk classification of this action.
	 *
	 * Allowed values: 'read', 'write', 'external'.
	 *
	 * @return string Risk level.
	 */
	public function get_risk(): string;

	/**
	 * Returns the declarative input schema defining acceptable arguments.
	 *
	 * Format:
	 * [
	 *   'param_name' => [
	 *     'type'        => 'string'|'integer'|'number'|'boolean'|'enum',
	 *     'required'    => true|false,
	 *     'max_length'  => 200,          // for string
	 *     'min'         => 1,            // for integer/number
	 *     'max'         => 100,          // for integer/number
	 *     'enum'        => ['a', 'b'],   // for enum
	 *     'description' => 'Help text'
	 *   ],
	 *   ...
	 * ]
	 *
	 * @return array<string, array<string, mixed>> Input parameter definitions.
	 */
	public function get_input_schema(): array;

	/**
	 * Checks whether the action can currently execute with the given arguments.
	 *
	 * Validates permissions, system state, and argument applicability.
	 *
	 * @param array $arguments Validated input arguments.
	 * @return bool True if execution is permitted.
	 */
	public function can_execute( array $arguments = [] ): bool;

	/**
	 * Executes the action and returns a normalized ActionResult.
	 *
	 * @param array $arguments Validated input arguments.
	 * @return ActionResult Execution result object.
	 */
	public function execute( array $arguments = [] ): ActionResult;
}
