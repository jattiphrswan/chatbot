<?php
/**
 * Test Suite: Node N17.2 WooCommerce Read Integration.
 *
 * @package SkyFish\GeminiChat\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use SkyFish\GeminiChat\Integrations\ActionResult;
use SkyFish\GeminiChat\Integrations\ActionValidator;
use SkyFish\GeminiChat\Integrations\ActionExecutor;
use SkyFish\GeminiChat\Integrations\IntegrationRegistry;
use SkyFish\GeminiChat\Integrations\WooCommerce\WooCommerceIntegration;
use SkyFish\GeminiChat\Integrations\WooCommerce\SearchProductsAction;
use SkyFish\GeminiChat\Integrations\WooCommerce\GetProductAction;
use SkyFish\GeminiChat\Integrations\WooCommerce\SearchByCategoryAction;
use SkyFish\GeminiChat\Integrations\WooCommerce\WooCommerceFormatter;

/**
 * Mock WC_Product for Testing when WooCommerce is absent.
 */
class MockWcProduct {
	private int $id;
	private string $name;
	private string $sku;
	private string $price;
	private string $sale_price;
	private string $stock_status;
	private string $short_desc;
	private string $desc;
	private string $status;

	public function __construct( array $data ) {
		$this->id           = $data['id'] ?? 101;
		$this->name         = $data['name'] ?? 'Test Sneakers';
		$this->sku          = $data['sku'] ?? 'TS-101';
		$this->price        = $data['price'] ?? '49.99';
		$this->sale_price   = $data['sale_price'] ?? '39.99';
		$this->stock_status = $data['stock_status'] ?? 'instock';
		$this->short_desc   = $data['short_desc'] ?? 'Great running sneakers';
		$this->desc         = $data['desc'] ?? 'High performance running sneakers with shock absorption.';
		$this->status       = $data['status'] ?? 'publish';
	}

	public function get_id(): int { return $this->id; }
	public function get_name(): string { return $this->name; }
	public function get_sku(): string { return $this->sku; }
	public function get_price(): string { return $this->price; }
	public function get_sale_price(): string { return $this->sale_price; }
	public function get_stock_status(): string { return $this->stock_status; }
	public function get_short_description(): string { return $this->short_desc; }
	public function get_description(): string { return $this->desc; }
	public function get_status(): string { return $this->status; }
}

class WooCommerceIntegrationTest {

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
		echo "========================================================\n";
		echo "Running Node N17.2 WooCommerce Read Integration Test Suite\n";
		echo "========================================================\n\n";

		$this->test_woocommerce_detection();
		$this->test_search_products_validation();
		$this->test_get_product_validation();
		$this->test_search_category_validation();
		$this->test_mocked_execution();
		$this->test_security_and_scope_exclusion();

		echo "\n--------------------------------------------------------\n";
		echo "Tests completed. Passed: {$this->passed}, Failed: {$this->failed}\n";
		echo "========================================================\n";

