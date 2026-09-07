<?php
/**
 * Human Handoff Business Integration Adapter.
 *
 * @package SkyFish\GeminiChat\Integrations\Handoff
 */

namespace SkyFish\GeminiChat\Integrations\Handoff;

use SkyFish\GeminiChat\Integrations\IntegrationInterface;
use SkyFish\GeminiChat\Handoff\HandoffService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HandoffIntegration
 *
 * Connects the Human Handoff subsystem to the central IntegrationRegistry.
 */
class HandoffIntegration implements IntegrationInterface {

	public const ID = 'handoff';

	/**
	 * Map of registered action instances.
	 *
	 * @var array<string, \SkyFish\GeminiChat\Integrations\ActionInterface>
	 */
	private array $actions = [];

	private HandoffService $handoff_service;

	/**
	 * HandoffIntegration constructor.
	 *
	 * @param HandoffService|null $handoff_service Optional handoff service.
	 */
	public function __construct( ?HandoffService $handoff_service = null ) {
		$this->handoff_service = $handoff_service ?? new HandoffService();
		$this->actions         = [
			CreateHandoffAction::ID => new CreateHandoffAction( $this->handoff_service ),
		];
	}

	/**
	 * Returns controlled integration identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * Returns human-readable integration name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Live Agent & Human Handoff', 'gemini-chat-assistant' );
	}

	/**
	 * Returns description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Facilitates controlled escalation of visitor chats to human team members and administrators.', 'gemini-chat-assistant' );
	}

	/**
	 * Checks whether the integration is available.
	 *
	 * Native WordPress feature: always available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Checks whether the integration is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return true;
	}

	/**
	 * Returns all actions provided by this integration.
	 *
	 * @return array<string, \SkyFish\GeminiChat\Integrations\ActionInterface>
	 */
	public function get_actions(): array {
		return $this->actions;
	}
}
