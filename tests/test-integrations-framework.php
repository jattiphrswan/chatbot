<?php
/**
 * Test Suite: Node N17.1 Integration Framework Foundation.
 *
 * @package SkyFish\GeminiChat\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use SkyFish\GeminiChat\Integrations\ActionInterface;
use SkyFish\GeminiChat\Integrations\ActionResult;
use SkyFish\GeminiChat\Integrations\ActionValidator;
use SkyFish\GeminiChat\Integrations\ActionExecutor;
use SkyFish\GeminiChat\Integrations\IntegrationInterface;
use SkyFish\GeminiChat\Integrations\IntegrationRegistry;

/**
 * Mock Test Action for Unit Testing.
 */
class TestReadAction implements ActionInterface {

	public function get_id(): string {
		return 'test_integration.search_items';
	}

	public function get_name(): string {
		return 'Search Items';
	}

	public function get_description(): string {
		return 'Searches test items.';
	}

	public function get_risk(): string {
		return self::RISK_READ;
	}

	public function get_input_schema(): array {
		return [
			'query' => [
				'type'       => 'string',
				'required'   => true,
				'min_length' => 2,
				'max_length' => 100,
			],
			'limit' => [
				'type'     => 'integer',
				'required' => false,
				'default'  => 5,
				'min'      => 1,
				'max'      => 20,
			],
			'active_only' => [
				'type'     => 'boolean',
				'required' => false,
				'default'  => true,
			],
		];
	}

	public function can_execute( array $arguments = [] ): bool {
		return true;
	}

	public function execute( array $arguments = [] ): ActionResult {
		if ( 'trigger_exception' === ( $arguments['query'] ?? '' ) ) {
			throw new \RuntimeException( 'Database connection dropped' );
		}

		return ActionResult::success(
			[
				'items' => [
					[ 'id' => 1, 'name' => 'Item 1 for ' . $arguments['query'] ],
				],
				'total' => 1,
			],
			'Found 1 item.'
		);
	}
}

/**
 * Mock Test Integration for Unit Testing.
 */
class TestBusinessIntegration implements IntegrationInterface {

	private bool $available;
	private bool $enabled;

	public function __construct( bool $available = true, bool $enabled = true ) {
		$this->available = $available;
		$this->enabled   = $enabled;
	}

	public function get_id(): string {
		return 'test_integration';
	}

	public function get_name(): string {
		return 'Test Business Integration';
	}

	public function get_description(): string {
		return 'Provides mock actions for framework testing.';
	}

	public function is_available(): bool {
		return $this->available;
	}

	public function is_enabled(): bool {
		return $this->enabled;
	}

	public function get_actions(): array {
		return [
			'test_integration.search_items' => new TestReadAction(),
		];
	}
}

class IntegrationsFrameworkTest {

	private int $passed = 0;
	private int $failed = 0;

	private function assert( bool $condition, string $description ): void {
		if ( $condition ) {
			$this->passed++;
			echo "[PASS] {$description}\n";
		} else {
			$this->failed++;
			echo "[FAIL] {$description}\n";
		}
	}

	public function run(): void {
		echo "====================================================\n";
		echo "Running Node N17.1 Integration Framework Test Suite\n";
		echo "====================================================\n\n";

		$this->test_action_result();
		$this->test_action_validator();
		$this->test_registry();
		$this->test_executor();
		$this->test_security_and_isolation();

		echo "\n----------------------------------------------------\n";
		echo "Tests completed. Passed: {$this->passed}, Failed: {$this->failed}\n";
		echo "====================================================\n";

		if ( $this->failed > 0 ) {
			exit( 1 );
		}
	}

	private function test_action_result(): void {
		echo "\n-- Section 1: ActionResult Value Object --\n";

		$success = ActionResult::success( [ 'items' => [ 1, 2, 3 ] ], 'Items loaded' );
		$this->assert( $success->is_success(), '1.1 ActionResult::success is_success() === true' );
		$this->assert( 'Items loaded' === $success->get_message(), '1.2 ActionResult message preserved' );
		$this->assert( null === $success->get_error(), '1.3 Success result has null error' );
		$this->assert( isset( $success->get_data()['items'] ), '1.4 Payload data preserved' );

		$array_form = $success->to_array();
		$this->assert( true === $array_form['success'] && null === $array_form['error'], '1.5 to_array() shape correct' );

		$failure = ActionResult::failure( 'INTEGRATION_UNAVAILABLE', 'Service is offline' );
		$this->assert( ! $failure->is_success(), '1.6 ActionResult::failure is_success() === false' );
		$this->assert( 'integration_unavailable' === $failure->get_error()['code'], '1.7 Error code sanitized' );
		$this->assert( 'Service is offline' === $failure->get_error()['message'], '1.8 Error message preserved' );
	}