		if ( $this->failed > 0 ) {
			exit( 1 );
		}
	}

	private function test_woocommerce_detection(): void {
		echo "\n-- Section 1: WooCommerce Detection & Integration Object --\n";

		$integration = new WooCommerceIntegration();
		$this->assert( 'woocommerce' === $integration->get_id(), '1.1 Integration ID is "woocommerce"' );
		$this->assert( 'WooCommerce Catalog' === $integration->get_name(), '1.2 Integration name correct' );
		$this->assert( is_bool( $integration->is_available() ), '1.3 is_available() returns boolean without error' );
		$this->assert( $integration->is_enabled(), '1.4 is_enabled() returns true' );

		$actions = $integration->get_actions();
		$this->assert( isset( $actions['woocommerce.search_products'] ), '1.5 search_products action registered' );
		$this->assert( isset( $actions['woocommerce.get_product'] ), '1.6 get_product action registered' );
		$this->assert( isset( $actions['woocommerce.search_by_category'] ), '1.7 search_by_category action registered' );
		$this->assert( 3 === count( $actions ), '1.8 Exactly 3 read-only actions provided' );

		// Test registration in IntegrationRegistry
		$registry = new IntegrationRegistry();
		$registered = $registry->register( $integration );
		$this->assert( $registered, '1.9 WooCommerceIntegration registers cleanly in IntegrationRegistry' );
		$this->assert( $registry->get_action( 'woocommerce.search_products' ) instanceof SearchProductsAction, '1.10 search_products retrievable from registry' );
	}

	private function test_search_products_validation(): void {
		echo "\n-- Section 2: Search Products Schema Validation --\n";

		$action = new SearchProductsAction();
		$schema = $action->get_input_schema();

		// Valid parameters
		$res1 = ActionValidator::validate( [ 'query' => 'hoodie', 'limit' => 5 ], $schema );
		$this->assert( $res1['valid'], '2.1 Valid search query and limit passes validation' );
		$this->assert( 'hoodie' === $res1['sanitized']['query'], '2.2 Query parameter sanitized' );
		$this->assert( 5 === $res1['sanitized']['limit'], '2.3 Limit parameter casted' );

		// Missing query (required)
		$res2 = ActionValidator::validate( [ 'limit' => 5 ], $schema );
		$this->assert( ! $res2['valid'], '2.4 Missing query fails validation' );
		$this->assert( isset( $res2['errors']['query'] ), '2.5 Query error flagged' );

		// Oversized query
		$oversized = str_repeat( 'a', 250 );
		$res3 = ActionValidator::validate( [ 'query' => $oversized ], $schema );
		$this->assert( ! $res3['valid'], '2.6 Query exceeding 200 chars fails' );

		// Oversized limit (> 10)
		$res4 = ActionValidator::validate( [ 'query' => 'shoes', 'limit' => 15 ], $schema );
		$this->assert( ! $res4['valid'], '2.7 Limit exceeding 10 items fails' );
	}

	private function test_get_product_validation(): void {
		echo "\n-- Section 3: Get Product Schema Validation --\n";

		$action = new GetProductAction();
		$schema = $action->get_input_schema();

		// Valid ID
		$res1 = ActionValidator::validate( [ 'product_id' => 42 ], $schema );
		$this->assert( $res1['valid'], '3.1 Valid product_id passes' );
		$this->assert( 42 === $res1['sanitized']['product_id'], '3.2 product_id correctly cast to integer' );

		// Invalid string ID
		$res2 = ActionValidator::validate( [ 'product_id' => 'not_a_number' ], $schema );
		$this->assert( ! $res2['valid'], '3.3 Non-numeric product_id fails' );

		// Out of bounds (< 1)
		$res3 = ActionValidator::validate( [ 'product_id' => 0 ], $schema );
		$this->assert( ! $res3['valid'], '3.4 Non-positive product_id fails' );
	}

	private function test_search_category_validation(): void {
		echo "\n-- Section 4: Search By Category Schema Validation --\n";

		$action = new SearchByCategoryAction();
		$schema = $action->get_input_schema();

		// Valid category
		$res1 = ActionValidator::validate( [ 'category' => 'clothing', 'limit' => 8 ], $schema );
		$this->assert( $res1['valid'], '4.1 Valid category passes' );
		$this->assert( 'clothing' === $res1['sanitized']['category'], '4.2 Category preserved' );

		// Missing category
		$res2 = ActionValidator::validate( [], $schema );
		$this->assert( ! $res2['valid'], '4.3 Missing category fails' );

		// Oversized category
		$res3 = ActionValidator::validate( [ 'category' => str_repeat( 'c', 120 ) ], $schema );
		$this->assert( ! $res3['valid'], '4.4 Oversized category fails' );
	}

	private function test_mocked_execution(): void {
		echo "\n-- Section 5: Normalization Formatter & Mocked Functions --\n";

		$mock_product = new MockWcProduct( [
			'id'          => 201,
			'name'        => 'Wireless Headphones',
			'sku'         => 'WH-201',
			'price'       => '99.00',
			'sale_price'  => '79.00',
			'short_desc'  => 'Premium sound quality.',
			'desc'        => 'Detailed description of wireless headphones.',
		] );

		// Summary formatting
		$summary = WooCommerceFormatter::format_summary( $mock_product );
		$this->assert( 201 === $summary['id'], '5.1 Formatter extracts correct ID' );
		$this->assert( 'Wireless Headphones' === $summary['name'], '5.2 Formatter extracts correct Name' );
		$this->assert( 'WH-201' === $summary['sku'], '5.3 Formatter extracts SKU' );
		$this->assert( '99.00' === $summary['price'], '5.4 Formatter extracts price' );
		$this->assert( '79.00' === $summary['sale_price'], '5.5 Formatter extracts sale price' );

		// Detail formatting
		$detail = WooCommerceFormatter::format_detail( $mock_product );
		$this->assert( 'Detailed description of wireless headphones.' === $detail['description'], '5.6 Formatter extracts full description' );
		$this->assert( is_array( $detail['categories'] ), '5.7 Categories returns array' );

		// Environment when WooCommerce functions are absent
		if ( ! function_exists( 'wc_get_products' ) ) {
			$search_action = new SearchProductsAction();
			$this->assert( ! $search_action->can_execute(), '5.8 can_execute() returns false when WooCommerce is inactive' );
			$res = $search_action->execute( [ 'query' => 'test' ] );
			$this->assert( ! $res->is_success(), '5.9 execute() returns failure ActionResult when WooCommerce is absent' );
			$this->assert( 'WOOCOMMERCE_UNAVAILABLE' === $res->get_error()['code'], '5.10 Error code is WOOCOMMERCE_UNAVAILABLE' );
		}
	}

	private function test_security_and_scope_exclusion(): void {
		echo "\n-- Section 6: Scope Exclusion & Security Verification --\n";

		$integration = new WooCommerceIntegration();
		$actions = $integration->get_actions();

		foreach ( $actions as $action_id => $action ) {
			$this->assert( ActionInterface::RISK_READ === $action->get_risk(), "6.1 Action {$action_id} is strictly RISK_READ" );
		}

		// Verify cart, checkout, and order functions are completely absent
		$this->assert( ! class_exists( 'WC_Cart' ), '6.2 WC_Cart class absent/unloaded' );
		$this->assert( ! function_exists( 'wc_create_order' ), '6.3 wc_create_order() absent/uncalled' );
		$this->assert( ! function_exists( 'wc_add_to_cart' ), '6.4 wc_add_to_cart() absent/uncalled' );
	}
}

$suite = new WooCommerceIntegrationTest();
$suite->run();
