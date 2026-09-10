<?php
/**
 * Standalone Test Suite for Public UI & Template (Node N6).
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

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = [] ) {
		return array_merge( $defaults, is_array( $args ) ? $args : [] );
	}
}

if ( ! function_exists( 'wp_unique_id' ) ) {
	function wp_unique_id( $prefix = '' ) {
		static $id = 0;
		return $prefix . ( ++$id );
	}
}

global $mock_options;
$mock_options = [];

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $mock_options;
		return $mock_options[ $option ] ?? $default;
	}
}

require_once __DIR__ . '/../includes/Admin/SettingsService.php';

use SkyFish\GeminiChat\Admin\SettingsService;

class PublicUITest {

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
		echo "Running Node N6 Public UI Tests\n";
		echo "========================================\n\n";

		$this->test_floating_template_structure();
		$this->test_embedded_template_structure();
		$this->test_escaping_in_template();
		$this->test_css_scoping_rules();
		$this->test_js_security_and_dom_safety();

		echo "\n========================================\n";
		echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
		echo "========================================\n";

		return 0 === $this->failed;
	}

	private function test_floating_template_structure(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [
			'assistant_name'  => 'SkyBot',
			'greeting'        => 'Hello Traveler',
			'welcome_message' => 'How can I assist you?',
			'placeholder'     => 'Ask anything...',
		];

		$mode        = 'floating';
		$instance_id = 'gca-floating-test';

		ob_start();
		include __DIR__ . '/../templates/chat-widget.php';
		$html = (string) ob_get_clean();

		$this->assert( false !== strpos( $html, 'class="gca-launcher"' ), 'Floating launcher button rendered' );
		$this->assert( false !== strpos( $html, 'class="gca-widget gca-widget--floating"' ), 'Floating widget container rendered' );
		$this->assert( false !== strpos( $html, 'SkyBot' ), 'Assistant name rendered in header' );
		$this->assert( false !== strpos( $html, 'Hello Traveler' ), 'Greeting headline rendered' );
		$this->assert( false !== strpos( $html, 'gca-start-card' ), 'Start Conversation card rendered' );
		$this->assert( false !== strpos( $html, 'gca-nav' ), 'Navigation bar rendered' );
		$this->assert( false !== strpos( $html, 'gca-composer' ), 'Composer form rendered' );
	}

	private function test_embedded_template_structure(): void {
		$mode        = 'embedded';
		$instance_id = 'gca-embedded-test';

		ob_start();
		include __DIR__ . '/../templates/chat-widget.php';
		$html = (string) ob_get_clean();

		$this->assert( false === strpos( $html, 'class="gca-launcher"' ), 'Embedded mode omits launcher' );
		$this->assert( false !== strpos( $html, 'class="gca-widget gca-widget--embedded"' ), 'Embedded widget container rendered' );
		$this->assert( false === strpos( $html, 'gca-btn-close' ), 'Embedded mode omits close button' );
	}

	private function test_escaping_in_template(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [
			'assistant_name'  => '<script>alert("xss")</script>Bot',
			'greeting'        => '<b>Welcome</b>',
			'welcome_message' => '<i>Ready?</i>',
			'placeholder'     => '" onclick="evil()',
		];

		$mode        = 'embedded';
		$instance_id = 'gca-escaping-test';

		ob_start();
		include __DIR__ . '/../templates/chat-widget.php';
		$html = (string) ob_get_clean();

		$this->assert( false === strpos( $html, '<script>alert("xss")</script>' ), 'Script tag escaped in assistant name' );
		$this->assert( false !== strpos( $html, '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;Bot' ), 'Escaped entity found' );
		$this->assert( false === strpos( $html, '" onclick="evil()' ), 'Attribute injection escaped in placeholder' );
	}

	private function test_css_scoping_rules(): void {
		$css_file = __DIR__ . '/../public/css/chat.css';
		$this->assert( file_exists( $css_file ), 'chat.css exists' );

		$css_content = file_get_contents( $css_file );

		// Ensure no unscoped global element selectors e.g. "button {", "textarea {", "input {"
		$this->assert( false === strpos( $css_content, "\nbutton {" ), 'No global button {} selector in chat.css' );
		$this->assert( false === strpos( $css_content, "\ntextarea {" ), 'No global textarea {} selector in chat.css' );
		$this->assert( false === strpos( $css_content, "\ninput {" ), 'No global input {} selector in chat.css' );
		$this->assert( false === strpos( $css_content, "\np {" ), 'No global p {} selector in chat.css' );
		$this->assert( false !== strpos( $css_content, '.gca-widget' ), '.gca-widget class present' );
		$this->assert( false !== strpos( $css_content, '.gca-launcher' ), '.gca-launcher class present' );

		// UI Polish & Overflow Verification
		$this->assert( false !== strpos( $css_content, '.gca-header__titles' ), '.gca-header__titles class present in CSS' );
		$this->assert( false !== strpos( $css_content, 'overflow-x: hidden' ), 'overflow-x hidden applied to prevent horizontal scroll' );
		$this->assert( false !== strpos( $css_content, '.gca-start-card__desc' ), '.gca-start-card__desc styled for multiline containment' );
		$this->assert( false !== strpos( $css_content, '.gca-nav__btn--active::before' ), '.gca-nav active indicator styled' );
	}

	private function test_js_security_and_dom_safety(): void {
		$js_file = __DIR__ . '/../public/js/chat.js';
		$this->assert( file_exists( $js_file ), 'chat.js exists' );

		$js_content = file_get_contents( $js_file );

		$this->assert( false === strpos( $js_content, 'generativelanguage.googleapis.com' ), 'JS does not call Google Gemini directly' );
		$this->assert( false === strpos( $js_content, 'eval(' ), 'JS contains no eval()' );
		$this->assert( false === strpos( $js_content, 'document.write' ), 'JS contains no document.write' );
		$this->assert( false !== strpos( $js_content, 'textContent = text' ), 'JS uses safe textContent for message rendering' );
		$this->assert( false !== strpos( $js_content, 'sessionToken' ), 'JS manages sessionToken' );
	}
}

if ( 'cli' === php_sapi_name() || ! defined( 'WPINC' ) ) {
	$suite = new PublicUITest();
	$suite->run_all();
}
