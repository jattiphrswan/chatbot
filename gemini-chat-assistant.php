<?php
/**
 * Plugin Name:       Gemini Chat Assistant
 * Plugin URI:        https://github.com/SkyFish/gemini-chat-assistant
 * Description:       Enterprise-grade WordPress AI Chat Assistant powered by Google Gemini API.
 * Version:           1.0.3
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            SkyFish
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gemini-chat-assistant
 * Domain Path:       /languages
 *
 * @package           SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
if ( ! defined( 'GCA_VERSION' ) ) {
	define( 'GCA_VERSION', '1.0.3' );
}
if ( ! defined( 'GCA_PLUGIN_FILE' ) ) {
	define( 'GCA_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'GCA_PLUGIN_DIR' ) ) {
	define( 'GCA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'GCA_PLUGIN_URL' ) ) {
	define( 'GCA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'GCA_PLUGIN_BASENAME' ) ) {
	define( 'GCA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'GCA_REST_NAMESPACE' ) ) {
	define( 'GCA_REST_NAMESPACE', 'gca/v1' );
}
if ( ! defined( 'GCA_SHORTCODE' ) ) {
	define( 'GCA_SHORTCODE', 'gemini_chat' );
}

// Register PSR-4 / class autoloader for SkyFish\GeminiChat namespace.
spl_autoload_register( function ( string $class ): void {
	$prefix = 'SkyFish\\GeminiChat\\';
	$base_dir = GCA_PLUGIN_DIR . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );

	// 1. Direct path matching subnamespaces (e.g. Database\ConversationRepository -> includes/Database/ConversationRepository.php).
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
		return;
	}

	// 2. Class prefixed files in subdirectories (e.g. Security\SecretStore -> includes/security/class-secret-store.php).
	$parts      = explode( '\\', $relative_class );
	$class_name = array_pop( $parts );
	$kebab      = strtolower( (string) preg_replace( '/([a-zA-Z])(?=[A-Z])/', '$1-', $class_name ) );
	$sub_dir    = ! empty( $parts ) ? strtolower( implode( '/', $parts ) ) . '/' : '';
	$prefixed   = $base_dir . $sub_dir . 'class-' . $kebab . '.php';
	if ( file_exists( $prefixed ) ) {
		require_once $prefixed;
		return;
	}

	// 3. Legacy root classes (e.g. ChatService -> includes/class-chat-service.php).
	$legacy = $base_dir . 'class-' . $kebab . '.php';
	if ( file_exists( $legacy ) ) {
		require_once $legacy;
		return;
	}
} );

// Require core class files.
require_once GCA_PLUGIN_DIR . 'includes/class-activator.php';
require_once GCA_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once GCA_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * The code that runs during plugin activation.
 */
function activate_gemini_chat_assistant(): void {
	Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_gemini_chat_assistant(): void {
	Deactivator::deactivate();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate_gemini_chat_assistant' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate_gemini_chat_assistant' );

/**
 * Begins execution of the plugin.
 */
function run_gemini_chat_assistant(): void {
	$plugin = Plugin::get_instance();
	$plugin->run();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\run_gemini_chat_assistant' );
