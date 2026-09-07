<?php
/**
 * Human Handoff Application Service for Gemini Chat Assistant.
 *
 * Coordinates visitor handoff requests, validation, conversation & lead association,
 * controlled status transitions, and intent detection.
 *
 * @package SkyFish\GeminiChat\Handoff
 */

namespace SkyFish\GeminiChat\Handoff;

use SkyFish\GeminiChat\Database\HandoffRepository;
use SkyFish\GeminiChat\Database\ConversationRepository;
use SkyFish\GeminiChat\Database\LeadRepository;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HandoffService
 */
class HandoffService {

	/**
	 * Controlled handoff lifecycle statuses.
	 */
	public const STATUS_PENDING   = 'pending';
	public const STATUS_ASSIGNED  = 'assigned';
	public const STATUS_RESOLVED  = 'resolved';
	public const STATUS_CANCELLED = 'cancelled';

	public const ALLOWED_STATUSES = [
		self::STATUS_PENDING,
		self::STATUS_ASSIGNED,
		self::STATUS_RESOLVED,
		self::STATUS_CANCELLED,
	];

	/**
	 * Controlled handoff reasons.
	 */
	public const REASON_CUSTOMER_REQUEST = 'customer_request';
	public const REASON_UNKNOWN_ANSWER   = 'unknown_answer';
	public const REASON_COMPLEX_QUESTION = 'complex_question';
	public const REASON_SALES_REQUEST    = 'sales_request';
	public const REASON_TECHNICAL_ISSUE  = 'technical_issue';

	public const ALLOWED_REASONS = [
		self::REASON_CUSTOMER_REQUEST,
		self::REASON_UNKNOWN_ANSWER,
		self::REASON_COMPLEX_QUESTION,
		self::REASON_SALES_REQUEST,
		self::REASON_TECHNICAL_ISSUE,
	];

	private HandoffRepository $handoff_repo;
	private ConversationRepository $conversation_repo;
	private LeadRepository $lead_repo;

	/**
	 * HandoffService constructor.
	 *
	 * @param HandoffRepository|null      $handoff_repo      Optional handoff repository.
	 * @param ConversationRepository|null $conversation_repo Optional conversation repository.
	 * @param LeadRepository|null         $lead_repo         Optional lead repository.
	 */
	public function __construct(
		?HandoffRepository $handoff_repo = null,
		?ConversationRepository $conversation_repo = null,
		?LeadRepository $lead_repo = null
	) {
		$this->handoff_repo      = $handoff_repo ?? new HandoffRepository();
		$this->conversation_repo = $conversation_repo ?? new ConversationRepository();
		$this->lead_repo         = $lead_repo ?? new LeadRepository();
	}

	/**
	 * Detects whether a visitor message expresses a request for human support.
	 *
	 * Checks explicit phrases and escalation intents without autonomous actions.
	 *
	 * @param string $message Sanitized user message.
	 * @return string|null Detected reason code (e.g. 'customer_request') or null if no handoff requested.
	 */
	public function detect_handoff_intent( string $message ): ?string {
		$lower = strtolower( trim( $message ) );

		if ( '' === $lower ) {
			return null;
		}

		// Direct explicit intent phrases
		$explicit_patterns = [
			'speak with someone',
			'speak to someone',
			'talk to someone',
			'talk to a person',
			'talk to person',
			'talk to human',
			'speak to human',
			'talk to an agent',
			'talk to agent',
			'speak with an agent',
			'speak with agent',
			'human support',
			'human agent',
			'live agent',
			'real person',
			'need a person',
			'contact support',
			'contact a representative',
			'talk to representative',
			'customer service representative',
			'call me',
			'have someone call me',
			'request a callback',
			'escalate to human',
		];

		foreach ( $explicit_patterns as $pattern ) {
			if ( false !== strpos( $lower, $pattern ) ) {
				return self::REASON_CUSTOMER_REQUEST;
			}
		}

		// Regex patterns for flexible phrasing
		$regex_patterns = [
			'/\b(want|need|like)\s+(to\s+)?(speak|talk)\s+(with|to)\s+(a\s+)?(human|person|agent|rep|representative|operator)\b/i',
			'/\b(connect|transfer)\s+(me\s+)?(to\s+)?(a\s+)?(human|person|agent|rep|support|operator)\b/i',
			'/\b(can|could)\s+someone\s+call\s+me\b/i',
			'/\b(can|could)\s+i\s+(speak|talk)\s+(to|with)\s+(someone|a\s+person|human)\b/i',
		];

		foreach ( $regex_patterns as $pattern ) {
			if ( preg_match( $pattern, $lower ) ) {
				return self::REASON_CUSTOMER_REQUEST;
			}
		}

		return null;
	}

