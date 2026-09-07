<?php
/**
 * Standalone Test Suite for Admin Dashboard Shell (Node N10).
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

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.com/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $echo = true ) {
		$result = ( (string) $checked === (string) $current ) ? 'checked="checked"' : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = 'Save Changes' ) {
		echo '<input type="submit" value="' . esc_attr( $text ) . '" class="button button-primary" />';
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( $group ) {
		echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '" />';
	}
}

global $mock_admin_menu, $mock_admin_submenu, $mock_enqueued_styles, $mock_enqueued_scripts;
$mock_admin_menu       = [];
$mock_admin_submenu    = [];
$mock_enqueued_styles  = [];
$mock_enqueued_scripts = [];

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

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = [], $ver = false, $media = 'all' ) {
		global $mock_enqueued_styles;
		$mock_enqueued_styles[ $handle ] = $src;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = [], $ver = false, $in_footer = false ) {
		global $mock_enqueued_scripts;
		$mock_enqueued_scripts[ $handle ] = $src;
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( $group, $name, $args = [] ) {
		return true;
	}
}

global $mock_current_caps;
$mock_current_caps = [];

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) {
		global $mock_current_caps;
		return ! empty( $mock_current_caps[ $cap ] );
	}
}

// Mock WordPress options.
global $mock_options;
$mock_options = [];

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $mock_options;
		return $mock_options[ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) {
		global $mock_options;
		$mock_options[ $option ] = $value;
		return true;
	}
}

require_once __DIR__ . '/../includes/Admin/SettingsService.php';
require_once __DIR__ . '/../includes/Admin/AdminMenu.php';

use SkyFish\GeminiChat\Admin\AdminMenu;
use SkyFish\GeminiChat\Admin\SettingsService;

/**
 * Class DashboardTest
 */
class DashboardTest {

	private int $passed = 0;
	private int $failed = 0;

	private function assert( bool $condition, string $test_name ): void {
		if ( $condition ) {
			$this->passed++;
			echo "[PASS] {$test_name}\n";
		} else {
			$this->failed++;
			echo "[FAIL] {$test_name}\n";
		}
	}

	public function run_all(): bool {
		echo "========================================\n";
		echo "Running Node N10 Admin Dashboard Tests\n";
		echo "========================================\n\n";

		$this->test_menu_registration();
		$this->test_asset_enqueueing_rules();
		$this->test_dashboard_template_rendering_configured();
		$this->test_dashboard_template_rendering_unconfigured();
		$this->test_capability_protection();
		$this->test_no_fake_analytics();

		echo "\n========================================\n";
		echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
		echo "========================================\n";

		return 0 === $this->failed;
	}

	private function reset_env(): void {
		global $mock_admin_menu, $mock_admin_submenu, $mock_enqueued_styles, $mock_enqueued_scripts, $mock_current_caps, $mock_options;
		$mock_admin_menu       = [];
		$mock_admin_submenu    = [];
		$mock_enqueued_styles  = [];
		$mock_enqueued_scripts = [];
		$mock_current_caps     = [ 'manage_options' => true ];
		$mock_options          = [];
	}

	private function test_menu_registration(): void {
		$this->reset_env();
		global $mock_admin_menu, $mock_admin_submenu;

		$menu = new AdminMenu();
		$menu->register_menu();

		$this->assert( isset( $mock_admin_menu['gemini-chat-assistant'] ), 'Top-level menu gemini-chat-assistant registered' );
		$this->assert( 'dashicons-format-chat' === $mock_admin_menu['gemini-chat-assistant']['icon'], 'Top-level menu icon is dashicons-format-chat' );
		$this->assert( 'manage_options' === $mock_admin_menu['gemini-chat-assistant']['capability'], 'Top-level requires manage_options capability' );

		// Submenus
		$this->assert( isset( $mock_admin_submenu['gemini-chat-assistant']['gemini-chat-assistant'] ), 'Dashboard submenu registered' );
		$this->assert( isset( $mock_admin_submenu['gemini-chat-assistant']['gca-settings'] ), 'Settings submenu registered' );
		$this->assert( 'manage_options' === $mock_admin_submenu['gemini-chat-assistant']['gca-settings']['capability'], 'Settings submenu requires manage_options' );
	}

