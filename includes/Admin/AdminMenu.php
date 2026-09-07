<?php
/**
 * Admin Menu, Dashboard, and Conversations Controller.
 *
 * @package SkyFish\GeminiChat\Admin
 */

namespace SkyFish\GeminiChat\Admin;

use SkyFish\GeminiChat\Database\ConversationRepository;
use SkyFish\GeminiChat\Database\MessageRepository;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminMenu
 *
 * Manages admin menu registration, settings registration, and page rendering.
 */
class AdminMenu {

	public const MAIN_MENU_SLUG          = 'gemini-chat-assistant';
	public const CONVERSATIONS_MENU_SLUG = 'gca-conversations';
	public const SETTINGS_MENU_SLUG      = 'gca-settings';

	private ConversationRepository $conversation_repo;
	private MessageRepository $message_repo;

	/**
	 * AdminMenu constructor.
	 *
	 * @param ConversationRepository|null $conversation_repo Optional conversation repository.
	 * @param MessageRepository|null      $message_repo      Optional message repository.
	 */
	public function __construct(
		?ConversationRepository $conversation_repo = null,
		?MessageRepository $message_repo = null
	) {
		$this->conversation_repo = $conversation_repo ?? new ConversationRepository();
		$this->message_repo      = $message_repo ?? new MessageRepository();
	}