	/**
	 * Creates a human handoff request for a conversation.
	 *
	 * Reuses any active pending handoff to prevent duplicates.
	 * Automatically links existing lead from N14 if found.
	 *
	 * @param int      $conversation_id Internal conversation database ID.
	 * @param string   $reason          Controlled reason string.
	 * @param int|null $lead_id         Optional internal lead ID.
	 * @return array<string, mixed>|WP_Error Normalized handoff row or WP_Error.
	 */
	public function create_handoff( int $conversation_id, string $reason = self::REASON_CUSTOMER_REQUEST, ?int $lead_id = null ) {
		// 1. Validate conversation ID.
		if ( $conversation_id <= 0 ) {
			return new WP_Error(
				'INVALID_CONVERSATION',
				__( 'A valid conversation ID is required to create a handoff request.', 'gemini-chat-assistant' ),
				[ 'status' => 400 ]
			);
		}

		$conversation = $this->conversation_repo->get_by_id( $conversation_id );
		if ( ! $conversation ) {
			return new WP_Error(
				'CONVERSATION_NOT_FOUND',
				__( 'Conversation record does not exist.', 'gemini-chat-assistant' ),
				[ 'status' => 404 ]
			);
		}

		// 2. Validate reason.
		if ( ! in_array( $reason, self::ALLOWED_REASONS, true ) ) {
			return new WP_Error(
				'INVALID_REASON',
				sprintf(
					/* translators: %s: Comma-separated list of allowed reasons */
					__( 'Invalid handoff reason. Allowed reasons: %s', 'gemini-chat-assistant' ),
					implode( ', ', self::ALLOWED_REASONS )
				),
				[ 'status' => 400 ]
			);
		}

		// 3. Duplicate handling: if active pending/assigned handoff exists for this conversation, return it.
		$existing_handoff = $this->handoff_repo->get_active_by_conversation_id( $conversation_id );
		if ( $existing_handoff ) {
			// If a lead was newly provided or discovered, update the record
			if ( null !== $lead_id && empty( $existing_handoff['lead_id'] ) ) {
				$this->handoff_repo->update_lead_id( (int) $existing_handoff['id'], $lead_id );
				$existing_handoff['lead_id'] = $lead_id;
			}
			return $existing_handoff;
		}

		// 4. Resolve Lead connection from N14 if lead_id was not explicitly passed.
		$resolved_lead_id = $lead_id;
		if ( empty( $resolved_lead_id ) ) {
			$lead = $this->lead_repo->get_by_conversation_id( $conversation_id );
			if ( $lead && ! empty( $lead['id'] ) ) {
				$resolved_lead_id = (int) $lead['id'];
			}
		}

		// 5. Create new handoff record.
		$record = [
			'public_id'       => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'handoff_', true ),
			'conversation_id' => $conversation_id,
			'lead_id'         => $resolved_lead_id,
			'reason'          => $reason,
			'status'          => self::STATUS_PENDING,
		];

		$created = $this->handoff_repo->create( $record );