	private function test_asset_enqueueing_rules(): void {
		$this->reset_env();
		global $mock_enqueued_styles, $mock_enqueued_scripts;

		$menu = new AdminMenu();

		// Should NOT enqueue on unrelated admin pages (e.g. edit.php)
		$menu->enqueue_assets( 'edit.php' );
		$this->assert( empty( $mock_enqueued_styles ), 'Admin styles NOT loaded on unrelated admin screens (edit.php)' );

		// Should enqueue on top-level plugin page
		$menu->enqueue_assets( 'toplevel_page_gemini-chat-assistant' );
		$this->assert( isset( $mock_enqueued_styles['gca-admin-styles'] ), 'Admin styles loaded on toplevel_page_gemini-chat-assistant' );

		// Should enqueue on settings page
		$menu->enqueue_assets( 'gemini-chat_page_gca-settings' );
		$this->assert( isset( $mock_enqueued_styles['gca-admin-styles'] ), 'Admin styles loaded on gemini-chat_page_gca-settings' );
	}

	private function test_dashboard_template_rendering_configured(): void {
		$this->reset_env();
		global $mock_options;

		$mock_options['gca_settings'] = [
			'enabled'        => true,
			'widget_enabled' => true,
			'model'          => 'gemini-3.8-flash',
			'store_messages' => true,
			'guest_access'   => true,
		];

		$settings      = SettingsService::get_all();
		$is_configured = true;
		$model         = SettingsService::get_model();
		$db_version    = '1.0.0';
		$settings_url  = 'https://example.com/wp-admin/admin.php?page=gca-settings';

		ob_start();
		include __DIR__ . '/../templates/admin/dashboard.php';
		$html = ob_get_clean();

		$this->assert( false !== strpos( $html, 'Gemini Chat Assistant' ), 'Dashboard title rendered' );
		$this->assert( false !== strpos( $html, 'v1.0.0' ), 'Plugin version rendered' );
		$this->assert( false !== strpos( $html, 'gemini-3.8-flash' ), 'Configured model slug displayed' );
		$this->assert( false !== strpos( $html, 'gca-admin-pill--success' ), 'Success pills rendered when configured' );
		$this->assert( false !== strpos( $html, 'Setup &amp; Verification Checklist' ) || false !== strpos( $html, 'Setup & Verification Checklist' ), 'Setup checklist rendered' );
		$this->assert( false !== strpos( $html, 'admin.php?page=gca-settings' ), 'Link to settings screen present' );
	}

	private function test_dashboard_template_rendering_unconfigured(): void {
		$this->reset_env();
		$settings      = SettingsService::get_all();
		$is_configured = false; // API key missing
		$model         = 'gemini-3.8-flash';
		$db_version    = '1.0.0';
		$settings_url  = 'https://example.com/wp-admin/admin.php?page=gca-settings';

		ob_start();
		include __DIR__ . '/../templates/admin/dashboard.php';
		$html = ob_get_clean();

		$this->assert( false !== strpos( $html, 'Action Required:' ), 'Action Required notice shown when API key is missing' );
		$this->assert( false !== strpos( $html, 'Missing Key' ), 'Missing Key status pill rendered' );
		$this->assert( false === strpos( $html, 'AIzaSy' ), 'Zero real or fake API keys present in rendered HTML' );
	}

	private function test_capability_protection(): void {
		$this->reset_env();
		global $mock_current_caps;

		$mock_current_caps = [ 'manage_options' => false ];
		$this->assert( false === current_user_can( 'manage_options' ), 'User without manage_options capability denied access' );
	}

	private function test_no_fake_analytics(): void {
		$template_code = file_get_contents( __DIR__ . '/../templates/admin/dashboard.php' );

		// Verify no fake metrics or charts
		$this->assert( false === strpos( $template_code, '1,245' ), 'Zero fake conversation counts' );
		$this->assert( false === strpos( $template_code, '893' ), 'Zero fake lead numbers' );
		$this->assert( false === strpos( $template_code, '<canvas' ), 'Zero premature Chart.js canvas elements' );
		$this->assert( false !== strpos( $template_code, 'Roadmap Capabilities' ), 'Roadmap future modules clearly demarcated' );
	}
}

if ( 'cli' === php_sapi_name() || ! defined( 'WPINC' ) ) {
	$suite = new DashboardTest();
	$suite->run_all();
}
