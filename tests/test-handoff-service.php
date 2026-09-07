<?php
/**
 * Test Suite: Node N17.3 Human Handoff.
 *
 * @package SkyFish\GeminiChat\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use SkyFish\GeminiChat\Database\Migrator;
use SkyFish\GeminiChat\Database\HandoffRepository;
use SkyFish\GeminiChat\Database\ConversationRepository;
use SkyFish\GeminiChat\Database\LeadRepository;
use SkyFish\GeminiChat\Handoff\HandoffService;
use SkyFish\GeminiChat\Integrations\ActionResult;
use SkyFish\GeminiChat\Integrations\ActionInterface;
use SkyFish\GeminiChat\Integrations\IntegrationRegistry;
use SkyFish\GeminiChat\Integrations\Handoff\HandoffIntegration;
use SkyFish\GeminiChat\Integrations\Handoff\CreateHandoffAction;

class HandoffServiceTest {

	private int $passed = 0;
	private int $failed = 0;

	public function run(): void {
		echo "=======================================================\n";
		echo "Running Node N17.3 Human Handoff Test Suite\n";
		echo "=======================================================\n";

		$this->test_schema_and_constants();
		$this->test_intent_detection();
		$this->test_handoff_creation_and_validation();
		$this->test_duplicate_handling_and_lead_attachment();
		$this->test_status_transitions();
		$this->test_integration_framework_registration();
		$this->test_scope_and_disallowed_apis();

		echo "\n=======================================================\n";
		printf( "Tests Completed: %d | Passed: %d | Failed: %d\n", $this->passed + $this->failed, $this->passed, $this->failed );
		echo "=======================================================\n";

		if ( $this->failed > 0 ) {
			exit( 1 );
		}
	}

	private function assert( bool $condition, string $description ): void {
		if ( $condition ) {
			echo "  [PASS] {$description}\n";
			$this->passed++;
		} else {
			echo "  [FAIL] {$description}\n";
			$this->failed++;
		}
	}

	private function test_schema_and_constants(): void {
		echo "\n-- Section 1: Schema & Constants Verification --\n";

		$this->assert( '1.3.0' === Migrator::SCHEMA_VERSION, '1.1 Schema version bumped to 1.3.0' );
		$this->assert( 'gca_handoffs' === HandoffRepository::TABLE_NAME, '1.2 Handoff table name is gca_handoffs' );

		// Statuses
		$this->assert( in_array( 'pending', HandoffService::ALLOWED_STATUSES, true ), '1.3 Status pending allowed' );
		$this->assert( in_array( 'assigned', HandoffService::ALLOWED_STATUSES, true ), '1.4 Status assigned allowed' );
		$this->assert( in_array( 'resolved', HandoffService::ALLOWED_STATUSES, true ), '1.5 Status resolved allowed' );
		$this->assert( in_array( 'cancelled', HandoffService::ALLOWED_STATUSES, true ), '1.6 Status cancelled allowed' );

		// Reasons
		$this->assert( in_array( 'customer_request', HandoffService::ALLOWED_REASONS, true ), '1.7 Reason customer_request allowed' );
		$this->assert( in_array( 'unknown_answer', HandoffService::ALLOWED_REASONS, true ), '1.8 Reason unknown_answer allowed' );
		$this->assert( in_array( 'complex_question', HandoffService::ALLOWED_REASONS, true ), '1.9 Reason complex_question allowed' );
		$this->assert( in_array( 'sales_request', HandoffService::ALLOWED_REASONS, true ), '1.10 Reason sales_request allowed' );
		$this->assert( in_array( 'technical_issue', HandoffService::ALLOWED_REASONS, true ), '1.11 Reason technical_issue allowed' );
	}

	private function test_intent_detection(): void {
		echo "\n-- Section 2: Handoff Intent Detection --\n";

		$service = new HandoffService();

		// Explicit human requests
		$this->assert( 'customer_request' === $service->detect_handoff_intent( 'I want to speak with someone' ), '2.1 Detects "speak with someone"' );
		$this->assert( 'customer_request' === $service->detect_handoff_intent( 'I need human support' ), '2.2 Detects "human support"' );
		$this->assert( 'customer_request' === $service->detect_handoff_intent( 'Can someone call me?' ), '2.3 Detects "can someone call me"' );
		$this->assert( 'customer_request' === $service->detect_handoff_intent( 'talk to an agent please' ), '2.4 Detects "talk to an agent"' );
		$this->assert( 'customer_request' === $service->detect_handoff_intent( 'Connect me to a representative' ), '2.5 Detects "connect me to a representative"' );
		$this->assert( 'customer_request' === $service->detect_handoff_intent( 'I need a real person to solve this' ), '2.6 Detects "real person"' );

		// Normal inquiries (no handoff)
		$this->assert( null === $service->detect_handoff_intent( 'What are your store hours?' ), '2.7 Ignores standard store hours inquiry' );
		$this->assert( null === $service->detect_handoff_intent( 'How much does the jacket cost?' ), '2.8 Ignores product inquiry' );
		$this->assert( null === $service->detect_handoff_intent( '' ), '2.9 Ignores empty message' );
	}

	private function test_handoff_creation_and_validation(): void {
		echo "\n-- Section 3: Creation & Validation Rules --\n";

		$service = new HandoffService();

		// Invalid conversation ID
		$res_zero_conv = $service->create_handoff( 0, 'customer_request' );
		$this->assert( is_wp_error( $res_zero_conv ), '3.1 Rejects conversation_id <= 0' );
		$this->assert( 'INVALID_CONVERSATION' === $res_zero_conv->get_error_code(), '3.2 Error code is INVALID_CONVERSATION' );

		// Invalid reason
		$res_bad_reason = $service->create_handoff( 12, 'invalid_reason_string' );
		$this->assert( is_wp_error( $res_bad_reason ), '3.3 Rejects unapproved reason' );
		$this->assert( 'INVALID_REASON' === $res_bad_reason->get_error_code(), '3.4 Error code is INVALID_REASON' );

		// Invalid status update
		$res_bad_status = $service->update_status( 'handoff_uuid_123', 'invalid_status_xyz' );
		$this->assert( is_wp_error( $res_bad_status ), '3.5 Rejects unapproved status transition' );
		$this->assert( 'INVALID_STATUS' === $res_bad_status->get_error_code(), '3.6 Error code is INVALID_STATUS' );
	}

	private function test_duplicate_handling_and_lead_attachment(): void {
		echo "\n-- Section 4: Duplicate Protection & Lead Attachment --\n";

		$mock_handoff_repo = new class extends HandoffRepository {
			private array $store = [];
			public function __construct() {}
			public function create( array $data ): ?array {
				$data['id'] = 55;
				$this->store[55] = $data;
				return $data;
			}
			public function get_active_by_conversation_id( int $conversation_id ): ?array {
				if ( 99 === $conversation_id ) {
					return [
						'id'              => 55,
						'public_id'       => 'handoff_uuid_active',
						'conversation_id' => 99,
						'lead_id'         => null,
						'reason'          => 'customer_request',
						'status'          => 'pending',
					];
				}
				return null;
			}
			public function update_lead_id( int $handoff_id, int $lead_id ): bool {
				return true;
			}
		};

		$mock_conv_repo = new class extends ConversationRepository {
			public function __construct() {}
			public function get_by_id( int $id ): ?array {
				return [ 'id' => $id, 'public_id' => 'conv_pub_123' ];
			}
		};

		$mock_lead_repo = new class extends LeadRepository {
			public function __construct() {}
			public function get_by_conversation_id( int $conversation_id ): ?array {
				return [ 'id' => 105, 'public_id' => 'lead_pub_456', 'name' => 'Alice' ];
			}
		};

		$service = new HandoffService( $mock_handoff_repo, $mock_conv_repo, $mock_lead_repo );

		// 4.1 Duplicate check returns existing active record
		$dup_result = $service->create_handoff( 99, 'customer_request' );
		$this->assert( is_array( $dup_result ), '4.1 Existing active handoff returned as array' );
		$this->assert( 'handoff_uuid_active' === $dup_result['public_id'], '4.2 Duplicate prevention returned active handoff UUID' );

		// 4.2 Lead auto-attachment on fresh conversation
		$fresh_result = $service->create_handoff( 100, 'customer_request' );
		$this->assert( is_array( $fresh_result ), '4.3 Fresh handoff created successfully' );
		$this->assert( 105 === $fresh_result['lead_id'], '4.4 Existing lead ID 105 automatically attached' );
	}

	private function test_status_transitions(): void {
		echo "\n-- Section 5: Status Transition Labels & Logic --\n";

		$this->assert( 'Pending' === HandoffService::get_status_label( 'pending' ), '5.1 Pending status label' );
		$this->assert( 'Assigned' === HandoffService::get_status_label( 'assigned' ), '5.2 Assigned status label' );
		$this->assert( 'Resolved' === HandoffService::get_status_label( 'resolved' ), '5.3 Resolved status label' );
		$this->assert( 'Cancelled' === HandoffService::get_status_label( 'cancelled' ), '5.4 Cancelled status label' );

		$this->assert( 'Customer Request' === HandoffService::get_reason_label( 'customer_request' ), '5.5 Customer request reason label' );
		$this->assert( 'Technical Issue' === HandoffService::get_reason_label( 'technical_issue' ), '5.6 Technical issue reason label' );
	}

	private function test_integration_framework_registration(): void {
		echo "\n-- Section 6: Integration Framework Registration --\n";

		$integration = new HandoffIntegration();
		$this->assert( 'handoff' === $integration->get_id(), '6.1 Integration ID is "handoff"' );
		$this->assert( $integration->is_available(), '6.2 Handoff integration is available' );
		$this->assert( $integration->is_enabled(), '6.3 Handoff integration is enabled' );

		$actions = $integration->get_actions();
		$this->assert( isset( $actions['handoff.create'] ), '6.4 Action handoff.create registered' );

		$action = $actions['handoff.create'];
		$this->assert( ActionInterface::RISK_WRITE === $action->get_risk(), '6.5 handoff.create risk is RISK_WRITE' );

		$schema = $action->get_input_schema();
		$this->assert( isset( $schema['conversation_id'] ), '6.6 conversation_id in schema' );
		$this->assert( true === $schema['conversation_id']['required'], '6.7 conversation_id is required' );

		$registry = new IntegrationRegistry();
		$registered = $registry->register( $integration );
		$this->assert( $registered, '6.8 HandoffIntegration cleanly registers in IntegrationRegistry' );
		$this->assert( $registry->get_action( 'handoff.create' ) instanceof CreateHandoffAction, '6.9 handoff.create retrievable from registry' );
	}

	private function test_scope_and_disallowed_apis(): void {
		echo "\n-- Section 7: Scope Verification & Disallowed APIs --\n";

		// Assert no notification triggers in N17.3
		$this->assert( ! function_exists( 'gca_send_handoff_notification' ), '7.1 No handoff notification sender function in N17.3' );

		// Assert no multi-AI providers
		$this->assert( ! class_exists( 'SkyFish\GeminiChat\Providers\OpenAIProvider' ), '7.2 OpenAIProvider absent' );
		$this->assert( ! class_exists( 'SkyFish\GeminiChat\Providers\ClaudeProvider' ), '7.3 ClaudeProvider absent' );
		$this->assert( ! class_exists( 'SkyFish\GeminiChat\Providers\ProviderFactory' ), '7.4 ProviderFactory absent' );
	}
}

$suite = new HandoffServiceTest();
$suite->run();
