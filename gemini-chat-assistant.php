<?php
/**
 * Plugin Name:       Gemini Chat Assistant
 * Plugin URI:        https://github.com/SkyFish/gemini-chat-assistant
 * Description:       Enterprise-grade WordPress AI Chat Assistant powered by Google Gemini API.
 * Version:           1.0.1
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
define( 'GCA_VERSION', '1.0.1' );
define( 'GCA_PLUGIN_FILE', __FILE__ );
define( 'GCA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GCA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GCA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'GCA_REST_NAMESPACE', 'gca/v1' );
define( 'GCA_SHORTCODE', 'gemini_chat' );

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