	private function test_action_validator(): void {
		echo "\n-- Section 2: ActionValidator Schema Enforcement --\n";

		$action = new TestReadAction();
		$schema = $action->get_input_schema();

		// 1. Valid parameters
		$res1 = ActionValidator::validate( [ 'query' => 'shoes', 'limit' => 10 ], $schema );
		$this->assert( $res1['valid'], '2.1 Valid arguments pass validation' );
		$this->assert( 'shoes' === $res1['sanitized']['query'], '2.2 String parameter sanitized' );
		$this->assert( 10 === $res1['sanitized']['limit'], '2.3 Integer parameter casted' );
		$this->assert( true === $res1['sanitized']['active_only'], '2.4 Default parameter applied' );

		// 2. Missing required
		$res2 = ActionValidator::validate( [ 'limit' => 5 ], $schema );
		$this->assert( ! $res2['valid'], '2.5 Missing required parameter fails validation' );
		$this->assert( isset( $res2['errors']['query'] ), '2.6 Error flagged for missing required field' );

		// 3. Unknown rogue argument
		$res3 = ActionValidator::validate( [ 'query' => 'shoes', 'rogue_param' => 'eval' ], $schema );
		$this->assert( ! $res3['valid'], '2.7 Unknown argument rejected strictly' );
		$this->assert( isset( $res3['errors']['rogue_param'] ), '2.8 Error flagged for unknown argument' );

		// 4. Bounds check
		$res4 = ActionValidator::validate( [ 'query' => 'shoes', 'limit' => 999 ], $schema );
		$this->assert( ! $res4['valid'], '2.9 Exceeding integer max fails' );
		$this->assert( isset( $res4['errors']['limit'] ), '2.10 Error flagged for out-of-bounds integer' );

		// 5. String length check
		$res5 = ActionValidator::validate( [ 'query' => 'a' ], $schema );
		$this->assert( ! $res5['valid'], '2.11 String below min_length fails' );
	}

	private function test_registry(): void {
		echo "\n-- Section 3: IntegrationRegistry --\n";

		$registry = new IntegrationRegistry();

		// Register valid
		$integration = new TestBusinessIntegration();
		$ok = $registry->register( $integration );
		$this->assert( $ok, '3.1 Valid integration registered successfully' );
		$this->assert( $registry->has( 'test_integration' ), '3.2 registry->has() returns true' );
		$this->assert( $registry->get( 'test_integration' ) instanceof IntegrationInterface, '3.3 registry->get() returns instance' );

		// Duplicate integration rejection
		$dup_ok = $registry->register( new TestBusinessIntegration() );
		$this->assert( ! $dup_ok, '3.4 Duplicate integration registration rejected' );

		// Action indexing
		$action = $registry->get_action( 'test_integration.search_items' );
		$this->assert( $action instanceof ActionInterface, '3.5 Action retrieved from registry' );
		$this->assert( 'test_integration' === $registry->get_integration_id_for_action( 'test_integration.search_items' ), '3.6 Action ownership mapped correctly' );

		// Invalid ID patterns
		$bad_integration = new class implements IntegrationInterface {
			public function get_id(): string { return 'BAD ID WITH SPACES!'; }
			public function get_name(): string { return 'Bad'; }
			public function get_description(): string { return 'Bad'; }
			public function is_available(): bool { return true; }
			public function is_enabled(): bool { return true; }
			public function get_actions(): array { return []; }
		};
		$this->assert( ! $registry->register( $bad_integration ), '3.7 Invalid integration ID format rejected' );
	}

