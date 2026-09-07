<?php
/**
 * Create Human Handoff Action.
 *
 * @package SkyFish\GeminiChat\Integrations\Handoff
 */

namespace SkyFish\GeminiChat\Integrations\Handoff;

use SkyFish\GeminiChat\Integrations\ActionInterface;
use SkyFish\GeminiChat\Integrations\ActionResult;
use SkyFish\GeminiChat\Handoff\HandoffService;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CreateHandoffAction
 */
class CreateHandoffAction implements ActionInterface {

	public const ID = 'handoff.create';

	private HandoffService $handoff_service;

	/**
	 * CreateHandoffAction constructor.
	 *
	 * @param HandoffService|null $handoff_service Optional handoff service.
	 */
	public function __construct( ?HandoffService $handoff_service = null ) {
		$this->handoff_service = $handoff_service ?? new HandoffService();
	}

	/**
	 * Returns action identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * Returns human-readable action name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Create Handoff Request', 'gemini-chat-assistant' );
	}

	/**
	 * Returns action description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Safely records an escalation or human assistance request linked to the active conversation.', 'gemini-chat-assistant' );
	}

	/**
	 * Returns risk classification.
	 *
	 * @return string
	 */
	public function get_risk(): string {
		return self::RISK_WRITE;
	}

	/**
	 * Returns input parameter schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_input_schema(): array {
		return [
			'conversation_id' => [
				'type'        => 'integer',
				'required'    => true,
				'min'         => 1,
				'description' => __( 'Internal database conversation ID.', 'gemini-chat-assistant' ),
			],
			'reason'          => [
				'type'        => 'enum',
				'required'    => false,
				'enum'        => HandoffService::ALLOWED_REASONS,
				'description' => __( 'Escalation reason classification.', 'gemini-chat-assistant' ),
			],
			'lead_id'         => [
				'type'        => 'integer',
				'required'    => false,
				'min'         => 1,
				'description' => __( 'Optional associated lead database ID.', 'gemini-chat-assistant' ),
			],
		];
	}

	/**
	 * Checks whether the action can execute.
	 *
	 * @param array $arguments Input arguments.
	 * @return bool
	 */
	public function can_execute( array $arguments = [] ): bool {
		return isset( $arguments['conversation_id'] ) && (int) $arguments['conversation_id'] > 0;
	}

	/**
	 * Executes the action.
	 *
	 * @param array $arguments Validated input arguments.
	 * @return ActionResult
	 */
	public function execute( array $arguments = [] ): ActionResult {
		$conversation_id = absint( $arguments['conversation_id'] ?? 0 );
		$reason          = ! empty( $arguments['reason'] ) ? sanitize_text_field( (string) $arguments['reason'] ) : HandoffService::REASON_CUSTOMER_REQUEST;
		$lead_id         = ! empty( $arguments['lead_id'] ) ? absint( $arguments['lead_id'] ) : null;

		if ( $conversation_id <= 0 ) {
			return ActionResult::failure(
				'INVALID_CONVERSATION_ID',
				__( 'A valid conversation ID is required.', 'gemini-chat-assistant' )
			);
		}

		$result = $this->handoff_service->create_handoff( $conversation_id, $reason, $lead_id );

		if ( is_wp_error( $result ) ) {
			return ActionResult::failure(
				$result->get_error_code(),
				$result->get_error_message()
			);
		}

		return ActionResult::success( [
			'handoff_id'      => $result['public_id'],
			'status'          => $result['status'],
			'reason'          => $result['reason'],
			'conversation_id' => $result['conversation_id'],
			'created_at'      => $result['created_at'],
		] );
	}
}
