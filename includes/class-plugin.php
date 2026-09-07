<?php
/**
 * The core plugin class.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin coordinator class.
 */
class Plugin {

	/**
	 * The unique instance of the plugin.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Gets the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor for singleton pattern.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->set_locale();
	}

	/**
	 * Loads required dependencies.
	 */
	private function load_dependencies(): void {
		// Future node classes will be loaded here.
	}

	/**
	 * Defines the locale for this plugin for internationalization.
	 */
	private function set_locale(): void {
		add_action( 'init', [ $this, 'load_plugin_textdomain' ] );
	}

	/**
	 * Loads the plugin text domain for translation.
	 */
	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			'gemini-chat-assistant',
			false,
			dirname( GCA_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Runs the loader to execute all of the hooks with WordPress.
	 */
	public function run(): void {
		// Hook registrations for upcoming nodes.
	}
}
