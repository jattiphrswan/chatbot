<?php
/**
 * The core plugin class.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

use SkyFish\GeminiChat\Providers\ClaudeProvider;
use SkyFish\GeminiChat\Providers\GeminiProvider;
use SkyFish\GeminiChat\Providers\OpenAIProvider;
use SkyFish\GeminiChat\Providers\ProviderRegistry;

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
	 * The AI provider registry.
	 *
	 * @var ProviderRegistry|null
	 */
	private ?ProviderRegistry $provider_registry = null;

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
		$this->init_providers();
	}

	/**
	 * Loads required dependencies.
	 */
	private function load_dependencies(): void {
		// Load Provider Abstraction.
		require_once GCA_PLUGIN_DIR . 'includes/Providers/ProviderInterface.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/ProviderResponse.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/ProviderException.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/ProviderRegistry.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/GeminiProvider.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/OpenAIProvider.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/ClaudeProvider.php';
	}

	/**
	 * Initializes and registers default AI providers.
	 */
	private function init_providers(): void {
		if ( null !== $this->provider_registry ) {
			return;
		}

		$this->provider_registry = new ProviderRegistry();
		$this->provider_registry->register( new GeminiProvider() );
		$this->provider_registry->register( new OpenAIProvider() );
		$this->provider_registry->register( new ClaudeProvider() );
	}

	/**
	 * Safe accessor to the AI provider registry.
	 *
	 * @return ProviderRegistry
	 */
	public function get_provider_registry(): ProviderRegistry {
		if ( null === $this->provider_registry ) {
			$this->init_providers();
		}
		return $this->provider_registry;
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
