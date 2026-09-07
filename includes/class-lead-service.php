<?php
/**
 * Lead Application Service for Gemini Chat Assistant.
 *
 * Coordinates pre-chat validation, rate limiting, duplicate protection,
 * conversation association, and privacy-respecting lead persistence.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Database\ConversationRepository;
use SkyFish\GeminiChat\Database\LeadRepository;
use SkyFish\GeminiChat\Database\SessionService;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LeadService
 */
class LeadService {

	private SettingsService $settings_service;
	private SessionService $session_service;
	private ConversationRepository $conversation_repo;
	private LeadRepository $lead_repo;

	/**
	 * LeadService constructor.
	 *
	 * @param SettingsService|null        $settings_service  Optional settings service.
	 * @param SessionService|null         $session_service   Optional session service.
	 * @param ConversationRepository|null $conversation_repo Optional conversation repository.
	 * @param LeadRepository|null         $lead_repo         Optional lead repository.
	 */
	public function __construct(
		?SettingsService $settings_service = null,
		?SessionService $session_service = null,
		?ConversationRepository $conversation_repo = null,
		?LeadRepository $lead_repo = null
	) {
		$this->settings_service  = $settings_service ?? SettingsService::get_instance();
		$this->conversation_repo = $conversation_repo ?? new ConversationRepository();
		$this->lead_repo         = $lead_repo ?? new LeadRepository();
		$this->session_service   = $session_service ?? new SessionService( $this->conversation_repo );
	}

	/**
	 * Handles and persists a visitor pre-chat form submission.
	 *
	 * @param array<string, mixed> $payload    Submitted pre-chat fields.
	 * @param string               $session_id Validated browser session identifier.
	 * @return array<string, mixed>|WP_Error Normalized response array or WP_Error.
	 */
	public function handle_prechat_submission( array $payload, string $session_id ) {
		// 1. Honeypot check: reject if invisible honeypot field is filled.
		if ( ! empty( $payload['website_url'] ) ) {
			return new WP_Error(
				'SPAM_DETECTED',
				__( 'Spam submission detected.', 'gemini-chat-assistant' ),
				[ 'status' => 400 ]
			);
		}

		$settings = $this->settings_service->get_all();

		// 2. If prechat is not globally enabled, return skipped status safely.
		if ( empty( $settings['prechat_enabled'] ) ) {
			return [
				'lead_id' => '',
				'status'  => 'skipped',
			];
		}

		// 3. Authoritative server-side field validation against configured settings.
		$validated_fields = Validator::validate_prechat( $payload, $settings );
		if ( is_wp_error( $validated_fields ) ) {
			return $validated_fields;
		}

		$store_leads = (bool) ( $settings['store_leads'] ?? true );

		// 4. Resolve or initialize conversation record.
		$conversation = $this->session_service->get_or_create_conversation( $session_id );
		$conv_id      = is_array( $conversation ) && isset( $conversation['id'] ) ? (int) $conversation['id'] : null;

		// 5. If store_leads is false, do not write PII to the database table.
		if ( ! $store_leads ) {
			return [
				'lead_id' => '',
				'status'  => 'ephemeral',
			];
		}

		// 6. Duplicate submission protection: check if lead already exists for this conversation.
		if ( $conv_id > 0 ) {
			$existing_lead = $this->lead_repo->get_by_conversation_id( $conv_id );
			if ( $existing_lead && ! empty( $existing_lead['public_id'] ) ) {
				return [
					'lead_id' => $existing_lead['public_id'],
					'status'  => 'already_exists',
				];
			}
		}

		// 7. Create lead record.
		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		$lead_record = [
			'public_id'       => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'lead_', true ),
			'conversation_id' => $conv_id,
			'user_id'         => $current_user_id,
			'name'            => $validated_fields['name'],
			'email'           => $validated_fields['email'],
			'phone'           => $validated_fields['phone'],
			'requirement'     => $validated_fields['requirement'],
			'status'          => 'new',
		];

		$created = $this->lead_repo->create( $lead_record );

		if ( ! $created || empty( $created['public_id'] ) ) {
			return new WP_Error(
				'LEAD_CREATION_FAILED',
				__( 'Failed to record lead inquiry.', 'gemini-chat-assistant' ),
				[ 'status' => 500 ]
			);
		}

		return [
			'lead_id' => $created['public_id'],
			'status'  => 'completed',
		];
	}
}