		if ( ! $created ) {
			return new WP_Error(
				'HANDOFF_CREATION_FAILED',
				__( 'Failed to record handoff request in database.', 'gemini-chat-assistant' ),
				[ 'status' => 500 ]
			);
		}

		return $created;
	}

	/**
	 * Updates the status of a handoff request.
	 *
	 * Enforces controlled status transitions.
	 *
	 * @param string $public_id  Handoff public UUID.
	 * @param string $new_status Target status ('pending', 'assigned', 'resolved', 'cancelled').
	 * @return bool|WP_Error True on success or WP_Error.
	 */
	public function update_status( string $public_id, string $new_status ) {
		$clean_status = sanitize_text_field( $new_status );

		if ( ! in_array( $clean_status, self::ALLOWED_STATUSES, true ) ) {
			return new WP_Error(
				'INVALID_STATUS',
				sprintf(
					/* translators: %s: Comma-separated list of allowed statuses */
					__( 'Invalid status value. Allowed: %s', 'gemini-chat-assistant' ),
					implode( ', ', self::ALLOWED_STATUSES )
				),
				[ 'status' => 400 ]
			);
		}

		$handoff = $this->handoff_repo->get_by_public_id( $public_id );
		if ( ! $handoff ) {
			return new WP_Error(
				'HANDOFF_NOT_FOUND',
				__( 'Handoff request not found.', 'gemini-chat-assistant' ),
				[ 'status' => 404 ]
			);
		}

		$updated = $this->handoff_repo->update_status( $public_id, $clean_status );

		if ( ! $updated ) {
			return new WP_Error(
				'UPDATE_FAILED',
				__( 'Failed to update handoff status.', 'gemini-chat-assistant' ),
				[ 'status' => 500 ]
			);
		}

		return true;
	}

	/**
	 * Retrieves a single handoff by public UUID.
	 *
	 * @param string $public_id Public UUID.
	 * @return array<string, mixed>|null
	 */
	public function get_handoff( string $public_id ): ?array {
		return $this->handoff_repo->get_by_public_id( $public_id );
	}

	/**
	 * Retrieves paginated handoff entries for admin view.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_admin_list( array $args = [] ): array {
		return $this->handoff_repo->get_admin_list( $args );
	}

	/**
	 * Counts filtered handoff entries for admin view.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return int
	 */
	public function count_admin_list( array $args = [] ): int {
		return $this->handoff_repo->count_admin_list( $args );
	}

	/**
	 * Returns human-readable label for handoff status.
	 *
	 * @param string $status Controlled status code.
	 * @return string
	 */
	public static function get_status_label( string $status ): string {
		$labels = [
			self::STATUS_PENDING   => __( 'Pending', 'gemini-chat-assistant' ),
			self::STATUS_ASSIGNED  => __( 'Assigned', 'gemini-chat-assistant' ),
			self::STATUS_RESOLVED  => __( 'Resolved', 'gemini-chat-assistant' ),
			self::STATUS_CANCELLED => __( 'Cancelled', 'gemini-chat-assistant' ),
		];

		return $labels[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Returns human-readable label for handoff reason.
	 *
	 * @param string $reason Controlled reason code.
	 * @return string
	 */
	public static function get_reason_label( string $reason ): string {
		$labels = [
			self::REASON_CUSTOMER_REQUEST => __( 'Customer Request', 'gemini-chat-assistant' ),
			self::REASON_UNKNOWN_ANSWER   => __( 'Unknown Answer', 'gemini-chat-assistant' ),
			self::REASON_COMPLEX_QUESTION => __( 'Complex Question', 'gemini-chat-assistant' ),
			self::REASON_SALES_REQUEST    => __( 'Sales Inquiry', 'gemini-chat-assistant' ),
			self::REASON_TECHNICAL_ISSUE  => __( 'Technical Issue', 'gemini-chat-assistant' ),
		];

		return $labels[ $reason ] ?? ucfirst( str_replace( '_', ' ', $reason ) );
	}
}
