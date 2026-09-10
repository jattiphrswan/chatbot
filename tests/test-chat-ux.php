<?php
/**
 * Standalone Test Suite for Full Chat UX (Node N9).
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

require_once __DIR__ . '/bootstrap.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! defined( 'GCA_PLUGIN_DIR' ) ) {
	define( 'GCA_PLUGIN_DIR', __DIR__ . '/../' );
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

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
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

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'https://example.com/wp-json/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_unique_id' ) ) {
	function wp_unique_id( $prefix = '' ) {
		return $prefix . 'test_uniq_123';
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
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
require_once __DIR__ . '/../includes/class-assets.php';

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Assets;

/**
 * Class ChatUxTest
 */
class ChatUxTest {

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
		echo "Running Node N9 Full Chat UX Tests\n";
		echo "========================================\n\n";

		$this->test_javascript_security_and_safeties();
		$this->test_javascript_ux_state_model();
		$this->test_stylesheet_rules_and_reduced_motion();
		$this->test_floating_template_elements();
		$this->test_embedded_template_elements();
		$this->test_localized_assets_configuration();

		echo "\n========================================\n";
		echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
		echo "========================================\n";

		return 0 === $this->failed;
	}

	private function test_javascript_security_and_safeties(): void {
		$js_path = __DIR__ . '/../public/js/chat.js';
		$this->assert( file_exists( $js_path ), 'public/js/chat.js exists' );
		$js_code = file_get_contents( $js_path );

		// Strict XSS checks
		$this->assert( false === strpos( $js_code, '.innerHTML' ), 'Strict XSS Check: Zero innerHTML used in chat.js' );
		$this->assert( false === strpos( $js_code, 'document.write' ), 'Strict XSS Check: Zero document.write in chat.js' );
		$this->assert( false === strpos( $js_code, 'eval(' ), 'Strict XSS Check: Zero eval() in chat.js' );
		$this->assert( false === strpos( $js_code, 'new Function' ), 'Strict XSS Check: Zero new Function in chat.js' );

		// IME Keyboard composition check
		$this->assert( false !== strpos( $js_code, 'isComposing' ), 'IME safety: isComposing flag handled in keyboard handler' );
	}

	private function test_javascript_ux_state_model(): void {
		$js_code = file_get_contents( __DIR__ . '/../public/js/chat.js' );

		$this->assert( false !== strpos( $js_code, 'activeScreen' ), 'State model contains activeScreen' );
		$this->assert( false !== strpos( $js_code, 'isSending' ), 'State model contains isSending' );
		$this->assert( false !== strpos( $js_code, 'unreadCount' ), 'State model contains unreadCount' );
		$this->assert( false !== strpos( $js_code, 'startRateLimitCountdown' ), 'Rate limit countdown handler present' );
		$this->assert( false !== strpos( $js_code, 'lastFailedMessage' ), 'Retry message state present' );
		$this->assert( false !== strpos( $js_code, 'scrollToBottom' ), 'Autoscroll handler present' );
		$this->assert( false !== strpos( $js_code, 'showResetConfirmation' ), 'Reset confirmation flow present' );
	}

	private function test_stylesheet_rules_and_reduced_motion(): void {
		$css_path = __DIR__ . '/../public/css/chat.css';
		$this->assert( file_exists( $css_path ), 'public/css/chat.css exists' );
		$css_code = file_get_contents( $css_path );

		$this->assert( false !== strpos( $css_code, '.gca-launcher__badge' ), 'CSS contains .gca-launcher__badge' );
		$this->assert( false !== strpos( $css_code, '.gca-scroll-bottom' ), 'CSS contains .gca-scroll-bottom' );
		$this->assert( false !== strpos( $css_code, '.gca-confirm-dialog' ), 'CSS contains .gca-confirm-dialog' );
		$this->assert( false !== strpos( $css_code, '.gca-error-notice__retry' ), 'CSS contains .gca-error-notice__retry' );
		$this->assert( false !== strpos( $css_code, 'prefers-reduced-motion: reduce' ), 'CSS contains prefers-reduced-motion media query' );
		$this->assert( false !== strpos( $css_code, '100dvh' ), 'CSS contains 100dvh mobile viewport unit' );
	}

	private function test_floating_template_elements(): void {
		ob_start();
		$mode        = 'floating';
		$instance_id = 'test-float-widget';
		include __DIR__ . '/../templates/chat-widget.php';
		$html = ob_get_clean();

		$this->assert( false !== strpos( $html, 'class="gca-launcher"' ), 'Floating template renders launcher button' );
		$this->assert( false !== strpos( $html, 'class="gca-launcher__badge"' ), 'Floating template contains unread badge element' );
		$this->assert( false !== strpos( $html, 'class="gca-confirm-dialog"' ), 'Floating template contains confirm dialog overlay' );
		$this->assert( false !== strpos( $html, 'class="gca-scroll-bottom"' ), 'Floating template contains scroll-to-bottom button' );
		$this->assert( false !== strpos( $html, 'class="gca-error-notice__retry"' ), 'Floating template contains error retry button' );
		$this->assert( false !== strpos( $html, 'aria-current="page"' ), 'Home tab initialized with aria-current="page"' );
	}

	private function test_embedded_template_elements(): void {
		ob_start();
		$mode        = 'embedded';
		$instance_id = 'test-embed-widget';
		include __DIR__ . '/../templates/chat-widget.php';
		$html = ob_get_clean();

		$this->assert( false === strpos( $html, 'class="gca-launcher"' ), 'Embedded template omits floating launcher' );
		$this->assert( false === strpos( $html, 'class="gca-btn-action gca-btn-close"' ), 'Embedded template omits close button' );
		$this->assert( false !== strpos( $html, 'class="gca-widget gca-widget--embedded"' ), 'Embedded widget wrapper class present' );
	}

	private function test_localized_assets_configuration(): void {
		$config = Assets::get_localized_config();

		$this->assert( isset( $config['restUrl'] ), 'restUrl present in config' );
		$this->assert( isset( $config['i18n']['tryAgain'] ), 'tryAgain localized string present' );
		$this->assert( isset( $config['i18n']['confirmReset'] ), 'confirmReset localized string present' );
		$this->assert( isset( $config['i18n']['rateLimited'] ), 'rateLimited localized string present' );
		$this->assert( ! isset( $config['api_key'] ), 'API key never exposed in localized config' );
	}
}

if ( 'cli' === php_sapi_name() || ! defined( 'WPINC' ) ) {
	$suite = new ChatUxTest();
	$suite->run_all();
}
