<?php
/**
 * Send Human Handoff Notification Action.
 *
 * @package SkyFish\GeminiChat\Integrations\Handoff
 */

namespace SkyFish\GeminiChat\Integrations\Handoff;

use SkyFish\GeminiChat\Integrations\ActionInterface;
use SkyFish\GeminiChat\Integrations\ActionResult;
use SkyFish\GeminiChat\Notifications\NotificationService;
use SkyFish\GeminiChat\Database\HandoffRepository;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendHandoffNotificationAction
 *
 * Integrates email notification sending into the Business Integration Framework as a RISK_EXTERNAL action.
 */
class SendHandoffNotificationAction implements ActionInterface {

	public const ID = 'handoff.send_notification';

	private NotificationService $notification_service;
	private HandoffRepository $handoff_repo;

	/**
	 * SendHandoffNotificationAction constructor.
	 *
	 * @param NotificationService|null $notification_service Optional notification service.
	 * @param HandoffRepository|null   $handoff_repo         Optional handoff repository.
	 */
	public function __construct(
		?NotificationService $notification_service = null,
		?HandoffRepository $handoff_repo = null
	) {
		$this->notification_service = $notification_service ?? new NotificationService();
		$this->handoff_repo         = $handoff_repo ?? new HandoffRepository();
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
		return __( 'Send Handoff Email Notification', 'gemini-chat-assistant' );
	}

	/**
	 * Returns action description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Dispatches an internal email alert to configured team members for an escalated handoff request via wp_mail().', 'gemini-chat-assistant' );
	}

	/**
	 * Returns risk classification.
	 *
	 * @return string
	 */
	public function get_risk(): string {
		return self::RISK_EXTERNAL;
	}

	/**
	 * Returns input parameter schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_input_schema(): array {
		return [
			'handoff_id' => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'Public UUID or internal ID of the handoff record.', 'gemini-chat-assistant' ),
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
		return ! empty( $arguments['handoff_id'] ) && $this->notification_service->is_enabled();
	}

	/**
	 * Executes the notification action.
	 *
	 * @param array $arguments Validated input arguments.
	 * @return ActionResult
	 */
	public function execute( array $arguments = [] ): ActionResult {
		$raw_id = trim( (string) ( $arguments['handoff_id'] ?? '' ) );

		if ( empty( $raw_id ) ) {
			return ActionResult::failure(
				'MISSING_HANDOFF_ID',
				__( 'A handoff identifier is required.', 'gemini-chat-assistant' )
			);
		}

		// Look up by public UUID or numeric ID.
		$handoff = is_numeric( $raw_id )
			? $this->handoff_repo->get_by_id( (int) $raw_id )
			: $this->handoff_repo->get_by_public_id( $raw_id );

		if ( ! $handoff ) {
			return ActionResult::failure(
				'HANDOFF_NOT_FOUND',
				__( 'Handoff record not found.', 'gemini-chat-assistant' )
			);
		}

		$outcome = $this->notification_service->send_handoff_notification( $handoff );

		if ( ! $outcome['success'] ) {
			return ActionResult::failure(
				$outcome['code'] ?? 'NOTIFICATION_FAILED',
				$outcome['message'] ?? __( 'Notification could not be sent.', 'gemini-chat-assistant' )
			);
		}

		return ActionResult::success( [
			'code'             => $outcome['code'],
			'message'          => $outcome['message'],
			'recipient_count'  => $outcome['recipient_count'] ?? 0,
			'handoff_public_id'=> $handoff['public_id'],
		] );
	}
}
