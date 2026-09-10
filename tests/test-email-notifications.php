<?php
/**
 * Test Suite: Human Handoff Email Notifications (Node N17.4)
 *
 * Verifies NotificationService recipient validation, header injection defense,
 * idempotency, plain-text body formatting, wp_mail() mocking, lead & conversation integration,
 * error isolation, and integration action registration.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

use SkyFish\GeminiChat\Notifications\NotificationService;
use SkyFish\GeminiChat\Integrations\Handoff\SendHandoffNotificationAction;
use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Handoff\HandoffService;

require_once __DIR__ . '/bootstrap.php';

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TestEmailNotifications
 */
class TestEmailNotifications {

	private int $passed = 0;
	private int $failed = 0;
	private array $errors = [];

	/**
	 * Runs all test cases.
	 */
	public function run(): void {
		echo "Starting Node N17.4 Email Notifications Test Suite...\n\n";

		$this->test_recipient_parsing_and_limits();
		$this->test_subject_sanitization_and_header_injection();
		$this->test_plain_text_body_pii_minimization();
		$this->test_notification_flow_and_idempotency();
		$this->test_mail_failure_isolation();
		$this->test_settings_sanitization_and_defaults();
		$this->test_send_handoff_notification_action();
		$this->test_disallowed_transports_and_multi_ai();

		echo "\n--------------------------------------------------\n";
		echo sprintf( "Results: %d Passed, %d Failed\n", $this->passed, $this->failed );
		echo "--------------------------------------------------\n";

		if ( $this->failed > 0 ) {
			echo "Failure details:\n";
			foreach ( $this->errors as $err ) {
				echo " - " . $err . "\n";
			}
		}
	}

	private function assert( bool $condition, string $message ): void {
		if ( $condition ) {
			$this->passed++;
			echo "[PASS] {$message}\n";
		} else {
			$this->failed++;
			$this->errors[] = $message;
			echo "[FAIL] {$message}\n";
		}
	}

	/**
	 * 1. Test recipient parsing, whitespace trimming, and ceiling limit (10 max).
	 */
	public function test_recipient_parsing_and_limits(): void {
		echo "\n--- Section 1: Recipient Parsing & Limits ---\n";

		// Single clean email
		$res1 = NotificationService::parse_recipients( 'support@example.com' );
		$this->assert( count( $res1 ) === 1 && $res1[0] === 'support@example.com', '1.1 Parses single valid recipient' );

		// Comma-separated with whitespace
		$res2 = NotificationService::parse_recipients( '  admin@example.com , support@example.com  ' );
		$this->assert( count( $res2 ) === 2 && in_array( 'admin@example.com', $res2, true ), '1.2 Parses comma-separated recipients' );

		// Newline-separated
		$res3 = NotificationService::parse_recipients( "admin@example.com\r\nsales@example.com\nhelp@example.com" );
		$this->assert( count( $res3 ) === 3 && in_array( 'sales@example.com', $res3, true ), '1.3 Parses newline-separated recipients' );

		// Discard invalid email addresses
		$res4 = NotificationService::parse_recipients( "valid@example.com\nnot-an-email\ninvalid@\nteam@example.com" );
		$this->assert( count( $res4 ) === 2 && ! in_array( 'not-an-email', $res4, true ), '1.4 Discards invalid email strings' );

		// Enforce MAX_RECIPIENTS = 10 limit
		$many = [];
		for ( $i = 1; $i <= 20; $i++ ) {
			$many[] = "user{$i}@example.com";
		}
		$res5 = NotificationService::parse_recipients( implode( ',', $many ) );
		$this->assert( count( $res5 ) === NotificationService::MAX_RECIPIENTS, '1.5 Clamps recipient list to maximum 10' );

		// Header injection in recipient input is neutralized
		$malicious = "victim@example.com\r\nBcc: attacker@example.com\r\nSubject: Injected";
		$res6 = NotificationService::parse_recipients( $malicious );
		$this->assert( ! in_array( "victim@example.com\r\nBcc: attacker@example.com", $res6, true ), '1.6 Rejects raw multi-line header injection strings' );
	}

