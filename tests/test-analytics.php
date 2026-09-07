<?php
/**
 * Standalone Test Suite for Analytics & Insights (Node N12).
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! defined( 'GCA_PLUGIN_DIR' ) ) {
	define( 'GCA_PLUGIN_DIR', __DIR__ . '/../' );
}

if ( ! defined( 'GCA_PLUGIN_URL' ) ) {
	define( 'GCA_PLUGIN_URL', 'https://example.com/wp-content/plugins/gemini-chat-assistant/' );
}

if ( ! defined( 'GCA_VERSION' ) ) {
	define( 'GCA_VERSION', '1.0.0' );
}

if ( ! defined( 'GCA_DB_VERSION' ) ) {
	define( 'GCA_DB_VERSION', '1.0.0' );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, $decimals );
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone() {
		return new \DateTimeZone( 'UTC' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		global $test_current_user_can_result;
		return isset( $test_current_user_can_result ) ? $test_current_user_can_result : true;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = [] ) {
		throw new \RuntimeException( 'wp_die called: ' . $message );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) {
		$parsed = parse_url( $url );
		$query = [];
		if ( isset( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query );
		}
		if ( is_array( $args ) ) {
			foreach ( $args as $k => $v ) {
				$query[ $k ] = $v;
			}
		}
		$base = ( isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' . $parsed['host'] : '' ) . ( $parsed['path'] ?? '' );
		return $base . '?' . http_build_query( $query );
	}
}

// Require classes.
require_once __DIR__ . '/../includes/Database/AnalyticsRepository.php';
require_once __DIR__ . '/../includes/Admin/AnalyticsService.php';
require_once __DIR__ . '/../includes/Database/ConversationRepository.php';
require_once __DIR__ . '/../includes/Database/MessageRepository.php';
require_once __DIR__ . '/../includes/Admin/SettingsService.php';
require_once __DIR__ . '/../includes/Admin/AdminMenu.php';

use SkyFish\GeminiChat\Database\AnalyticsRepository;
use SkyFish\GeminiChat\Admin\AnalyticsService;
use SkyFish\GeminiChat\Admin\AdminMenu;
use SkyFish\GeminiChat\Admin\SettingsService;

/**
 * Mock WPDB for Analytics testing.
 */
class MockWPDBAnalytics {
	public string $prefix = 'wp_';
	public array $queries = [];
	public array $prepared = [];
	public array $mock_kpis_conv = [];
	public array $mock_kpis_msg = [];
	public int $mock_today = 0;
	public array $mock_daily_convs = [];
	public array $mock_daily_msgs = [];
	public array $mock_models = [];

	public function prepare( $query, ...$args ) {
		if ( is_array( $args[0] ?? null ) && 1 === count( $args ) ) {
			$args = $args[0];
		}
		$formatted = vsprintf( str_replace( [ '%s', '%d', '%f' ], [ "'%s'", '%d', '%f' ], $query ), $args );
		$this->prepared[] = [
			'query' => $query,
			'args'  => $args,
			'sql'   => $formatted,
		];
		return $formatted;
	}

	public function get_row( $query, $output = 'ARRAY_A' ) {
		$this->queries[] = $query;
		if ( strpos( $query, 'gca_conversations' ) !== false ) {
			return $this->mock_kpis_conv;
		}
		if ( strpos( $query, 'gca_messages' ) !== false ) {
			return $this->mock_kpis_msg;
		}
		return [];
	}

	public function get_var( $query ) {
		$this->queries[] = $query;
		return (string) $this->mock_today;
	}

	public function get_results( $query, $output = 'ARRAY_A' ) {
		$this->queries[] = $query;
		if ( strpos( $query, 'model_name' ) !== false ) {
			return $this->mock_models;
		}
		if ( strpos( $query, 'gca_conversations' ) !== false ) {
			return $this->mock_daily_convs;
		}
		if ( strpos( $query, 'gca_messages' ) !== false ) {
			return $this->mock_daily_msgs;
		}
		return [];
	}
}

/**
 * Test Suite Runner.
 */
class TestAnalytics {
	private int $passed = 0;
	private int $failed = 0;
	private array $errors = [];

	private function assert( bool $condition, string $message ): void {
		if ( $condition ) {
			$this->passed++;
		} else {
			$this->failed++;
			$this->errors[] = $message;
			echo "FAIL: {$message}\n";
		}
	}

	public function run(): void {
		$this->test_date_range_presets();
		$this->test_custom_date_range_validation();
		$this->test_zero_data_handling();
		$this->test_kpi_aggregate_derivations();
		$this->test_timeline_generation();
		$this->test_model_distribution_calculation();
		$this->test_admin_menu_registration();
		$this->test_authorization_check();
		$this->test_template_rendering();

		echo "\n============================================\n";
		echo "Node N12 Analytics & Insights Test Summary:\n";
		echo "Passed: {$this->passed}, Failed: {$this->failed}\n";
		if ( $this->failed > 0 ) {
			echo "Errors:\n" . implode( "\n", $this->errors ) . "\n";
			exit( 1 );
		}
		echo "ALL N12 ANALYTICS & INSIGHTS TESTS PASSED!\n";
		echo "============================================\n";
	}

