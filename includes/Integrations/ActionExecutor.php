<?php
/**
 * Safe Action Executor Pipeline.
 *
 * @package SkyFish\GeminiChat\Integrations
 */

namespace SkyFish\GeminiChat\Integrations;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ActionExecutor
 *
 * Controls safe execution of registered integration actions.
 * Enforces integration availability, enabled status, input schema validation,
 * permission checks, and robust exception trapping without credential leakage.
 */
class ActionExecutor {

	private IntegrationRegistry $registry;

	/**
	 * ActionExecutor constructor.
	 *
	 * @param IntegrationRegistry $registry Integration registry instance.
	 */
	public function __construct( IntegrationRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Executes an action safely by action ID.
	 *
	 * @param string               $action_id  Controlled action identifier (e.g., 'integration.action').
	 * @param array<string, mixed> $arguments  Input parameters.
	 * @return ActionResult Normalized execution outcome.
	 */
	public function execute( string $action_id, array $arguments = [] ): ActionResult {
		// 1. Validate action ID format to prevent injection attacks.
		if ( ! preg_match( '/^[a-z0-9_-]+\.[a-z0-9_-]+$/', $action_id ) ) {
			return ActionResult::failure(
				'INVALID_ACTION_ID',
				__( 'Action identifier format is invalid.', 'gemini-chat-assistant' )
			);
		}

		// 2. Resolve action from registry.
		$action = $this->registry->get_action( $action_id );
		if ( null === $action ) {
			return ActionResult::failure(
				'ACTION_NOT_FOUND',
				sprintf(
					/* translators: %s: action identifier */
					__( 'Action "%s" is not registered.', 'gemini-chat-assistant' ),
					esc_html( $action_id )
				)
			);
		}

		// 3. Resolve host integration and check status.
		$integration_id = $this->registry->get_integration_id_for_action( $action_id );
		$integration    = $integration_id ? $this->registry->get( $integration_id ) : null;

		if ( null === $integration ) {
			return ActionResult::failure(
				'INTEGRATION_NOT_FOUND',
				__( 'The host integration for this action was not found.', 'gemini-chat-assistant' )
			);
		}

		if ( ! $integration->is_available() ) {
			return ActionResult::failure(
				'INTEGRATION_UNAVAILABLE',
				sprintf(
					/* translators: %s: integration name */
					__( 'Integration "%s" is unavailable in this environment.', 'gemini-chat-assistant' ),
					esc_html( $integration->get_name() )
				)
			);
		}

		if ( ! $integration->is_enabled() ) {
			return ActionResult::failure(
				'INTEGRATION_DISABLED',
				sprintf(
					/* translators: %s: integration name */
					__( 'Integration "%s" is currently disabled.', 'gemini-chat-assistant' ),
					esc_html( $integration->get_name() )
				)
			);
		}

		// 4. Validate input arguments against action schema.
		$schema           = $action->get_input_schema();
		$validation_state = ActionValidator::validate( $arguments, $schema );

		if ( ! $validation_state['valid'] ) {
			return ActionResult::failure(
				'INVALID_ACTION_ARGUMENTS',
				__( 'Action arguments failed schema validation.', 'gemini-chat-assistant' ),
				[ 'errors' => $validation_state['errors'] ]
			);
		}

		$sanitized_args = $validation_state['sanitized'];

		// 5. Check action execution policy / permission.
		if ( ! $action->can_execute( $sanitized_args ) ) {
			return ActionResult::failure(
				'ACTION_NOT_ALLOWED',
				__( 'Action execution was denied by policy or environmental state.', 'gemini-chat-assistant' )
			);
		}

		// 6. Execute action with exception safety.
		try {
			$result = $action->execute( $sanitized_args );

			if ( ! ( $result instanceof ActionResult ) ) {
				return ActionResult::failure(
					'MALFORMED_ACTION_RESULT',
					__( 'The action returned an unexpected result structure.', 'gemini-chat-assistant' )
				);
			}

			return $result;
		} catch ( \Throwable $e ) {
			// Catch any throwable to guarantee no uncaught crashes or secret leakages.
			return ActionResult::failure(
				'ACTION_FAILED',
				__( 'The action encountered an unexpected error during execution.', 'gemini-chat-assistant' )
			);
		}
	}
}
