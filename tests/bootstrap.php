<?php
/**
 * Test Bootstrap & WordPress Stubs for Gemini Chat Assistant.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}
if ( ! defined( 'GCA_PLUGIN_DIR' ) ) {
	define( 'GCA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'OBJECT_K' ) ) {
	define( 'OBJECT_K', 'OBJECT_K' );
}


// Global mocks storage
$GLOBALS['mock_wp_options']    = [];
$GLOBALS['mock_options']       = &$GLOBALS['mock_wp_options'];
$GLOBALS['mock_wp_transients'] = [];
$GLOBALS['mock_wp_actions']    = [];
$GLOBALS['mock_wp_filters']    = [];

// Options API
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

// Transients API
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		return $GLOBALS['mock_wp_transients'][ $transient ] ?? false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['mock_wp_transients'][ $transient ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		unset( $GLOBALS['mock_wp_transients'][ $transient ] );
		return true;
	}
}

// Hooks
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['mock_wp_actions'][ $tag ][] = $callback;
		return true;
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag, ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['mock_wp_filters'][ $tag ][] = $callback;
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

// Paths and URLs
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'https://example.com/wp-json/' . ltrim( $path, '/' );
	}
}

// Formatting & Sanitization
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
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		if ( '' === $color || null === $color ) {
			return '';
		}
		if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color ) ) {
			return (string) $color;
		}
		return '';
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string, $remove_breaks = false ) {
		$string = preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', $string );
		$string = strip_tags( (string) $string );
		if ( $remove_breaks ) {
			$string = preg_replace( '/[\\r\\n\\t ]+/', ' ', $string );
		}
		return trim( $string );
	}
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( $email, FILTER_SANITIZE_EMAIL );
	}
}
if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
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
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

// mbstring polyfills (matching WordPress wp-includes/compat.php)
if ( ! function_exists( 'mb_strlen' ) ) {
	function mb_strlen( $str, $encoding = null ) {
		return strlen( (string) $str );
	}
}
if ( ! function_exists( 'mb_substr' ) ) {
	function mb_substr( $str, $start, $length = null, $encoding = null ) {
		return null === $length ? substr( (string) $str, $start ) : substr( (string) $str, $start, $length );
	}
}
if ( ! function_exists( 'mb_strtolower' ) ) {
	function mb_strtolower( $str, $encoding = null ) {
		return strtolower( (string) $str );
	}
}
if ( ! function_exists( 'mb_strrpos' ) ) {
	function mb_strrpos( $haystack, $needle, $offset = 0, $encoding = null ) {
		return strrpos( (string) $haystack, (string) $needle, $offset );
	}
}

// Translations
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

// WordPress Core Classes
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
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
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

		public function get_col( $query = '', $x = 0 ) {
			return [];
		}

		public int $insert_id = 1;
		public string $last_error = '';

		public function insert( $table, $data, $format = null ) {
			return 1;
		}

		public function update( $table, $data, $where, $format = null, $where_format = null ) {
			return 1;
		}

		public function delete( $table, $where, $where_format = null ) {
			return 1;
		}

		public function esc_like( $text ): string {
			return addcslashes( (string) $text, '_%\\' );
		}
	}
}
$GLOBALS['wpdb'] = new \wpdb();

if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( $text, $num_words = 55, $more = null ) {
		if ( null === $more ) $more = '&hellip;';
		$words = preg_split( "/[\n\r\t ]+/", $text, $num_words + 1, PREG_SPLIT_NO_EMPTY );
		if ( count( $words ) > $num_words ) {
			array_pop( $words );
			$text = implode( ' ', $words ) . $more;
		} else {
			$text = implode( ' ', $words );
		}
		return $text;
	}
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, $decimals );
	}
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $echo = true ) {
		$res = ( (string) $checked === (string) $current ) ? " checked='checked'" : '';
		if ( $echo ) echo $res;
		return $res;
	}
}
if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $echo = true ) {
		$res = ( (string) $selected === (string) $current ) ? " selected='selected'" : '';
		if ( $echo ) echo $res;
		return $res;
	}
}
if ( ! function_exists( 'disabled' ) ) {
	function disabled( $disabled, $current = true, $echo = true ) {
		$res = ( (string) $disabled === (string) $current ) ? " disabled='disabled'" : '';
		if ( $echo ) echo $res;
		return $res;
	}
}

if ( ! class_exists( 'WP_REST_Controller' ) ) {
	abstract class WP_REST_Controller {
		protected $namespace;
		protected $rest_base;
	}
}
if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		public const CREATABLE = 'POST';
		public const READABLE  = 'GET';
	}
}
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private array $params = [];
		public function set_param( $k, $v ) { $this->params[ $k ] = $v; }
		public function get_param( $k ) { return $this->params[ $k ] ?? null; }
		public function get_params() { return $this->params; }
	}
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public $data;
		public $status;
		public array $headers = [];
		public function __construct( $data = null, $status = 200, array $headers = [] ) {
			$this->data    = $data;
			$this->status  = $status;
			$this->headers = $headers;
		}
		public function get_data() { return $this->data; }
		public function get_status(): int { return (int) $this->status; }
		public function set_data( $data ) { $this->data = $data; }
		public function set_status( int $status ) { $this->status = $status; }
		public function get_headers(): array { return $this->headers; }
		public function header( string $key, string $value, bool $replace = true ) {
			$this->headers[ $key ] = $value;
		}
	}
}

// User and Auth
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() { return 1; }
}
global $mock_current_caps, $test_current_user_can_result;
$mock_current_caps            = [];
$test_current_user_can_result = true;

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		if ( isset( $GLOBALS['mock_user_caps'] ) && is_array( $GLOBALS['mock_user_caps'] ) ) {
			return ! empty( $GLOBALS['mock_user_caps'][ $capability ] );
		}
		if ( isset( $GLOBALS['mock_current_caps'] ) && array_key_exists( $capability, $GLOBALS['mock_current_caps'] ) ) {
			return (bool) $GLOBALS['mock_current_caps'][ $capability ];
		}
		return isset( $GLOBALS['test_current_user_can_result'] ) ? (bool) $GLOBALS['test_current_user_can_result'] : true;
	}
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = [] ) {
		throw new \RuntimeException( 'wp_die called: ' . ( is_scalar( $message ) ? (string) $message : json_encode( $message ) ) );
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) { return 'test_nonce_' . $action; }
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		if ( empty( $nonce ) || 'invalid_nonce' === $nonce || false === $nonce ) {
			return false;
		}
		if ( is_string( $nonce ) && strpos( $nonce, 'test_nonce_' ) === 0 ) {
			return $nonce === 'test_nonce_' . $action ? 1 : false;
		}
		return 1;
	}
}
if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		$nonce = isset( $_REQUEST[ $query_arg ] ) ? $_REQUEST[ $query_arg ] : ( isset( $_POST[ $query_arg ] ) ? $_POST[ $query_arg ] : ( isset( $_GET[ $query_arg ] ) ? $_GET[ $query_arg ] : '' ) );
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( 'The link you followed has expired.', '', [ 'response' => 403 ] );
		}
		return 1;
	}
}
if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) { return true; }
}

global $mock_admin_menu, $mock_admin_submenu;
$mock_admin_menu    = [];
$mock_admin_submenu = [];

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null ) {
		global $mock_admin_menu;
		$mock_admin_menu[ $menu_slug ] = [
			'page_title' => $page_title,
			'menu_title' => $menu_title,
			'capability' => $capability,
			'callback'   => $function,
			'icon'       => $icon_url,
			'position'   => $position,
		];
		return $menu_slug;
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '', $position = null ) {
		global $mock_admin_submenu;
		$mock_admin_submenu[ $parent_slug ][ $menu_slug ] = [
			'page_title' => $page_title,
			'menu_title' => $menu_title,
			'capability' => $capability,
			'callback'   => $function,
			'position'   => $position,
		];
		return $menu_slug;
	}
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12 ) {
		return bin2hex( random_bytes( (int) ceil( $length / 2 ) ) );
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}
}

// Assets & UI
$GLOBALS['mock_styles']    = [];
$GLOBALS['mock_scripts']   = [];
$GLOBALS['mock_enqueued']  = [];
$GLOBALS['mock_localized'] = [];

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, $src, $deps = [], $ver = false, $media = 'all' ) {
		$GLOBALS['mock_styles'][ $handle ] = $src;
	}
}
global $mock_enqueued_styles, $mock_enqueued_scripts;
$mock_enqueued_styles         = [];
$mock_enqueued_scripts        = [];

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = [], $ver = false, $media = 'all' ) {
		$GLOBALS['mock_enqueued'][ $handle ]        = true;
		$GLOBALS['mock_enqueued_styles'][ $handle ] = $src;
	}
}
if ( ! function_exists( 'wp_add_inline_style' ) ) {
	function wp_add_inline_style( $handle, $data ) {}
}
if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( $handle, $src, $deps = [], $ver = false, $in_footer = false ) {
		$GLOBALS['mock_scripts'][ $handle ] = $src;
	}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = [], $ver = false, $in_footer = false ) {
		$GLOBALS['mock_enqueued'][ $handle ]         = true;
		$GLOBALS['mock_enqueued_scripts'][ $handle ] = $src;
	}
}
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $name, $data ) {
		$GLOBALS['mock_localized'][ $handle ] = [
			'name' => $name,
			'data' => $data,
		];
	}
}
if ( ! function_exists( 'wp_unique_id' ) ) {
	function wp_unique_id( $prefix = '' ) {
		static $id = 0;
		return $prefix . ( ++$id );
	}
}
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['mock_shortcodes'][ $tag ] = $callback;
	}
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return false; }
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
}
if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $sql ) { return [ 'created' => true ]; }
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) { return true; }
}

// Mail
if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = [] ) {
		$GLOBALS['mock_wp_mail_sent'][] = [
			'to'          => $to,
			'subject'     => $subject,
			'message'     => $message,
			'headers'     => $headers,
			'attachments' => $attachments,
		];
		return true;
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302 ) {
		global $test_last_redirect;
		$test_last_redirect = $location;
		return true;
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
if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $keys, $url = '' ) {
		$parsed = parse_url( $url );
		$query = [];
		if ( isset( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query );
		}
		foreach ( (array) $keys as $k ) {
			unset( $query[ $k ] );
		}
		$base = ( isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' . $parsed['host'] : '' ) . ( $parsed['path'] ?? '' );
		return empty( $query ) ? $base : $base . '?' . http_build_query( $query );
	}
}

// Load core plugin files
require_once GCA_PLUGIN_DIR . 'gemini-chat-assistant.php';
\SkyFish\GeminiChat\Plugin::get_instance();