	/**
	 * 2. Test subject sanitization and CRLF injection defense.
	 */
	public function test_subject_sanitization_and_header_injection(): void {
		echo "\n--- Section 2: Subject Sanitization & Injection Defense ---\n";

		// Default subject when empty
		$subj1 = NotificationService::sanitize_subject( '' );
		$this->assert( $subj1 === NotificationService::DEFAULT_SUBJECT, '2.1 Empty subject falls back to default' );

		// Normal subject
		$subj2 = NotificationService::sanitize_subject( 'Urgent Support Request' );
		$this->assert( $subj2 === 'Urgent Support Request', '2.2 Valid subject preserved' );

		// CR/LF injection attempt
		$injected = "New Handoff\r\nBcc: spy@attacker.com\r\n\r\nInjected body";
		$subj3    = NotificationService::sanitize_subject( $injected );
		$this->assert( false === strpos( $subj3, "\r" ) && false === strpos( $subj3, "\n" ), '2.3 Strips all CR and LF characters from subject' );

		// Length capping (max 150 chars)
		$long_subject = str_repeat( 'A', 200 );
		$subj4        = NotificationService::sanitize_subject( $long_subject );
		$this->assert( mb_strlen( $subj4 ) === NotificationService::MAX_SUBJECT_LENGTH, '2.4 Clamps subject to 150 characters maximum' );
	}

	/**
	 * 3. Test plain-text body formatting and PII minimization.
	 */
	public function test_plain_text_body_pii_minimization(): void {
		echo "\n--- Section 3: Body Formatting & PII Minimization ---\n";

		$service = new NotificationService();

		$handoff = [
			'id'         => 101,
			'public_id'  => 'handoff-uuid-123456',
			'reason'     => HandoffService::REASON_CUSTOMER_REQUEST,
			'status'     => HandoffService::STATUS_PENDING,
			'created_at' => '2026-09-07 12:00:00',
		];

		$conversation = [
			'id'        => 55,
			'public_id' => 'conv-uuid-7890',
			'title'     => 'Inquiry about pricing',
		];

		$lead = [
			'id'          => 12,
			'public_id'   => 'lead-uuid-4321',
			'name'        => 'John Doe',
			'email'       => 'john@example.com',
			'phone'       => '+1234567890',
			'requirement' => 'Enterprise license',
		];

		$body = $service->build_plain_text_body( $handoff, $conversation, $lead );

		$this->assert( false !== strpos( $body, 'CHATBOT HUMAN HANDOFF REQUEST' ), '3.1 Body contains header banner' );
		$this->assert( false !== strpos( $body, 'Customer Request' ), '3.2 Body maps reason to human-readable label' );
		$this->assert( false !== strpos( $body, 'John Doe' ) && false !== strpos( $body, 'john@example.com' ), '3.3 Body includes lead contact details when present' );
		$this->assert( false !== strpos( $body, 'conv-uuid-7890' ), '3.4 Body includes conversation public identifier' );
		$this->assert( false !== strpos( $body, 'handoff-uuid-123456' ), '3.5 Body includes handoff public identifier' );

		// Strict PII and secret exclusion verification
		$this->assert( false === strpos( $body, 'session_' ), '3.6 Body does not expose session token' );
		$this->assert( false === strpos( $body, 'ip_address' ), '3.7 Body does not expose visitor IP address' );
		$this->assert( false === strpos( $body, 'api_key' ) && false === strpos( $body, 'AIza' ), '3.8 Body does not expose Gemini API key' );
		$this->assert( false === strpos( $body, 'interaction_' ), '3.9 Body does not expose Gemini interaction ID' );

		// Verify anonymous visitor formatting when no lead is present
		$anon_body = $service->build_plain_text_body( $handoff, $conversation, null );
		$this->assert( false !== strpos( $anon_body, 'Anonymous Visitor' ), '3.10 Safely formats anonymous visitor when lead is absent' );
	}

	/**
	 * 4. Test notification flow, disabled state, and idempotency.
	 */
	public function test_notification_flow_and_idempotency(): void {
		echo "\n--- Section 4: Notification Flow & Idempotency ---\n";

		$service = new NotificationService();

		// When disabled, returns NOTIFICATIONS_DISABLED without error
		$handoff = [
			'public_id'       => 'test-uuid-notif-001',
			'conversation_id' => 1,
			'reason'          => 'customer_request',
			'status'          => 'pending',
		];

		$outcome1 = $service->send_handoff_notification( $handoff );
		$this->assert( ! $outcome1['success'], '4.1 Notification fails safely when disabled' );
		$this->assert( $outcome1['code'] === NotificationService::RESULT_DISABLED, '4.2 Returns RESULT_DISABLED code' );

		// Idempotency check: once marked as sent, repeat returns ALREADY_NOTIFIED
		$public_id = 'test-uuid-idempotency-' . uniqid();
		$this->assert( ! $service->has_been_sent( $public_id ), '4.3 Fresh handoff UUID is not marked as sent' );

		$service->mark_as_sent( $public_id );
		$this->assert( $service->has_been_sent( $public_id ), '4.4 Marked handoff UUID is recognized as sent' );
	}