	private function test_executor(): void {
		echo "\n-- Section 4: ActionExecutor Safe Pipeline --\n";

		$registry = new IntegrationRegistry();
		$registry->register( new TestBusinessIntegration() );
		$executor = new ActionExecutor( $registry );

		// 1. Successful execution
		$res1 = $executor->execute( 'test_integration.search_items', [ 'query' => 'running shoes' ] );
		$this->assert( $res1->is_success(), '4.1 Successful action execution returns success ActionResult' );
		$this->assert( isset( $res1->get_data()['items'] ), '4.2 Output data delivered to caller' );

		// 2. Unknown action ID
		$res2 = $executor->execute( 'test_integration.does_not_exist' );
		$this->assert( ! $res2->is_success(), '4.3 Unknown action ID returns failure' );
		$this->assert( 'ACTION_NOT_FOUND' === $res2->get_error()['code'], '4.4 Error code is ACTION_NOT_FOUND' );

		// 3. Malformed action ID
		$res3 = $executor->execute( '../../evil/path' );
		$this->assert( ! $res3->is_success(), '4.5 Malformed action ID returns failure' );
		$this->assert( 'INVALID_ACTION_ID' === $res3->get_error()['code'], '4.6 Error code is INVALID_ACTION_ID' );

		// 4. Unavailable integration
		$unavail_registry = new IntegrationRegistry();
		$unavail_registry->register( new TestBusinessIntegration( false, true ) );
		$unavail_exec = new ActionExecutor( $unavail_registry );
		$res4 = $unavail_exec->execute( 'test_integration.search_items', [ 'query' => 'test' ] );
		$this->assert( ! $res4->is_success(), '4.7 Unavailable integration execution rejected' );
		$this->assert( 'INTEGRATION_UNAVAILABLE' === $res4->get_error()['code'], '4.8 Error code is INTEGRATION_UNAVAILABLE' );

		// 5. Disabled integration
		$disabled_registry = new IntegrationRegistry();
		$disabled_registry->register( new TestBusinessIntegration( true, false ) );
		$disabled_exec = new ActionExecutor( $disabled_registry );
		$res5 = $disabled_exec->execute( 'test_integration.search_items', [ 'query' => 'test' ] );
		$this->assert( ! $res5->is_success(), '4.9 Disabled integration execution rejected' );
		$this->assert( 'INTEGRATION_DISABLED' === $res5->get_error()['code'], '4.10 Error code is INTEGRATION_DISABLED' );

		// 6. Argument validation failure
		$res6 = $executor->execute( 'test_integration.search_items', [ 'query' => 'a' ] ); // min length is 2
		$this->assert( ! $res6->is_success(), '4.11 Invalid arguments rejected' );
		$this->assert( 'INVALID_ACTION_ARGUMENTS' === $res6->get_error()['code'], '4.12 Error code is INVALID_ACTION_ARGUMENTS' );

		// 7. Exception containment without raw trace leakage
		$res7 = $executor->execute( 'test_integration.search_items', [ 'query' => 'trigger_exception' ] );
		$this->assert( ! $res7->is_success(), '4.13 Thrown exception trapped safely' );
		$this->assert( 'ACTION_FAILED' === $res7->get_error()['code'], '4.14 Error code is ACTION_FAILED' );
		$this->assert( false === strpos( $res7->get_error()['message'], 'Database connection dropped' ), '4.15 Raw exception message is NOT leaked' );
	}

	private function test_security_and_isolation(): void {
		echo "\n-- Section 5: Security, Non-Existent N17.2+ Features & Provider Integrity --\n";

		// 1. Verify risk classification constant values
		$this->assert( ActionInterface::RISK_READ === 'read', '5.1 RISK_READ defined as "read"' );
		$this->assert( ActionInterface::RISK_WRITE === 'write', '5.2 RISK_WRITE defined as "write"' );
		$this->assert( ActionInterface::RISK_EXTERNAL === 'external', '5.3 RISK_EXTERNAL defined as "external"' );

		// 2. Verify no live WooCommerce actions exist in repository
		$this->assert( ! function_exists( 'wc_get_products' ), '5.4 WooCommerce live function wc_get_products() absent/uncalled' );

		// 3. Verify no OpenAI / Claude provider classes exist
		$this->assert( ! class_exists( 'SkyFish\GeminiChat\Providers\OpenAIProvider' ), '5.5 OpenAIProvider does not exist' );
		$this->assert( ! class_exists( 'SkyFish\GeminiChat\Providers\ClaudeProvider' ), '5.6 ClaudeProvider does not exist' );
		$this->assert( ! interface_exists( 'SkyFish\GeminiChat\Providers\ProviderInterface' ), '5.7 ProviderInterface does not exist' );
	}
}

$suite = new IntegrationsFrameworkTest();
$suite->run();
