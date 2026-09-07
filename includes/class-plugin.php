<?php
/**
 * The core plugin class.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

use SkyFish\GeminiChat\Admin\AdminMenu;
use SkyFish\GeminiChat\Admin\ProfileService;
use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Database\ConversationRepository;
use SkyFish\GeminiChat\Database\MessageRepository;
use SkyFish\GeminiChat\Database\Migrator;
use SkyFish\GeminiChat\Database\SessionService;
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
	 * The Admin Menu coordinator.
	 *
	 * @var AdminMenu|null
	 */
	private ?AdminMenu $admin_menu = null;

	/**
	 * Session service instance.
	 *
	 * @var SessionService|null
	 */
	private ?SessionService $session_service = null;

	/**
	 * Conversation repository instance.
	 *
	 * @var ConversationRepository|null
	 */
	private ?ConversationRepository $conversation_repo = null;

	/**
	 * Message repository instance.
	 *
	 * @var MessageRepository|null
	 */
	private ?MessageRepository $message_repo = null;

	/**
	 * Lead repository instance.
	 *
	 * @var LeadRepository|null
	 */
	private ?LeadRepository $lead_repo = null;

	/**
	 * Lead service instance.
	 *
	 * @var LeadService|null
	 */
	private ?LeadService $lead_service = null;

	/**
	 * AI profile service instance.
	 *
	 * @var ProfileService|null
	 */
	private ?ProfileService $profile_service = null;

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
		// Database & Repositories.
		require_once GCA_PLUGIN_DIR . 'includes/Database/Migrator.php';
		require_once GCA_PLUGIN_DIR . 'includes/Database/ConversationRepository.php';
		require_once GCA_PLUGIN_DIR . 'includes/Database/MessageRepository.php';
		require_once GCA_PLUGIN_DIR . 'includes/Database/LeadRepository.php';
		require_once GCA_PLUGIN_DIR . 'includes/Database/AnalyticsRepository.php';
		require_once GCA_PLUGIN_DIR . 'includes/Database/SessionService.php';

		// Admin & Settings Services.
		require_once GCA_PLUGIN_DIR . 'includes/Admin/SettingsService.php';
		require_once GCA_PLUGIN_DIR . 'includes/Admin/ProfileService.php';
		require_once GCA_PLUGIN_DIR . 'includes/Admin/AnalyticsService.php';
		require_once GCA_PLUGIN_DIR . 'includes/Admin/AppearanceService.php';
		require_once GCA_PLUGIN_DIR . 'includes/Admin/AdminMenu.php';

		// Load Gemini API Client.
		require_once GCA_PLUGIN_DIR . 'includes/class-gemini-client.php';

		// REST API & Application Services.
		require_once GCA_PLUGIN_DIR . 'includes/class-rate-limiter.php';
		require_once GCA_PLUGIN_DIR . 'includes/class-validator.php';
		require_once GCA_PLUGIN_DIR . 'includes/class-chat-service.php';
		require_once GCA_PLUGIN_DIR . 'includes/class-lead-service.php';
		require_once GCA_PLUGIN_DIR . 'includes/class-rest-controller.php';

		// Public Frontend UI & Shortcode.
		require_once GCA_PLUGIN_DIR . 'includes/class-assets.php';
		require_once GCA_PLUGIN_DIR . 'includes/class-shortcode.php';

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
	 * Gemini API client instance.
	 *
	 * @var GeminiClient|null
	 */
	private ?GeminiClient $gemini_client = null;

	/**
	 * Chat orchestration service instance.
	 *
	 * @var ChatService|null
	 */
	private ?ChatService $chat_service = null;

	/**
	 * REST API controller instance.
	 *
	 * @var RestController|null
	 */
	private ?RestController $rest_controller = null;

	/**
	 * Rate limiter instance.
	 *
	 * @var RateLimiter|null
	 */
	private ?RateLimiter $rate_limiter = null;

	/**
	 * Frontend assets loader instance.
	 *
	 * @var Assets|null
	 */
	private ?Assets $assets = null;

	/**
	 * Public shortcode handler instance.
	 *
	 * @var Shortcode|null
	 */
	private ?Shortcode $shortcode = null;

	/**
	 * Accessor to Assets loader.
	 *
	 * @return Assets
	 */
	public function get_assets(): Assets {
		if ( null === $this->assets ) {
			$this->assets = new Assets();
		}
		return $this->assets;
	}

	/**
	 * Accessor to Shortcode handler.
	 *
	 * @return Shortcode
	 */
	public function get_shortcode(): Shortcode {
		if ( null === $this->shortcode ) {
			$this->shortcode = new Shortcode();
		}
		return $this->shortcode;
	}

	/**
	 * Accessor to RateLimiter.
	 *
	 * @return RateLimiter
	 */
	public function get_rate_limiter(): RateLimiter {
		if ( null === $this->rate_limiter ) {
			$this->rate_limiter = new RateLimiter( SettingsService::get_instance() );
		}
		return $this->rate_limiter;
	}

	/**
	 * Accessor to GeminiClient.
	 *
	 * @return GeminiClient
	 */
	public function get_gemini_client(): GeminiClient {
		if ( null === $this->gemini_client ) {
			$this->gemini_client = new GeminiClient();
		}
		return $this->gemini_client;
	}

	/**
	 * Accessor to ProfileService.
	 *
	 * @return ProfileService
	 */
	public function get_profile_service(): ProfileService {
		if ( null === $this->profile_service ) {
			$this->profile_service = new ProfileService( SettingsService::get_instance() );
		}
		return $this->profile_service;
	}

	/**
	 * Accessor to ChatService.
	 *
	 * @return ChatService
	 */
	public function get_chat_service(): ChatService {
		if ( null === $this->chat_service ) {
			$this->chat_service = new ChatService(
				SettingsService::get_instance(),
				$this->get_session_service(),
				$this->get_conversation_repository(),
				$this->get_message_repository(),
				$this->get_gemini_client(),
				$this->get_profile_service()
			);
		}
		return $this->chat_service;
	}

	/**
	 * Accessor to RestController.
	 *
	 * @return RestController
	 */
	public function get_rest_controller(): RestController {
		if ( null === $this->rest_controller ) {
			$this->rest_controller = new RestController(
				SettingsService::get_instance(),
				$this->get_chat_service(),
				$this->get_gemini_client(),
				$this->get_rate_limiter(),
				$this->get_lead_service()
			);
		}
		return $this->rest_controller;
	}

	/**
	 * Accessor to LeadRepository.
	 *
	 * @return LeadRepository
	 */
	public function get_lead_repository(): LeadRepository {
		if ( null === $this->lead_repo ) {
			$this->lead_repo = new LeadRepository();
		}
		return $this->lead_repo;
	}

	/**
	 * Accessor to LeadService.
	 *
	 * @return LeadService
	 */
	public function get_lead_service(): LeadService {
		if ( null === $this->lead_service ) {
			$this->lead_service = new LeadService(
				SettingsService::get_instance(),
				$this->get_session_service(),
				$this->get_conversation_repository(),
				$this->get_lead_repository()
			);
		}
		return $this->lead_service;
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
	 * Accessor to SessionService.
	 *
	 * @return SessionService
	 */
	public function get_session_service(): SessionService {
		if ( null === $this->session_service ) {
			$this->session_service = new SessionService(
				$this->get_conversation_repository(),
				$this->get_message_repository()
			);
		}
		return $this->session_service;
	}

	/**
	 * Accessor to ConversationRepository.
	 *
	 * @return ConversationRepository
	 */
	public function get_conversation_repository(): ConversationRepository {
		if ( null === $this->conversation_repo ) {
			$this->conversation_repo = new ConversationRepository();
		}
		return $this->conversation_repo;
	}

	/**
	 * Accessor to MessageRepository.
	 *
	 * @return MessageRepository
	 */
	public function get_message_repository(): MessageRepository {
		if ( null === $this->message_repo ) {
			$this->message_repo = new MessageRepository();
		}
		return $this->message_repo;
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
		if ( is_admin() ) {
			$this->admin_menu = new AdminMenu();
			$this->admin_menu->init();
		}

		$this->get_rest_controller()->init();
		$this->get_assets()->init();
		$this->get_shortcode()->init();

		add_action( 'wp_footer', [ $this, 'render_floating_widget' ] );
	}

	/**
	 * Renders the floating chat widget in the footer of public pages.
	 */
	public function render_floating_widget(): void {
		if ( is_admin() ) {
			return;
		}

		$enabled        = (bool) SettingsService::get( 'enabled', true );
		$widget_enabled = (bool) SettingsService::get( 'widget_enabled', true );

		if ( ! $enabled || ! $widget_enabled ) {
			return;
		}

		$mode        = 'floating';
		$instance_id = 'gca-floating-widget';

		$template_path = GCA_PLUGIN_DIR . 'templates/chat-widget.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}
}