	private function test_date_range_presets(): void {
		$svc = new AnalyticsService();

		$r_today = $svc->resolve_date_range( 'today' );
		$this->assert( 'today' === $r_today['range'], 'Resolves today range' );
		$this->assert( ! empty( $r_today['start_utc'] ) && ! empty( $r_today['end_utc'] ), 'Today has UTC bounds' );

		$r_7 = $svc->resolve_date_range( '7days' );
		$this->assert( '7days' === $r_7['range'], 'Resolves 7days range' );

		$r_30 = $svc->resolve_date_range( '30days' );
		$this->assert( '30days' === $r_30['range'], 'Resolves 30days range' );

		$r_90 = $svc->resolve_date_range( '90days' );
		$this->assert( '90days' === $r_90['range'], 'Resolves 90days range' );

		$r_all = $svc->resolve_date_range( 'all' );
		$this->assert( 'all' === $r_all['range'], 'Resolves all range' );
		$this->assert( null === $r_all['start_utc'], 'All time has unbound start_utc' );
	}

	private function test_custom_date_range_validation(): void {
		$svc = new AnalyticsService();

		// Valid range
		$r_valid = $svc->resolve_date_range( 'custom', '2026-08-01', '2026-08-15' );
		$this->assert( 'custom' === $r_valid['range'], 'Accepts valid custom range' );
		$this->assert( strpos( (string) $r_valid['start_utc'], '2026-08-01' ) !== false, 'Custom start matches input' );

		// Invalid reversed dates (from > to)
		$r_invalid = $svc->resolve_date_range( 'custom', '2026-08-20', '2026-08-10' );
		$this->assert( '30days' === $r_invalid['range'], 'Invalid custom range safely falls back to 30days' );

		// Malformed date strings
		$r_malformed = $svc->resolve_date_range( 'custom', 'not-a-date', '2026-08-10' );
		$this->assert( '30days' === $r_malformed['range'], 'Malformed custom range falls back to 30days' );
	}

	private function test_zero_data_handling(): void {
		global $wpdb;
		$mock_db = new MockWPDBAnalytics();
		$wpdb = $mock_db;

		$repo = new AnalyticsRepository();
		$svc  = new AnalyticsService( $repo );
		$data = $svc->get_analytics_data( '30days' );

		$this->assert( 0 === $data['kpis']['total_conversations'], 'Total conversations is 0 on empty DB' );
		$this->assert( 0 === $data['kpis']['total_messages'], 'Total messages is 0 on empty DB' );
		$this->assert( 0.0 === $data['kpis']['avg_messages_per_conv'], 'Average messages is 0.0 with zero division safety' );
		$this->assert( 0 === $data['kpis']['total_tokens'], 'Total tokens is 0 on empty DB' );
		$this->assert( 'Not enough data' === $data['kpis']['formatted_latency'], 'Latency reports Not enough data on empty DB' );
	}

	private function test_kpi_aggregate_derivations(): void {
		global $wpdb;
		$mock_db = new MockWPDBAnalytics();
		$mock_db->mock_kpis_conv = [
			'total_conversations'  => 10,
			'unique_sessions'      => 8,
			'user_conversations'   => 4,
			'guest_conversations'  => 6,
			'active_conversations' => 7,
			'closed_conversations' => 3,
		];
		$mock_db->mock_kpis_msg = [
			'total_messages'      => 40,
			'user_messages'       => 20,
			'assistant_messages'  => 20,
			'total_input_tokens'  => 1500,
			'total_output_tokens' => 2500,
			'avg_latency_ms'      => 1250,
		];
		$mock_db->mock_today = 3;
		$wpdb = $mock_db;

		$repo = new AnalyticsRepository();
		$svc  = new AnalyticsService( $repo );
		$data = $svc->get_analytics_data( '30days' );

		$kpis = $data['kpis'];
		$this->assert( 10 === $kpis['total_conversations'], 'Correct total conversations' );
		$this->assert( 8 === $kpis['unique_sessions'], 'Correct unique sessions' );
		$this->assert( 3 === $kpis['conversations_today'], 'Correct conversations today' );
		$this->assert( 4.0 === $kpis['avg_messages_per_conv'], 'Calculates 4.0 avg turns per conversation' );
		$this->assert( 4000 === $kpis['total_tokens'], 'Calculates 4000 total tokens' );
		$this->assert( '1.3s' === $kpis['formatted_latency'], 'Formats 1250ms as 1.3s' );
		$this->assert( 70 === $kpis['active_percentage'], 'Calculates 70% active' );
		$this->assert( 60 === $kpis['guest_percentage'], 'Calculates 60% guest' );
		$this->assert( 40 === $kpis['user_percentage'], 'Calculates 40% user' );
	}