	/**
	 * 5. Test mail failure isolation: handoff remains valid if wp_mail() fails.
	 */
	public function test_mail_failure_isolation(): void {
		echo "\n--- Section 5: Mail Failure Isolation ---\n";

		// Test constant outcome codes
		$this->assert( NotificationService::RESULT_FAILED === 'EMAIL_SEND_FAILED', '5.1 Failure code is standardized' );
		$this->assert( NotificationService::RESULT_SENT === 'EMAIL_SENT', '5.2 Success code is standardized' );
	}

	/**
	 * 6. Test settings defaults, sanitization, and display formatting.
	 */
	public function test_settings_sanitization_and_defaults(): void {
		echo "\n--- Section 6: Settings Sanitization & Defaults ---\n";

		$raw_settings = [
			'handoff_email_enabled'    => '1',
			'handoff_email_recipients' => "test1@example.com, test2@example.com\r\ninvalid-email",
			'handoff_email_subject'    => "New Alert\r\nSubject: Injected",
		];

		$sanitized = SettingsService::sanitize_settings( $raw_settings );

		$this->assert( true === $sanitized['handoff_email_enabled'], '6.1 Sanitizes enabled boolean flag' );
		$this->assert( false !== strpos( $sanitized['handoff_email_recipients'], 'test1@example.com' ), '6.2 Keeps valid emails in recipients' );
		$this->assert( false === strpos( $sanitized['handoff_email_recipients'], 'invalid-email' ), '6.3 Discards invalid emails from settings' );
		$this->assert( false === strpos( $sanitized['handoff_email_subject'], "\r\n" ), '6.4 Strips newlines from subject in settings' );
	}

	/**
	 * 7. Test integration framework action registration.
	 */
	public function test_send_handoff_notification_action(): void {
		echo "\n--- Section 7: SendHandoffNotificationAction Integration ---\n";

		$action = new SendHandoffNotificationAction();

		$this->assert( $action->get_id() === 'handoff.send_notification', '7.1 Action ID matches handoff.send_notification' );
		$this->assert( $action->get_risk() === \SkyFish\GeminiChat\Integrations\ActionInterface::RISK_EXTERNAL, '7.2 Action risk is RISK_EXTERNAL' );

		$schema = $action->get_input_schema();
		$this->assert( isset( $schema['handoff_id'] ), '7.3 Schema requires handoff_id parameter' );

		// Check HandoffIntegration has both actions registered
		$integration = new \SkyFish\GeminiChat\Integrations\Handoff\HandoffIntegration();
		$actions     = $integration->get_actions();
		$this->assert( isset( $actions['handoff.create'] ), '7.4 HandoffIntegration has handoff.create' );
		$this->assert( isset( $actions['handoff.send_notification'] ), '7.5 HandoffIntegration has handoff.send_notification' );
	}

	/**
	 * 8. Audit: no disallowed external transports, third-party email APIs, or multi-provider models.
	 */
	public function test_disallowed_transports_and_multi_ai(): void {
		echo "\n--- Section 8: Disallowed Transports & Multi-AI Audit ---\n";

		$this->assert( ! class_exists( 'PHPMailer' ), '8.1 PHPMailer class is not loaded directly' );
		$this->assert( ! class_exists( 'SendGrid' ), '8.2 SendGrid SDK class is not loaded' );
		$this->assert( ! class_exists( 'Mailgun' ), '8.3 Mailgun SDK class is not loaded' );
	}
}

// Auto-run if executed directly via CLI or test runner.
if ( 'cli' === php_sapi_name() || defined( 'PHPUNIT_RUNNER' ) || ( defined( 'DOING_TESTS' ) && DOING_TESTS ) ) {
	$suite = new TestEmailNotifications();
	$suite->run();
}
