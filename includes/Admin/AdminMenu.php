<?php
/**
 * Admin Menu and Settings Page Controller.
 *
 * @package SkyFish\GeminiChat\Admin
 */

namespace SkyFish\GeminiChat\Admin;

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

	/**
	 * Hook registrations.
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Registers WordPress top-level and submenu pages.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Gemini Chat Settings', 'gemini-chat-assistant' ),
			__( 'Gemini Chat', 'gemini-chat-assistant' ),
			'manage_options',
			'gemini-chat-assistant',
			[ $this, 'render_settings_page' ],
			'dashicons-format-chat',
			30
		);

		add_submenu_page(
			'gemini-chat-assistant',
			__( 'Settings', 'gemini-chat-assistant' ),
			__( 'Settings', 'gemini-chat-assistant' ),
			'manage_options',
			'gemini-chat-assistant',
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
	 * Enqueues admin stylesheet and JavaScript only on the plugin's settings screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_gemini-chat-assistant' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'gca-admin-settings',
			GCA_PLUGIN_URL . 'admin/css/admin-settings.css',
			[],
			GCA_VERSION
		);

		wp_enqueue_script(
			'gca-admin-settings',
			GCA_PLUGIN_URL . 'admin/js/admin-settings.js',
			[],
			GCA_VERSION,
			true
		);
	}

	/**
	 * Renders the admin settings page view.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'gemini-chat-assistant' ) );
		}

		$settings         = SettingsService::get_all();
		$is_configured    = SettingsService::is_api_key_configured();

		include GCA_PLUGIN_DIR . 'templates/admin/settings.php';
	}
}
