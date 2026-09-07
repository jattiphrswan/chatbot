<?php
/**
 * Email Notification Service for Gemini Chat Assistant.
 *
 * Coordinates operational email dispatches for human handoff requests using WordPress native wp_mail().
 * Strictly internal team notifications with header injection defense, recipient bounds, and idempotency.
 *
 * @package SkyFish\GeminiChat\Notifications
 */

namespace SkyFish\GeminiChat\Notifications;

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Handoff\HandoffService;
use SkyFish\GeminiChat\Database\HandoffRepository;
use SkyFish\GeminiChat\Database\ConversationRepository;
use SkyFish\GeminiChat\Database\LeadRepository;
use SkyFish\GeminiChat\Admin\AdminMenu;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NotificationService
 */
class NotificationService {

	public const MAX_RECIPIENTS = 10;
	public const MAX_SUBJECT_LENGTH = 150;
	public const DEFAULT_SUBJECT = 'New Chatbot Handoff Request';

	public const RESULT_DISABLED    = 'NOTIFICATIONS_DISABLED';
	public const RESULT_NO_RECIPIENT = 'NO_VALID_RECIPIENTS';
	public const RESULT_ALREADY_SENT = 'ALREADY_NOTIFIED';
	public const RESULT_SENT         = 'EMAIL_SENT';
	public const RESULT_FAILED       = 'EMAIL_SEND_FAILED';

	/**
	 * Short-lived transient key prefix to guarantee idempotency.
	 */
	private const TRANSIENT_PREFIX = 'gca_notif_sent_';

	private HandoffRepository $handoff_repo;
	private ConversationRepository $conversation_repo;
	private LeadRepository $lead_repo;

	/**
	 * NotificationService constructor.
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
	 * Checks whether human handoff email notifications are enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) SettingsService::get( 'handoff_email_enabled', false );
	}

	/**
	 * Retrieves and sanitizes the configured recipient email addresses.
	 *
	 * Discards invalid emails, trims whitespace, strips CR/LF, and enforces the recipient ceiling (10 max).
	 * Falls back to WordPress admin_email ONLY when notifications are enabled and no valid recipient is configured.
	 *
	 * @return array<int, string> List of verified email addresses.
	 */
	public function get_recipients(): array {
		$raw_recipients = SettingsService::get( 'handoff_email_recipients', '' );
		$recipients     = self::parse_recipients( $raw_recipients );

		// Fallback to get_option( 'admin_email' ) if enabled and no explicit recipients exist.
		if ( empty( $recipients ) && $this->is_enabled() ) {
			$admin_email = function_exists( 'get_option' ) ? get_option( 'admin_email' ) : '';
			if ( ! empty( $admin_email ) && is_email( $admin_email ) ) {
				$recipients[] = sanitize_email( $admin_email );
			}
		}

		return array_slice( array_unique( $recipients ), 0, self::MAX_RECIPIENTS );
	}

	/**
	 * Parses a raw multiline or comma-separated recipients string into a sanitized list of emails.
	 *
	 * @param mixed $raw Raw input.
	 * @return array<int, string>
	 */
	public static function parse_recipients( mixed $raw ): array {
		if ( is_array( $raw ) ) {
			$items = $raw;
		} elseif ( is_string( $raw ) ) {
			// Split by comma or newline.
			$items = preg_split( '/[\r\n,]+/', $raw );
			if ( ! is_array( $items ) ) {
				$items = [];
			}
		} else {
			return [];
		}

		$valid = [];
		foreach ( $items as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}
			// Aggressively strip any carriage return, line feed, or control characters.
			$clean = preg_replace( '/[\r\n\t\x00-\x1F\x7F]/', '', trim( $item ) );
			if ( empty( $clean ) ) {
				continue;
			}
			$sanitized = sanitize_email( $clean );
			if ( ! empty( $sanitized ) && is_email( $sanitized ) ) {
				$valid[] = $sanitized;
			}
			if ( count( $valid ) >= self::MAX_RECIPIENTS ) {
				break;
			}
		}