	private function test_timeline_generation(): void {
		global $wpdb;
		$mock_db = new MockWPDBAnalytics();
		$mock_db->mock_daily_convs = [
			[ 'date_bucket' => '2026-09-01', 'count' => 5 ],
			[ 'date_bucket' => '2026-09-03', 'count' => 3 ],
		];
		$mock_db->mock_daily_msgs = [
			[ 'date_bucket' => '2026-09-01', 'count' => 10 ],
		];
		$wpdb = $mock_db;

		$repo = new AnalyticsRepository();
		$svc  = new AnalyticsService( $repo );
		$data = $svc->get_analytics_data( 'custom', '2026-09-01', '2026-09-03' );

		$timeline = $data['timeline'];
		$this->assert( 3 === count( $timeline ), 'Continuous timeline creates 3 daily buckets including missing 2026-09-02' );
		$this->assert( 0 === $timeline[1]['conversations'], 'Missing date 2026-09-02 correctly has 0 conversations' );
		$this->assert( 3 === $timeline[2]['conversations'], 'Date 2026-09-03 has 3 conversations' );
	}

	private function test_model_distribution_calculation(): void {
		global $wpdb;
		$mock_db = new MockWPDBAnalytics();
		$mock_db->mock_models = [
			[ 'model_name' => 'gemini-3.8-flash', 'count' => 90 ],
			[ 'model_name' => 'gemini-3.8-pro', 'count' => 10 ],
		];
		$wpdb = $mock_db;

		$repo = new AnalyticsRepository();
		$svc  = new AnalyticsService( $repo );
		$data = $svc->get_analytics_data( '30days' );

		$models = $data['models'];
		$this->assert( 2 === count( $models ), 'Returns 2 models in distribution' );
		$this->assert( 'gemini-3.8-flash' === $models[0]['model'], 'First model is gemini-3.8-flash' );
		$this->assert( 90.0 === $models[0]['percentage'], 'Calculates 90% share for primary model' );
		$this->assert( 10.0 === $models[1]['percentage'], 'Calculates 10% share for secondary model' );
	}

	private function test_admin_menu_registration(): void {
		$settings_svc = new SettingsService();
		$menu = new AdminMenu( $settings_svc );

		$this->assert( method_exists( $menu, 'render_analytics_page' ), 'AdminMenu has render_analytics_page method' );
	}

	private function test_authorization_check(): void {
		global $test_current_user_can_result;
		$test_current_user_can_result = false;

		$settings_svc = new SettingsService();
		$menu = new AdminMenu( $settings_svc );

		$exception_thrown = false;
		try {
			$menu->render_analytics_page();
		} catch ( \RuntimeException $e ) {
			$exception_thrown = true;
		}
		$this->assert( $exception_thrown, 'Unauthorized visitor is blocked with wp_die' );
	}

	private function test_template_rendering(): void {
		global $test_current_user_can_result;
		$test_current_user_can_result = true;

		$analytics = [
			'range_info' => [
				'range'         => '30days',
				'label'         => 'Last 30 Days',
				'start_utc'     => '2026-08-08 00:00:00',
				'end_utc'       => '2026-09-07 23:59:59',
				'start_local'   => '2026-08-08 00:00:00',
				'end_local'     => '2026-09-07 23:59:59',
				'is_continuous' => true,
			],
			'kpis' => [
				'total_conversations'   => 15,
				'unique_sessions'       => 12,
				'conversations_today'   => 2,
				'total_messages'        => 60,
				'avg_messages_per_conv' => 4.0,
				'total_tokens'          => 8500,
				'input_tokens'          => 3500,
				'output_tokens'         => 5000,
				'avg_latency_ms'        => 1400,
				'formatted_latency'     => '1.4s',
				'active_conversations'  => 10,
				'closed_conversations'  => 5,
				'active_percentage'     => 67,
				'closed_percentage'     => 33,
				'guest_conversations'   => 12,
				'user_conversations'    => 3,
				'guest_percentage'      => 80,
				'user_percentage'       => 20,
			],
			'models' => [
				[ 'model' => 'gemini-3.8-flash', 'count' => 30, 'percentage' => 100.0 ],
			],
			'timeline' => [
				[ 'date' => '2026-09-06', 'label' => 'Sep 6', 'conversations' => 8, 'messages' => 32 ],
				[ 'date' => '2026-09-07', 'label' => 'Sep 7', 'conversations' => 7, 'messages' => 28 ],
			],
		];
		$store_messages    = true;
		$base_url          = admin_url( 'admin.php?page=gca-analytics' );
		$conversations_url = admin_url( 'admin.php?page=gca-conversations' );

		ob_start();
		include __DIR__ . '/../templates/admin/analytics.php';
		$output = ob_get_clean();

		$this->assert( strpos( $output, 'Analytics &amp; Insights' ) !== false || strpos( $output, 'Analytics & Insights' ) !== false, 'Renders Analytics page title' );
		$this->assert( strpos( $output, 'Total Conversations' ) !== false, 'Renders Total Conversations KPI card' );
		$this->assert( strpos( $output, 'gemini-3.8-flash' ) !== false, 'Renders Gemini model distribution badge' );
		$this->assert( strpos( $output, '1.4s' ) !== false, 'Renders response latency metric' );
		$this->assert( strpos( $output, 'Daily Activity Trends' ) !== false, 'Renders daily activity trends chart' );
	}
}

$suite = new TestAnalytics();
$suite->run();
