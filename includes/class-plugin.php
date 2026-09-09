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
use SkyFish\GeminiChat\Database\LeadRepository;
use SkyFish\GeminiChat\Database\MessageRepository;
use SkyFish\GeminiChat\Database\Migrator;
use SkyFish\GeminiChat\Database\SessionService;

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
		require_once GCA_PLUGIN_DIR . 'includes/Database/FaqRepository.php';
		require_once GCA_PLUGIN_DIR . 'includes/Database/KnowledgeRepository.php';

		// Knowledge & RAG Engine (N16).
		require_once GCA_PLUGIN_DIR . 'includes/Knowledge/KnowledgeIndexer.php';
		require_once GCA_PLUGIN_DIR . 'includes/Knowledge/KnowledgeRetriever.php';
		require_once GCA_PLUGIN_DIR . 'includes/Knowledge/KnowledgeContextBuilder.php';

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

		// Business Integrations Framework (N17.1).
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/ActionResult.php';
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/ActionInterface.php';
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/IntegrationInterface.php';
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/ActionValidator.php';
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/IntegrationRegistry.php';
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/ActionExecutor.php';

		// WooCommerce Integration (N17.2).
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/WooCommerce/WooCommerceFormatter.php';
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/WooCommerce/SearchProductsAction.php';
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/WooCommerce/GetProductAction.php';
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/WooCommerce/SearchByCategoryAction.php';
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/WooCommerce/WooCommerceIntegration.php';

		// Human Handoff (N17.3).
		require_once GCA_PLUGIN_DIR . 'includes/Database/HandoffRepository.php';
		require_once GCA_PLUGIN_DIR . 'includes/handoff/class-handoff-service.php';
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/Handoff/CreateHandoffAction.php';
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/Handoff/SendHandoffNotificationAction.php';
		require_once GCA_PLUGIN_DIR . 'includes/Integrations/Handoff/HandoffIntegration.php';

		// Email Notifications (N17.4).
		require_once GCA_PLUGIN_DIR . 'includes/notifications/class-notification-service.php';

		// AI Providers (N16 / N18 / N21).
		require_once GCA_PLUGIN_DIR . 'includes/Providers/ProviderInterface.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/ModelRegistry.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/ProviderResponse.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/ProviderException.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/ProviderRegistry.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/ProviderSelectionService.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/GeminiProvider.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/OpenAIClient.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/OpenAIProvider.php';
		require_once GCA_PLUGIN_DIR . 'includes/Providers/ClaudeClient.php';
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
	 * AI Provider Registry instance (N18).
	 *
	 * @var \SkyFish\GeminiChat\Providers\ProviderRegistry|null
	 */
	private ?\SkyFish\GeminiChat\Providers\ProviderRegistry $provider_registry = null;

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
				$this->get_profile_service(),
				$this->get_knowledge_retriever(),
				$this->get_knowledge_context_builder(),
				$this->get_handoff_service(),
				$this->get_provider_registry(),
				$this->get_provider_selection_service()
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
	 * Accessor to FaqRepository.
	 *
	 * @return \SkyFish\GeminiChat\Database\FaqRepository
	 */
	public function get_faq_repository(): \SkyFish\GeminiChat\Database\FaqRepository {
		static $faq_repo = null;
		if ( null === $faq_repo ) {
			$faq_repo = new \SkyFish\GeminiChat\Database\FaqRepository();
		}
		return $faq_repo;
	}

	/**
	 * Accessor to KnowledgeRepository.
	 *
	 * @return \SkyFish\GeminiChat\Database\KnowledgeRepository
	 */
	public function get_knowledge_repository(): \SkyFish\GeminiChat\Database\KnowledgeRepository {
		static $knowledge_repo = null;
		if ( null === $knowledge_repo ) {
			$knowledge_repo = new \SkyFish\GeminiChat\Database\KnowledgeRepository();
		}
		return $knowledge_repo;
	}

	/**
	 * Accessor to KnowledgeIndexer.
	 *
	 * @return \SkyFish\GeminiChat\Knowledge\KnowledgeIndexer
	 */
	public function get_knowledge_indexer(): \SkyFish\GeminiChat\Knowledge\KnowledgeIndexer {
		static $indexer = null;
		if ( null === $indexer ) {
			$indexer = new \SkyFish\GeminiChat\Knowledge\KnowledgeIndexer(
				$this->get_knowledge_repository(),
				$this->get_faq_repository()
			);
		}
		return $indexer;
	}

	/**
	 * Accessor to KnowledgeRetriever.
	 *
	 * @return \SkyFish\GeminiChat\Knowledge\KnowledgeRetriever
	 */
	public function get_knowledge_retriever(): \SkyFish\GeminiChat\Knowledge\KnowledgeRetriever {
		static $retriever = null;
		if ( null === $retriever ) {
			$retriever = new \SkyFish\GeminiChat\Knowledge\KnowledgeRetriever(
				$this->get_knowledge_repository()
			);
		}
		return $retriever;
	}

	/**
	 * Accessor to KnowledgeContextBuilder.
	 *
	 * @return \SkyFish\GeminiChat\Knowledge\KnowledgeContextBuilder
	 */
	public function get_knowledge_context_builder(): \SkyFish\GeminiChat\Knowledge\KnowledgeContextBuilder {
		static $builder = null;
		if ( null === $builder ) {
			$builder = new \SkyFish\GeminiChat\Knowledge\KnowledgeContextBuilder();
		}
		return $builder;
	}

	/**
	 * Accessor to HandoffRepository.
	 *
	 * @return \SkyFish\GeminiChat\Database\HandoffRepository
	 */
	public function get_handoff_repository(): \SkyFish\GeminiChat\Database\HandoffRepository {
		static $handoff_repo = null;
		if ( null === $handoff_repo ) {
			$handoff_repo = new \SkyFish\GeminiChat\Database\HandoffRepository();
		}
		return $handoff_repo;
	}

	/**
	 * Accessor to NotificationService.
	 *
	 * @return \SkyFish\GeminiChat\Notifications\NotificationService
	 */
	public function get_notification_service(): \SkyFish\GeminiChat\Notifications\NotificationService {
		static $notification_service = null;
		if ( null === $notification_service ) {
			$notification_service = new \SkyFish\GeminiChat\Notifications\NotificationService(
				$this->get_handoff_repository(),
				$this->get_conversation_repository(),
				$this->get_lead_repository()
			);
		}
		return $notification_service;
	}

	/**
	 * Accessor to HandoffService.
	 *
	 * @return \SkyFish\GeminiChat\Handoff\HandoffService
	 */
	public function get_handoff_service(): \SkyFish\GeminiChat\Handoff\HandoffService {
		static $handoff_service = null;
		if ( null === $handoff_service ) {
			$handoff_service = new \SkyFish\GeminiChat\Handoff\HandoffService(
				$this->get_handoff_repository(),
				$this->get_conversation_repository(),
				$this->get_lead_repository(),
				$this->get_notification_service()
			);
		}
		return $handoff_service;
	}

	/**
	 * Accessor to IntegrationRegistry.
	 *
	 * @return \SkyFish\GeminiChat\Integrations\IntegrationRegistry
	 */
	public function get_integration_registry(): \SkyFish\GeminiChat\Integrations\IntegrationRegistry {
		static $registry = null;
		if ( null === $registry ) {
			$registry = new \SkyFish\GeminiChat\Integrations\IntegrationRegistry();
			// Register WooCommerce business integration (N17.2).
			$registry->register( new \SkyFish\GeminiChat\Integrations\WooCommerce\WooCommerceIntegration() );
			// Register Human Handoff business integration (N17.3) with email notification action (N17.4).
			$registry->register( new \SkyFish\GeminiChat\Integrations\Handoff\HandoffIntegration( $this->get_handoff_service(), $this->get_notification_service() ) );
		}
		return $registry;
	}

	/**
	 * Accessor to ProviderRegistry (N18).
	 *
	 * @return \SkyFish\GeminiChat\Providers\ProviderRegistry
	 */
	public function get_provider_registry(): \SkyFish\GeminiChat\Providers\ProviderRegistry {
		if ( null === $this->provider_registry ) {
			$this->provider_registry = new \SkyFish\GeminiChat\Providers\ProviderRegistry();
			$this->provider_registry->register( new \SkyFish\GeminiChat\Providers\GeminiProvider( $this->get_gemini_client() ) );
			$this->provider_registry->register( new \SkyFish\GeminiChat\Providers\OpenAIProvider() );
			$this->provider_registry->register( new \SkyFish\GeminiChat\Providers\ClaudeProvider() );
		}
		return $this->provider_registry;
	}

	/**
	 * Accessor to ProviderSelectionService (N21).
	 *
	 * @return \SkyFish\GeminiChat\Providers\ProviderSelectionService
	 */
	public function get_provider_selection_service(): \SkyFish\GeminiChat\Providers\ProviderSelectionService {
		static $selection_service = null;
		if ( null === $selection_service ) {
			$selection_service = new \SkyFish\GeminiChat\Providers\ProviderSelectionService(
				$this->get_provider_registry(),
				SettingsService::get_instance()
			);
		}
		return $selection_service;
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
		// Initialize automatic knowledge sync hooks (save_post, before_delete_post, transition_post_status)
		$this->get_knowledge_indexer()->init_hooks();

		// Initialize business integrations registry
		$this->get_integration_registry()->init();

		if ( is_admin() ) {
			$this->admin_menu = new AdminMenu(
				$this->get_conversation_repository(),
				$this->get_message_repository(),
				null,
				$this->get_lead_repository(),
				$this->get_profile_service(),
				$this->get_faq_repository(),
				$this->get_knowledge_repository(),
				$this->get_knowledge_indexer(),
				$this->get_knowledge_retriever(),
				$this->get_integration_registry(),
				$this->get_handoff_repository(),
				$this->get_handoff_service(),
				$this->get_notification_service()
			);
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