	/**
	 * Hook registrations.
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Admin post action hooks for conversations
		add_action( 'admin_post_gca_delete_conversation', [ $this, 'handle_delete_conversation' ] );
		add_action( 'admin_post_gca_close_conversation', [ $this, 'handle_close_conversation' ] );
		add_action( 'admin_post_gca_reopen_conversation', [ $this, 'handle_reopen_conversation' ] );
	}

	/**
	 * Registers WordPress top-level and submenu pages.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Gemini Chat Assistant', 'gemini-chat-assistant' ),
			__( 'Gemini Chat', 'gemini-chat-assistant' ),
			'manage_options',
			self::MAIN_MENU_SLUG,
			[ $this, 'render_dashboard_page' ],
			'dashicons-format-chat',
			30
		);

		add_submenu_page(
			self::MAIN_MENU_SLUG,
			__( 'Dashboard', 'gemini-chat-assistant' ),
			__( 'Dashboard', 'gemini-chat-assistant' ),
			'manage_options',
			self::MAIN_MENU_SLUG,
			[ $this, 'render_dashboard_page' ]
		);

		add_submenu_page(
			self::MAIN_MENU_SLUG,
			__( 'Conversations', 'gemini-chat-assistant' ),
			__( 'Conversations', 'gemini-chat-assistant' ),
			'manage_options',
			self::CONVERSATIONS_MENU_SLUG,
			[ $this, 'render_conversations_page' ]
		);

		add_submenu_page(
			self::MAIN_MENU_SLUG,
			__( 'Settings', 'gemini-chat-assistant' ),
			__( 'Settings', 'gemini-chat-assistant' ),
			'manage_options',
			self::SETTINGS_MENU_SLUG,
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Registers settings with WordPress Settings API.
	 */
	public function register_settings(): void {
		register_setting(
			'gca_settings_group',
			SettingsService::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ SettingsService::class, 'sanitize_settings' ],
				'default'           => \SkyFish\GeminiChat\Activator::get_default_settings(),
			]
		);
	}

	/**
	 * Enqueues admin stylesheet and JavaScript only on the plugin's admin screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$allowed_hooks = [
			'toplevel_page_' . self::MAIN_MENU_SLUG,
			'gemini-chat_page_' . self::CONVERSATIONS_MENU_SLUG,
			'gemini-chat-assistant_page_' . self::CONVERSATIONS_MENU_SLUG,
			'gemini-chat_page_' . self::SETTINGS_MENU_SLUG,
			'gemini-chat-assistant_page_' . self::SETTINGS_MENU_SLUG,
		];

		if ( ! in_array( $hook_suffix, $allowed_hooks, true ) && false === strpos( $hook_suffix, 'gemini-chat' ) ) {
			return;
		}

		wp_enqueue_style(
			'gca-admin-styles',
			GCA_PLUGIN_URL . 'admin/css/admin-settings.css',
			[],
			GCA_VERSION
		);

		if ( false !== strpos( $hook_suffix, self::SETTINGS_MENU_SLUG ) ) {
			wp_enqueue_script(
				'gca-admin-settings',
				GCA_PLUGIN_URL . 'admin/js/admin-settings.js',
				[],
				GCA_VERSION,
				true
			);
		}
	}

	/**
	 * Renders the admin dashboard page view.
	 */
	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'gemini-chat-assistant' ) );
		}

		$settings           = SettingsService::get_all();
		$is_configured      = SettingsService::is_api_key_configured();
		$model              = SettingsService::get_model();
		$db_version         = defined( 'GCA_DB_VERSION' ) ? GCA_DB_VERSION : '1.0.0';
		$settings_url       = admin_url( 'admin.php?page=' . self::SETTINGS_MENU_SLUG );
		$conversations_url  = admin_url( 'admin.php?page=' . self::CONVERSATIONS_MENU_SLUG );
		$total_conversations = $this->conversation_repo->count_all();

		include GCA_PLUGIN_DIR . 'templates/admin/dashboard.php';
	}

	/**
	 * Renders the conversations management list or detail view.
	 */
	public function render_conversations_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'gemini-chat-assistant' ) );
		}

		$conversation_id = ! empty( $_GET['conversation_id'] ) ? sanitize_text_field( (string) $_GET['conversation_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! empty( $conversation_id ) ) {
			// Detail view
			$conversation = $this->conversation_repo->get_by_public_id( $conversation_id );
			if ( ! $conversation ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . self::CONVERSATIONS_MENU_SLUG ) );
				exit;
			}

			$messages = $this->message_repo->get_by_conversation_id( (int) $conversation['id'], 500, 'ASC' );
			$back_url = admin_url( 'admin.php?page=' . self::CONVERSATIONS_MENU_SLUG );

			include GCA_PLUGIN_DIR . 'templates/admin/conversation-detail.php';
			return;
		}

		// List view
		$current_page   = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page       = 20;
		$current_status = ! empty( $_GET['status'] ) ? sanitize_text_field( (string) $_GET['status'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_term    = ! empty( $_GET['s'] ) ? sanitize_text_field( (string) $_GET['s'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$filter_args = [
			'page'     => $current_page,
			'per_page' => $per_page,
			'status'   => 'all' !== $current_status ? $current_status : null,
			'search'   => $search_term,
			'orderby'  => 'updated_at',
			'order'    => 'DESC',
		];

		$conversations = $this->conversation_repo->get_admin_list( $filter_args );
		$total_items   = $this->conversation_repo->count_admin_list( $filter_args );
		$total_pages   = (int) ceil( $total_items / $per_page );
		$base_url      = admin_url( 'admin.php?page=' . self::CONVERSATIONS_MENU_SLUG );

		include GCA_PLUGIN_DIR . 'templates/admin/conversations.php';
	}

	/**
	 * Handles POST action to delete a conversation.
	 */
	public function handle_delete_conversation(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'gemini-chat-assistant' ) );
		}

		$public_id = ! empty( $_POST['conversation_id'] ) ? sanitize_text_field( (string) $_POST['conversation_id'] ) : '';
		check_admin_referer( 'gca_delete_conversation_' . $public_id );

		if ( ! empty( $public_id ) ) {
			$this->conversation_repo->delete_by_public_id( $public_id );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => self::CONVERSATIONS_MENU_SLUG, 'deleted' => 1 ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handles POST action to close an active conversation.
	 */
	public function handle_close_conversation(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'gemini-chat-assistant' ) );
		}

		$public_id = ! empty( $_POST['conversation_id'] ) ? sanitize_text_field( (string) $_POST['conversation_id'] ) : '';
		check_admin_referer( 'gca_close_conversation_' . $public_id );

		if ( ! empty( $public_id ) ) {
			$this->conversation_repo->update_status_by_public_id( $public_id, 'closed' );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => self::CONVERSATIONS_MENU_SLUG, 'conversation_id' => $public_id, 'closed' => 1 ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handles POST action to reopen a closed conversation.
	 */
	public function handle_reopen_conversation(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'gemini-chat-assistant' ) );
		}

		$public_id = ! empty( $_POST['conversation_id'] ) ? sanitize_text_field( (string) $_POST['conversation_id'] ) : '';
		check_admin_referer( 'gca_reopen_conversation_' . $public_id );

		if ( ! empty( $public_id ) ) {
			$this->conversation_repo->update_status_by_public_id( $public_id, 'active' );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => self::CONVERSATIONS_MENU_SLUG, 'conversation_id' => $public_id, 'reopened' => 1 ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Renders the admin settings page view.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'gemini-chat-assistant' ) );
		}

		$settings      = SettingsService::get_all();
		$is_configured = SettingsService::is_api_key_configured();

		include GCA_PLUGIN_DIR . 'templates/admin/settings.php';
	}
}
