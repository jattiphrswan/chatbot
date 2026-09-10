<?php
/**
 * Runtime Bootstrap Test for WordPress Plugin Startup & Class Resolution.
 *
 * Simulates WordPress startup sequence:
 * - Environment constants
 * - WordPress core mocks ($wpdb, options, actions, filters, REST classes)
 * - Plugin bootstrapping:
 *   - Activation hook: Activator::activate() -> Migrator::migrate()
 *   - Instantiation: Plugin::get_instance() -> load_dependencies()
 *   - Startup: Plugin::run()
 *   - Accessors: All service and repository accessors including get_lead_repository()
 *
 * @package SkyFish\GeminiChat\Tests
 */


// Mock environment
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

// Global options store
$GLOBALS['mock_wp_options'] = [];
$GLOBALS['mock_wp_actions'] = [];
$GLOBALS['mock_wp_filters'] = [];

// WordPress mock functions
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['mock_wp_options'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $key, $value = '' ) {
		if ( ! isset( $GLOBALS['mock_wp_options'][ $key ] ) ) {
			$GLOBALS['mock_wp_options'][ $key ] = $value;
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value = '' ) {
		$GLOBALS['mock_wp_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['mock_wp_options'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['mock_wp_actions'][ $tag ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['mock_wp_filters'][ $tag ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag, ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $callback ) {}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'https://example.com/wp-content/plugins/gemini-chat-assistant/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return 'gemini-chat-assistant/gemini-chat-assistant.php';
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return true;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = [] ) {
		return array_merge( $defaults, is_array( $args ) ? $args : [] );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private mixed $data;

		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Controller' ) ) {
	abstract class WP_REST_Controller {}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		public const CREATABLE = 'POST';
		public const READABLE  = 'GET';
	}
}

if ( ! class_exists( 'wpdb' ) ) {
	class wpdb {
		public string $prefix = 'wp_';

		public function get_charset_collate(): string {
			return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
		}

		public function prepare( $query, ...$args ): string {
			return (string) $query;
		}

		public function query( $query ) {
			return true;
		}

		public function get_row( $query, $output = 'OBJECT' ) {
			return null;
		}

		public function get_results( $query, $output = 'OBJECT' ) {
			return [];
		}

		public function get_var( $query ) {
			return null;
		}

		public function insert( $table, $data, $format = null ) {
			return 1;
		}
	}
}

$GLOBALS['wpdb'] = new \wpdb();

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $sql ) {
		return [ 'created' => true ];
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff )
		);
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12 ) {
		return bin2hex( random_bytes( (int) ceil( $length / 2 ) ) );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated, $plugin_rel_path ) {
		return true;
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules() {
		return true;
	}
}

// Include plugin entrypoint
require_once dirname( __DIR__ ) . '/gemini-chat-assistant.php';

class RuntimeBootstrapTest {

	private int $passed = 0;
	private int $failed = 0;