		return array_unique( $valid );
	}

	/**
	 * Formats recipients array into a clean textarea-ready string (one per line).
	 *
	 * @param mixed $recipients Array or string.
	 * @return string
	 */
	public static function format_recipients_for_display( mixed $recipients ): string {
		$parsed = self::parse_recipients( $recipients );
		return implode( "\n", $parsed );
	}

	/**
	 * Retrieves and sanitizes the configured email subject line.
	 *
	 * Aggressively removes CR/LF to prevent email header injection.
	 *
	 * @return string
	 */
	public function get_subject(): string {
		$raw = SettingsService::get( 'handoff_email_subject', self::DEFAULT_SUBJECT );
		return self::sanitize_subject( (string) $raw );
	}

	/**
	 * Sanitizes email subject string, stripping header injection vectors and capping length.
	 *
	 * @param string $subject Raw subject.
	 * @return string
	 */
	public static function sanitize_subject( string $subject ): string {
		// Strip newlines, tabs, and null bytes (header injection defense).
		$clean = preg_replace( '/[\r\n\t\x00-\x1F\x7F]+/', ' ', trim( $subject ) );
		$clean = sanitize_text_field( $clean );

		if ( '' === $clean ) {
			$clean = self::DEFAULT_SUBJECT;
		}

		if ( mb_strlen( $clean ) > self::MAX_SUBJECT_LENGTH ) {
			$clean = mb_substr( $clean, 0, self::MAX_SUBJECT_LENGTH );
		}

		return $clean;
	}

	/**
	 * Sends a human handoff notification email to configured recipients.
	 *
	 * Uses WordPress native wp_mail(). Idempotent per handoff public UUID.
	 *
	 * @param array<string, mixed> $handoff Validated handoff record row.
	 * @return array<string, mixed> Normalized outcome array.
	 */
	public function send_handoff_notification( array $handoff ): array {
		if ( ! $this->is_enabled() ) {
			return [
				'success' => false,
				'code'    => self::RESULT_DISABLED,
				'message' => __( 'Email notifications are disabled in settings.', 'gemini-chat-assistant' ),
			];
		}

		$public_id = (string) ( $handoff['public_id'] ?? '' );
		if ( empty( $public_id ) ) {
			return [
				'success' => false,
				'code'    => 'INVALID_HANDOFF',
				'message' => __( 'Invalid handoff record.', 'gemini-chat-assistant' ),
			];
		}

		// Idempotency check: prevent duplicate sends for the same handoff request.
		if ( $this->has_been_sent( $public_id ) ) {
			return [
				'success' => true,
				'code'    => self::RESULT_ALREADY_SENT,
				'message' => __( 'Notification was already processed for this handoff.', 'gemini-chat-assistant' ),
			];
		}

		$recipients = $this->get_recipients();
		if ( empty( $recipients ) ) {
			return [
				'success' => false,
				'code'    => self::RESULT_NO_RECIPIENT,
				'message' => __( 'No valid recipient email address configured.', 'gemini-chat-assistant' ),
			];
		}

		// Resolve associated records for email content.
		$conversation_id = absint( $handoff['conversation_id'] ?? 0 );
		$lead_id         = ! empty( $handoff['lead_id'] ) ? absint( $handoff['lead_id'] ) : null;

		$conversation = $conversation_id > 0 ? $this->conversation_repo->get_by_id( $conversation_id ) : null;
		$lead         = ( null !== $lead_id && $lead_id > 0 ) ? $this->lead_repo->get_by_id( $lead_id ) : null;

		$subject = $this->get_subject();
		$body    = $this->build_plain_text_body( $handoff, $conversation, $lead );

		// Strict plain-text headers. Zero CC/BCC/attachments from visitor input.
		$headers = [
			'Content-Type: text/plain; charset=UTF-8',
		];

		// Dispatch via WordPress native wp_mail().
		$sent = wp_mail( $recipients, $subject, $body, $headers );

		if ( $sent ) {
			// Mark handoff as notified in transient to prevent duplicate sends on retries.
			$this->mark_as_sent( $public_id );

			return [
				'success'          => true,
				'code'             => self::RESULT_SENT,
				'message'          => __( 'Notification accepted for sending by WordPress mail transport.', 'gemini-chat-assistant' ),
				'recipient_count'  => count( $recipients ),
			];
		}

		return [
			'success' => false,
			'code'    => self::RESULT_FAILED,
			'message' => __( 'WordPress wp_mail() returned false. Mail transport could not accept message.', 'gemini-chat-assistant' ),
		];
	}

	/**
	 * Builds internal plain-text notification body with strict PII minimization.
	 *
	 * Does NOT include session hashes, IP addresses, Gemini API keys, or full chat transcripts.
	 *
	 * @param array<string, mixed>      $handoff      Handoff record.
	 * @param array<string, mixed>|null $conversation Optional linked conversation.
	 * @param array<string, mixed>|null $lead         Optional linked lead.
	 * @return string Plain text email body.
	 */
	public function build_plain_text_body( array $handoff, ?array $conversation, ?array $lead ): string {
		$h_id         = (string) ( $handoff['public_id'] ?? '' );
		$status       = (string) ( $handoff['status'] ?? HandoffService::STATUS_PENDING );
		$reason       = (string) ( $handoff['reason'] ?? HandoffService::REASON_CUSTOMER_REQUEST );
		$status_label = HandoffService::get_status_label( $status );
		$reason_label = HandoffService::get_reason_label( $reason );

		$date_format = get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' );
		$created_ts  = ! empty( $handoff['created_at'] ) ? strtotime( (string) $handoff['created_at'] ) : current_time( 'timestamp' );
		$created_str = function_exists( 'date_i18n' ) ? date_i18n( $date_format, $created_ts ) : date( 'Y-m-d H:i:s', $created_ts );

		$lines   = [];
		$lines[] = '==================================================';
		$lines[] = 'CHATBOT HUMAN HANDOFF REQUEST';
		$lines[] = '==================================================';
		$lines[] = '';
		$lines[] = sprintf( 'Reason:  %s', $reason_label );
		$lines[] = sprintf( 'Status:  %s', $status_label );
		$lines[] = sprintf( 'Created: %s', $created_str );
		$lines[] = '';

		// Visitor / Lead Section (if legitimately captured in N14).
		$lines[] = '--------------------------------------------------';
		$lines[] = 'VISITOR DETAILS';
		$lines[] = '--------------------------------------------------';
		if ( $lead ) {
			$name  = ! empty( $lead['name'] ) ? sanitize_text_field( (string) $lead['name'] ) : __( 'Not provided', 'gemini-chat-assistant' );
			$email = ! empty( $lead['email'] ) ? sanitize_email( (string) $lead['email'] ) : __( 'Not provided', 'gemini-chat-assistant' );
			$phone = ! empty( $lead['phone'] ) ? sanitize_text_field( (string) $lead['phone'] ) : __( 'Not provided', 'gemini-chat-assistant' );

			$lines[] = sprintf( 'Name:  %s', $name );
			$lines[] = sprintf( 'Email: %s', $email );
			$lines[] = sprintf( 'Phone: %s', $phone );

			if ( ! empty( $lead['requirement'] ) ) {
				$lines[] = sprintf( 'Requirement: %s', sanitize_text_field( (string) $lead['requirement'] ) );
			}
		} else {
			$lines[] = __( 'Anonymous Visitor (No pre-chat lead captured)', 'gemini-chat-assistant' );
		}
		$lines[] = '';

		// Safe Admin Navigation Links.
		$lines[] = '--------------------------------------------------';
		$lines[] = 'ADMIN LINKS';
		$lines[] = '--------------------------------------------------';

		$admin_base = function_exists( 'admin_url' ) ? admin_url( 'admin.php' ) : '/wp-admin/admin.php';

		// 1. Handoff link
		$handoff_url = add_query_arg(
			[
				'page'       => AdminMenu::HANDOFFS_MENU_SLUG,
				'handoff_id' => $h_id,
			],
			$admin_base
		);
		$lines[] = sprintf( 'View Handoff:     %s', esc_url_raw( $handoff_url ) );

		// 2. Conversation link (if present)
		if ( $conversation && ! empty( $conversation['public_id'] ) ) {
			$conv_url = add_query_arg(
				[
					'page'            => AdminMenu::CONVERSATIONS_MENU_SLUG,
					'conversation_id' => (string) $conversation['public_id'],
				],
				$admin_base
			);
			$lines[] = sprintf( 'View Conversation:%s', esc_url_raw( $conv_url ) );
		}

		// 3. Lead link (if present)
		if ( $lead && ! empty( $lead['public_id'] ) ) {
			$lead_url = add_query_arg(
				[
					'page'    => AdminMenu::LEADS_MENU_SLUG,
					'lead_id' => (string) $lead['public_id'],
				],
				$admin_base
			);
			$lines[] = sprintf( 'View Lead:        %s', esc_url_raw( $lead_url ) );
		}

		$lines[] = '';
		$lines[] = '==================================================';
		$lines[] = sprintf( 'Sent by Gemini Chat Assistant on %s', function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : 'WordPress' );
		$lines[] = 'Note: This is an internal operational notification. Do not reply to this automated email.';

		return implode( "\n", $lines );
	}

	/**
	 * Checks whether this handoff has already been notified via transient.
	 *
	 * @param string $public_id Handoff public UUID.
	 * @return bool
	 */
	public function has_been_sent( string $public_id ): bool {
		if ( empty( $public_id ) ) {
			return false;
		}
		$transient_key = self::TRANSIENT_PREFIX . md5( $public_id );
		return false !== get_transient( $transient_key );
	}

	/**
	 * Marks this handoff as notified in a WordPress transient (retained for 7 days).
	 *
	 * @param string $public_id Handoff public UUID.
	 * @return void
	 */
	public function mark_as_sent( string $public_id ): void {
		if ( empty( $public_id ) ) {
			return;
		}
		$transient_key = self::TRANSIENT_PREFIX . md5( $public_id );
		set_transient( $transient_key, current_time( 'mysql', true ), 7 * DAY_IN_SECONDS );
	}

	/**
	 * Dispatches a test email to the configured recipients upon explicit admin request.
	 *
	 * @return array<string, mixed>
	 */
	public function send_test_notification(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return [
				'success' => false,
				'code'    => 'UNAUTHORIZED',
				'message' => __( 'Insufficient permissions to send test notification.', 'gemini-chat-assistant' ),
			];
		}

		$recipients = $this->get_recipients();
		if ( empty( $recipients ) ) {
			return [
				'success' => false,
				'code'    => self::RESULT_NO_RECIPIENT,
				'message' => __( 'No valid recipient email address configured.', 'gemini-chat-assistant' ),
			];
		}

		$subject = sprintf( '[TEST] %s', $this->get_subject() );
		$body    = implode(
			"\n",
			[
				'==================================================',
				'GEMINI CHAT ASSISTANT - TEST NOTIFICATION',
				'==================================================',
				'',
				__( 'This is a test notification verifying your WordPress wp_mail() transport configuration.', 'gemini-chat-assistant' ),
				'',
				sprintf( __( 'Site: %s', 'gemini-chat-assistant' ), function_exists( 'home_url' ) ? home_url() : '' ),
				sprintf( __( 'Dispatched at: %s', 'gemini-chat-assistant' ), current_time( 'mysql' ) ),
				'',
				__( 'If you received this message, human handoff notifications will be accepted for delivery.', 'gemini-chat-assistant' ),
				'==================================================',
			]
		);

		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
		$sent    = wp_mail( $recipients, $subject, $body, $headers );

		if ( $sent ) {
			return [
				'success' => true,
				'code'    => self::RESULT_SENT,
				'message' => __( 'Test email accepted for sending by WordPress mail transport.', 'gemini-chat-assistant' ),
			];
		}

		return [
			'success' => false,
			'code'    => self::RESULT_FAILED,
			'message' => __( 'Test email could not be sent. WordPress wp_mail() returned false.', 'gemini-chat-assistant' ),
		];
	}
}