	public function run(): void {
		echo "=======================================================\n";
		echo "Running Full Runtime Bootstrap & Class Resolution Test\n";
		echo "=======================================================\n";

		$this->test_activation_hook();
		$this->test_plugin_singleton();
		$this->test_plugin_run_startup();
		$this->test_lead_repository_accessor();
		$this->test_all_plugin_accessors();

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

	private function test_activation_hook(): void {
		echo "\n-- Section 1: Plugin Activation Hook --\n";

		// Execute activation hook
		\SkyFish\GeminiChat\Activator::activate();

		$settings = get_option( 'gca_settings' );
		$this->assert( is_array( $settings ), '1.1 gca_settings option created on activation' );
		$this->assert( isset( $settings['default_provider'] ), '1.2 gca_settings contains default_provider' );

		$db_version = get_option( \SkyFish\GeminiChat\Database\Migrator::VERSION_OPTION );
		$this->assert( $db_version === \SkyFish\GeminiChat\Database\Migrator::SCHEMA_VERSION, '1.3 gca_db_version matches SCHEMA_VERSION' );
	}

	private function test_plugin_singleton(): void {
		echo "\n-- Section 2: Plugin Singleton & Dependency Loading --\n";

		$plugin = \SkyFish\GeminiChat\Plugin::get_instance();
		$this->assert( $plugin instanceof \SkyFish\GeminiChat\Plugin, '2.1 Plugin::get_instance() returns Plugin instance' );
	}

	private function test_plugin_run_startup(): void {
		echo "\n-- Section 3: Plugin::run() Full Startup Trace --\n";

		$plugin = \SkyFish\GeminiChat\Plugin::get_instance();
		$exception_thrown = false;

		try {
			$plugin->run();
		} catch ( \Throwable $e ) {
			$exception_thrown = true;
			echo "  [ERROR] Exception during Plugin::run(): " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
		}

		$this->assert( ! $exception_thrown, '3.1 Plugin::run() executes without fatal errors or missing class exceptions' );
	}

	private function test_lead_repository_accessor(): void {
		echo "\n-- Section 4: Regression Test for LeadRepository Resolution --\n";

		$plugin = \SkyFish\GeminiChat\Plugin::get_instance();
		$lead_repo = $plugin->get_lead_repository();

		$this->assert( $lead_repo instanceof \SkyFish\GeminiChat\Database\LeadRepository, '4.1 get_lead_repository() returns SkyFish\GeminiChat\Database\LeadRepository' );
	}

	private function test_all_plugin_accessors(): void {
		echo "\n-- Section 5: All Plugin Service & Repository Accessors --\n";

		$plugin = \SkyFish\GeminiChat\Plugin::get_instance();

		$this->assert( $plugin->get_conversation_repository() instanceof \SkyFish\GeminiChat\Database\ConversationRepository, '5.1 get_conversation_repository() valid' );
		$this->assert( $plugin->get_message_repository() instanceof \SkyFish\GeminiChat\Database\MessageRepository, '5.2 get_message_repository() valid' );
		$this->assert( $plugin->get_faq_repository() instanceof \SkyFish\GeminiChat\Database\FaqRepository, '5.3 get_faq_repository() valid' );
		$this->assert( $plugin->get_knowledge_repository() instanceof \SkyFish\GeminiChat\Database\KnowledgeRepository, '5.4 get_knowledge_repository() valid' );
		$this->assert( $plugin->get_handoff_repository() instanceof \SkyFish\GeminiChat\Database\HandoffRepository, '5.5 get_handoff_repository() valid' );

		$this->assert( $plugin->get_session_service() instanceof \SkyFish\GeminiChat\Database\SessionService, '5.6 get_session_service() valid' );
		$this->assert( $plugin->get_lead_service() instanceof \SkyFish\GeminiChat\LeadService, '5.7 get_lead_service() valid' );
		$this->assert( $plugin->get_profile_service() instanceof \SkyFish\GeminiChat\Admin\ProfileService, '5.8 get_profile_service() valid' );
		$this->assert( $plugin->get_handoff_service() instanceof \SkyFish\GeminiChat\Handoff\HandoffService, '5.9 get_handoff_service() valid' );
		$this->assert( $plugin->get_notification_service() instanceof \SkyFish\GeminiChat\Notifications\NotificationService, '5.10 get_notification_service() valid' );

		$this->assert( $plugin->get_knowledge_indexer() instanceof \SkyFish\GeminiChat\Knowledge\KnowledgeIndexer, '5.11 get_knowledge_indexer() valid' );
		$this->assert( $plugin->get_knowledge_retriever() instanceof \SkyFish\GeminiChat\Knowledge\KnowledgeRetriever, '5.12 get_knowledge_retriever() valid' );
		$this->assert( $plugin->get_knowledge_context_builder() instanceof \SkyFish\GeminiChat\Knowledge\KnowledgeContextBuilder, '5.13 get_knowledge_context_builder() valid' );

		$this->assert( $plugin->get_integration_registry() instanceof \SkyFish\GeminiChat\Integrations\IntegrationRegistry, '5.14 get_integration_registry() valid' );
		$this->assert( $plugin->get_provider_registry() instanceof \SkyFish\GeminiChat\Providers\ProviderRegistry, '5.15 get_provider_registry() valid' );
		$this->assert( $plugin->get_provider_selection_service() instanceof \SkyFish\GeminiChat\Providers\ProviderSelectionService, '5.16 get_provider_selection_service() valid' );

		$this->assert( $plugin->get_gemini_client() instanceof \SkyFish\GeminiChat\GeminiClient, '5.17 get_gemini_client() valid' );
		$this->assert( $plugin->get_chat_service() instanceof \SkyFish\GeminiChat\ChatService, '5.18 get_chat_service() valid' );
		$this->assert( $plugin->get_rest_controller() instanceof \SkyFish\GeminiChat\RestController, '5.19 get_rest_controller() valid' );
		$this->assert( $plugin->get_assets() instanceof \SkyFish\GeminiChat\Assets, '5.20 get_assets() valid' );
		$this->assert( $plugin->get_shortcode() instanceof \SkyFish\GeminiChat\Shortcode, '5.21 get_shortcode() valid' );
		$this->assert( $plugin->get_rate_limiter() instanceof \SkyFish\GeminiChat\RateLimiter, '5.22 get_rate_limiter() valid' );
	}
}

// Execute test
$suite = new RuntimeBootstrapTest();
$suite->run();
