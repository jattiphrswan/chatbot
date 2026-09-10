<?php
/**
 * Settings Service & Credential Resolver for Gemini Chat Assistant.
 *
 * @package SkyFish\GeminiChat\Admin
 */

namespace SkyFish\GeminiChat\Admin;

use SkyFish\GeminiChat\Activator;
use SkyFish\GeminiChat\Security\SecretStore;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SkyFish\\GeminiChat\\Activator' ) && file_exists( dirname( __DIR__ ) . '/class-activator.php' ) ) {
	require_once dirname( __DIR__ ) . '/class-activator.php';
}

/**
 * Class SettingsService
 *
 * Centralized service to manage plugin settings and server-side credential status.
 */
class SettingsService {

	public const OPTION_KEY = 'gca_settings';

	/**
	 * Singleton instance.
	 *
	 * @var SettingsService|null
	 */
	private static ?SettingsService $instance = null;

	/**
	 * Gets singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Retrieves all sanitized settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		$defaults = Activator::get_default_settings();
		$saved    = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Retrieves a specific configuration value.
	 *
	 * @param string $key     Configuration key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$settings = self::get_all();
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Retrieves the configured Gemini model slug.
	 *
	 * @return string
	 */
	public static function get_model(): string {
		$model = (string) self::get( 'model', 'gemini-2.5-flash' );
		return ! empty( $model ) ? sanitize_text_field( $model ) : 'gemini-2.5-flash';
	}

	/**
	 * Checks whether the Gemini API key is configured on the server.
	 *
	 * Checks:
	 * 1. GEMINI_API_KEY environment variable.
	 * 2. GCA_GEMINI_API_KEY constant in wp-config.php.
	 *
	 * @return bool
	 */
	public static function is_api_key_configured(): bool {
		$key = self::get_api_key();
		return ! empty( $key );
	}

	/**
	 * Retrieves the server-side Gemini API key securely.
	 *
	 * NEVER output or store this value.
	 *
	 * @return string
	 */
	public static function get_api_key(): string {
		// 1. Check environment variable (preferred).
		$env_key = getenv( 'GEMINI_API_KEY' );
		if ( ! empty( $env_key ) && is_string( $env_key ) ) {
			return trim( $env_key );
		}

		if ( ! empty( $_ENV['GEMINI_API_KEY'] ) && is_string( $_ENV['GEMINI_API_KEY'] ) ) {
			return trim( $_ENV['GEMINI_API_KEY'] );
		}

		if ( ! empty( $_SERVER['GEMINI_API_KEY'] ) && is_string( $_SERVER['GEMINI_API_KEY'] ) ) {
			return trim( $_SERVER['GEMINI_API_KEY'] );
		}

		// 2. Check wp-config constant (fallback).
		if ( defined( 'GCA_GEMINI_API_KEY' ) && is_string( GCA_GEMINI_API_KEY ) && ! empty( GCA_GEMINI_API_KEY ) ) {
			return trim( GCA_GEMINI_API_KEY );
		}

		// 3. Check stored database credential (fallback for admin-entered key).
		$stored_key = self::get_stored_credential( 'gemini' );
		if ( ! empty( $stored_key ) ) {
			return $stored_key;
		}

		return '';
	}

	/**
	 * Internal option name for encrypted/isolated credentials.
	 */
	public const CREDENTIALS_OPTION_KEY = 'gca_provider_credentials';

	/**
	 * Supported provider slugs.
	 */
	public const ALLOWED_PROVIDERS = [ 'gemini', 'openai', 'claude' ];

	/**
	 * Retrieves the configured default provider slug.
	 *
	 * Defaults to 'gemini'.
	 *
	 * @return string
	 */
	public static function get_default_provider(): string {
		$provider = (string) self::get( 'default_provider', 'gemini' );
		$provider = sanitize_key( $provider );
		return in_array( $provider, self::ALLOWED_PROVIDERS, true ) ? $provider : 'gemini';
	}

	/**
	 * Checks whether a given provider is enabled.
	 *
	 * @param string $provider_id Provider identifier ('gemini', 'openai', 'claude').
	 * @return bool
	 */
	public static function is_provider_enabled( string $provider_id ): bool {
		$provider_id = sanitize_key( $provider_id );
		if ( ! in_array( $provider_id, self::ALLOWED_PROVIDERS, true ) ) {
			return false;
		}

		// Gemini defaults to true if unconfigured in settings.
		$default_enabled = ( 'gemini' === $provider_id );
		return (bool) self::get( 'provider_' . $provider_id . '_enabled', $default_enabled );
	}

	/**
	 * Checks whether a provider is configured with credentials.
	 *
	 * @param string $provider_id Provider identifier ('gemini', 'openai', 'claude').
	 * @return bool
	 */
	public static function is_provider_configured( string $provider_id ): bool {
		$key = self::get_provider_api_key( $provider_id );
		return ! empty( $key );
	}

	/**
	 * Retrieves configured default model for a provider.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return string
	 */
	public static function get_provider_model( string $provider_id ): string {
		$provider_id = sanitize_key( $provider_id );
		switch ( $provider_id ) {
			case 'openai':
				$val = (string) self::get( 'provider_openai_model', 'gpt-4o-mini' );
				return ! empty( $val ) ? sanitize_text_field( $val ) : 'gpt-4o-mini';

			case 'claude':
				$val = (string) self::get( 'provider_claude_model', 'claude-3-5-haiku-20241022' );
				return ! empty( $val ) ? sanitize_text_field( $val ) : 'claude-3-5-haiku-20241022';

			case 'gemini':
			default:
				$val = (string) self::get( 'provider_gemini_model', '' );
				if ( empty( $val ) ) {
					$val = (string) self::get( 'model', 'gemini-2.5-flash' );
				}
				return ! empty( $val ) ? sanitize_text_field( $val ) : 'gemini-2.5-flash';
		}
	}

	/**
	 * Checks whether visitors are permitted to select AI provider in the chat widget (N21).
	 *
	 * @return bool
	 */
	public static function allow_public_provider_selection(): bool {
		return (bool) self::get( 'allow_public_provider_selection', false );
	}

	/**
	 * Checks whether visitors are permitted to select AI model in the chat widget (N21).
	 *
	 * @return bool
	 */
	public static function allow_public_model_selection(): bool {
		return (bool) self::get( 'allow_public_model_selection', false );
	}

	/**
	 * Retrieves the API key for a specified provider securely server-side.
	 *
	 * Priority order:
	 * 1. Environment variable (e.g. GEMINI_API_KEY, OPENAI_API_KEY, ANTHROPIC_API_KEY)
	 * 2. wp-config.php constant (e.g. GCA_GEMINI_API_KEY, GCA_OPENAI_API_KEY, GCA_CLAUDE_API_KEY)
	 * 3. Stored server-side credential option.
	 *
	 * NEVER expose or serialize this value to public REST or client JS.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return string Plaintext API key or empty string.
	 */
	public static function get_provider_api_key( string $provider_id ): string {
		$provider_id = sanitize_key( $provider_id );

		switch ( $provider_id ) {
			case 'gemini':
				return self::get_api_key();

			case 'openai':
				// 1. Environment variable.
				$env = getenv( 'OPENAI_API_KEY' );
				if ( ! empty( $env ) && is_string( $env ) ) {
					return trim( $env );
				}
				if ( ! empty( $_ENV['OPENAI_API_KEY'] ) && is_string( $_ENV['OPENAI_API_KEY'] ) ) {
					return trim( $_ENV['OPENAI_API_KEY'] );
				}
				if ( ! empty( $_SERVER['OPENAI_API_KEY'] ) && is_string( $_SERVER['OPENAI_API_KEY'] ) ) {
					return trim( $_SERVER['OPENAI_API_KEY'] );
				}
				// 2. Server constant.
				if ( defined( 'GCA_OPENAI_API_KEY' ) && is_string( GCA_OPENAI_API_KEY ) && ! empty( GCA_OPENAI_API_KEY ) ) {
					return trim( GCA_OPENAI_API_KEY );
				}
				// 3. Stored credential.
				return self::get_stored_credential( 'openai' );

			case 'claude':
				// 1. Environment variable.
				$env = getenv( 'ANTHROPIC_API_KEY' );
				if ( ! empty( $env ) && is_string( $env ) ) {
					return trim( $env );
				}
				if ( ! empty( $_ENV['ANTHROPIC_API_KEY'] ) && is_string( $_ENV['ANTHROPIC_API_KEY'] ) ) {
					return trim( $_ENV['ANTHROPIC_API_KEY'] );
				}
				if ( ! empty( $_SERVER['ANTHROPIC_API_KEY'] ) && is_string( $_SERVER['ANTHROPIC_API_KEY'] ) ) {
					return trim( $_SERVER['ANTHROPIC_API_KEY'] );
				}
				// 2. Server constant.
				if ( defined( 'GCA_CLAUDE_API_KEY' ) && is_string( GCA_CLAUDE_API_KEY ) && ! empty( GCA_CLAUDE_API_KEY ) ) {
					return trim( GCA_CLAUDE_API_KEY );
				}
				if ( defined( 'GCA_ANTHROPIC_API_KEY' ) && is_string( GCA_ANTHROPIC_API_KEY ) && ! empty( GCA_ANTHROPIC_API_KEY ) ) {
					return trim( GCA_ANTHROPIC_API_KEY );
				}
				// 3. Stored credential.
				return self::get_stored_credential( 'claude' );

			default:
				return '';
		}
	}

	/**
	 * Updates a provider's API key securely in SecretStore.
	 *
	 * Empty submissions are ignored by callers to prevent accidental deletion.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $api_key     New plaintext API key.
	 * @return bool True if successfully stored.
	 */
	public static function update_provider_api_key( string $provider_id, string $api_key ): bool {
		$provider_id = sanitize_key( $provider_id );
		$api_key     = trim( $api_key );

		if ( ! in_array( $provider_id, self::ALLOWED_PROVIDERS, true ) || empty( $api_key ) ) {
			return false;
		}

		return SecretStore::store( $provider_id, $api_key );
	}

	/**
	 * Explicitly removes a stored provider API key.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return bool True if removed.
	 */
	public static function remove_provider_api_key( string $provider_id ): bool {
		$provider_id = sanitize_key( $provider_id );
		if ( ! in_array( $provider_id, self::ALLOWED_PROVIDERS, true ) ) {
			return false;
		}

		return SecretStore::delete( $provider_id );
	}

	/**
	 * Checks whether a provider credential is specifically stored in the database.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return bool True if a credential exists in database options.
	 */
	public static function has_stored_credential( string $provider_id ): bool {
		$provider_id = sanitize_key( $provider_id );
		return SecretStore::has( $provider_id );
	}

	/**
	 * Retrieves a masked representation of the provider's API key.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return string Masked key or empty string.
	 */
	public static function get_masked_provider_api_key( string $provider_id ): string {
		$key = self::get_provider_api_key( $provider_id );
		return ! empty( $key ) ? SecretStore::mask( $key ) : '';
	}

	/**
	 * Option key prefix for connection verification status.
	 */
	public const CONNECTION_STATUS_OPTION_PREFIX = 'gca_connection_status_';

	/**
	 * Retrieves connection status info for a provider.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return array{status: string, last_checked: int, message: string} Status details.
	 */
	public static function get_connection_status_info( string $provider_id ): array {
		$provider_id = sanitize_key( $provider_id );
		$default     = [
			'status'       => self::is_provider_configured( $provider_id ) ? 'configured' : 'not_configured',
			'last_checked' => 0,
			'message'      => '',
		];

		$saved = get_option( self::CONNECTION_STATUS_OPTION_PREFIX . $provider_id, [] );
		if ( ! is_array( $saved ) ) {
			return $default;
		}

		$status = $saved['status'] ?? $default['status'];
		// If recorded as verified/failed but credentials were removed, fall back to not_configured.
		if ( ! self::is_provider_configured( $provider_id ) ) {
			$status = 'not_configured';
		}

		return [
			'status'       => $status,
			'last_checked' => isset( $saved['last_checked'] ) ? absint( $saved['last_checked'] ) : 0,
			'message'      => isset( $saved['message'] ) ? sanitize_text_field( (string) $saved['message'] ) : '',
		];
	}

	/**
	 * Updates connection verification status for a provider.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $status      'verified', 'failed', or 'configured'.
	 * @param string $message     Optional error/info message.
	 * @return bool
	 */
	public static function set_connection_status( string $provider_id, string $status, string $message = '' ): bool {
		$provider_id = sanitize_key( $provider_id );
		$data        = [
			'status'       => sanitize_key( $status ),
			'last_checked' => time(),
			'message'      => sanitize_text_field( $message ),
		];

		return update_option( self::CONNECTION_STATUS_OPTION_PREFIX . $provider_id, $data, 'no' );
	}

	/**
	 * Identifies the source of a provider credential.
	 *
	 * @param string $provider_id Provider identifier ('gemini', 'openai', 'claude').
	 * @return string 'environment', 'constant', 'database', or 'none'.
	 */
	public static function get_credential_source( string $provider_id ): string {
		$provider_id = sanitize_key( $provider_id );

		switch ( $provider_id ) {
			case 'gemini':
				if ( ! empty( getenv( 'GEMINI_API_KEY' ) ) || ! empty( $_ENV['GEMINI_API_KEY'] ) || ! empty( $_SERVER['GEMINI_API_KEY'] ) ) {
					return 'environment';
				}
				if ( defined( 'GCA_GEMINI_API_KEY' ) && is_string( GCA_GEMINI_API_KEY ) && ! empty( GCA_GEMINI_API_KEY ) ) {
					return 'constant';
				}
				if ( self::has_stored_credential( 'gemini' ) ) {
					return 'database';
				}
				return 'none';

			case 'openai':
				if ( ! empty( getenv( 'OPENAI_API_KEY' ) ) || ! empty( $_ENV['OPENAI_API_KEY'] ) || ! empty( $_SERVER['OPENAI_API_KEY'] ) ) {
					return 'environment';
				}
				if ( defined( 'GCA_OPENAI_API_KEY' ) && is_string( GCA_OPENAI_API_KEY ) && ! empty( GCA_OPENAI_API_KEY ) ) {
					return 'constant';
				}
				if ( self::has_stored_credential( 'openai' ) ) {
					return 'database';
				}
				return 'none';

			case 'claude':
				if ( ! empty( getenv( 'ANTHROPIC_API_KEY' ) ) || ! empty( $_ENV['ANTHROPIC_API_KEY'] ) || ! empty( $_SERVER['ANTHROPIC_API_KEY'] ) ) {
					return 'environment';
				}
				if ( ( defined( 'GCA_CLAUDE_API_KEY' ) && is_string( GCA_CLAUDE_API_KEY ) && ! empty( GCA_CLAUDE_API_KEY ) ) ||
				     ( defined( 'GCA_ANTHROPIC_API_KEY' ) && is_string( GCA_ANTHROPIC_API_KEY ) && ! empty( GCA_ANTHROPIC_API_KEY ) ) ) {
					return 'constant';
				}
				if ( self::has_stored_credential( 'claude' ) ) {
					return 'database';
				}
				return 'none';

			default:
				return 'none';
		}
	}

	/**
	 * Internal helper to read and decode a stored credential.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return string Decoded credential or empty string.
	 */
	private static function get_stored_credential( string $provider_id ): string {
		return SecretStore::retrieve( $provider_id );
	}

	/**
	 * Obfuscates/encodes credentials before storing in options.
	 *
	 * @deprecated Use SecretStore::encrypt()
	 * @param string $plaintext Plain API key.
	 * @return string Encrypted/encoded string.
	 */
	private static function encode_credential( string $plaintext ): string {
		return SecretStore::encrypt( $plaintext );
	}

	/**
	 * Decodes/decrypts stored credential string.
	 *
	 * @deprecated Use SecretStore::decrypt()
	 * @param string $encoded Encoded string from database.
	 * @return string Decoded plain API key.
	 */
	private static function decode_credential( string $encoded ): string {
		return SecretStore::decrypt( $encoded );
	}

	/**
	 * Sanitizes and validates settings input before saving to database.
	 *
	 * Note: API keys are NEVER handled here.
	 *
	 * @param array<string, mixed> $input Raw submitted POST input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( array $input ): array {
		$defaults  = Activator::get_default_settings();
		$sanitized = [];

		// General.
		$sanitized['enabled']         = ! empty( $input['enabled'] );
		$sanitized['assistant_name']  = isset( $input['assistant_name'] ) ? sanitize_text_field( $input['assistant_name'] ) : $defaults['assistant_name'];
		$sanitized['greeting']        = isset( $input['greeting'] ) ? sanitize_text_field( $input['greeting'] ) : $defaults['greeting'];
		$sanitized['welcome_message'] = isset( $input['welcome_message'] ) ? sanitize_textarea_field( $input['welcome_message'] ) : $defaults['welcome_message'];
		$sanitized['placeholder']     = isset( $input['placeholder'] ) ? sanitize_text_field( $input['placeholder'] ) : $defaults['placeholder'];

		// AI & Providers (N18).
		$default_provider = isset( $input['default_provider'] ) ? sanitize_key( $input['default_provider'] ) : 'gemini';
		$sanitized['default_provider'] = in_array( $default_provider, self::ALLOWED_PROVIDERS, true ) ? $default_provider : 'gemini';

		// Provider: Gemini.
		$sanitized['provider_gemini_enabled'] = ! empty( $input['provider_gemini_enabled'] );
		$raw_gemini_model                     = isset( $input['provider_gemini_model'] ) ? sanitize_text_field( trim( (string) $input['provider_gemini_model'] ) ) : ( isset( $input['model'] ) ? sanitize_text_field( trim( (string) $input['model'] ) ) : 'gemini-2.5-flash' );
		$sanitized['provider_gemini_model']   = \SkyFish\GeminiChat\Providers\ModelRegistry::has_model( 'gemini', $raw_gemini_model ) ? $raw_gemini_model : 'gemini-2.5-flash';
		$sanitized['model']                   = $sanitized['provider_gemini_model']; // Backward compatibility.

		// Provider: OpenAI.
		$sanitized['provider_openai_enabled'] = ! empty( $input['provider_openai_enabled'] );
		$raw_openai_model                     = isset( $input['provider_openai_model'] ) ? sanitize_text_field( trim( (string) $input['provider_openai_model'] ) ) : 'gpt-4o-mini';
		$sanitized['provider_openai_model']   = \SkyFish\GeminiChat\Providers\ModelRegistry::has_model( 'openai', $raw_openai_model ) ? $raw_openai_model : 'gpt-4o-mini';

		// Provider: Claude.
		$sanitized['provider_claude_enabled'] = ! empty( $input['provider_claude_enabled'] );
		$raw_claude_model                     = isset( $input['provider_claude_model'] ) ? sanitize_text_field( trim( (string) $input['provider_claude_model'] ) ) : 'claude-3-5-haiku-20241022';
		$sanitized['provider_claude_model']   = \SkyFish\GeminiChat\Providers\ModelRegistry::has_model( 'claude', $raw_claude_model ) ? $raw_claude_model : 'claude-3-5-haiku-20241022';

		// Public Chat Controls (N21).
		$sanitized['allow_public_provider_selection'] = ! empty( $input['allow_public_provider_selection'] );
		$sanitized['allow_public_model_selection']    = ! empty( $input['allow_public_model_selection'] );

		$sanitized['system_instruction'] = isset( $input['system_instruction'] ) ? sanitize_textarea_field( $input['system_instruction'] ) : $defaults['system_instruction'];

		// Secure Credential Updates (only when new non-empty key entered).
		if ( ! empty( $input['api_key_gemini'] ) && is_string( $input['api_key_gemini'] ) ) {
			self::update_provider_api_key( 'gemini', $input['api_key_gemini'] );
		}
		if ( ! empty( $input['api_key_openai'] ) && is_string( $input['api_key_openai'] ) ) {
			self::update_provider_api_key( 'openai', $input['api_key_openai'] );
		}
		if ( ! empty( $input['api_key_claude'] ) && is_string( $input['api_key_claude'] ) ) {
			self::update_provider_api_key( 'claude', $input['api_key_claude'] );
		}

		// Widget.
		$sanitized['widget_enabled']        = ! empty( $input['widget_enabled'] );
		$sanitized['embedded_chat_enabled'] = ! empty( $input['embedded_chat_enabled'] );
		$sanitized['desktop_enabled']       = ! empty( $input['desktop_enabled'] );
		$sanitized['mobile_enabled']        = ! empty( $input['mobile_enabled'] );

		// Pre-chat.
		$sanitized['prechat_enabled']     = ! empty( $input['prechat_enabled'] );
		$sanitized['collect_name']        = ! empty( $input['collect_name'] );
		$sanitized['require_name']        = ! empty( $input['require_name'] );
		$sanitized['collect_email']       = ! empty( $input['collect_email'] );
		$sanitized['require_email']       = ! empty( $input['require_email'] );
		$sanitized['collect_phone']       = ! empty( $input['collect_phone'] );
		$sanitized['require_phone']       = ! empty( $input['require_phone'] );
		$sanitized['collect_requirement'] = ! empty( $input['collect_requirement'] );
		$sanitized['require_requirement'] = ! empty( $input['require_requirement'] );

		// FAQ (N16).
		$sanitized['faq_enabled']   = ! empty( $input['faq_enabled'] );
		$sanitized['faq_show_home'] = ! empty( $input['faq_show_home'] );
		$faq_limit                  = isset( $input['faq_home_limit'] ) ? absint( $input['faq_home_limit'] ) : ( $defaults['faq_home_limit'] ?? 6 );
		$sanitized['faq_home_limit'] = max( 1, min( 20, $faq_limit ) );

		// Website Knowledge / RAG (N16).
		$sanitized['knowledge_enabled']          = ! empty( $input['knowledge_enabled'] );
		$sanitized['knowledge_pages_enabled']    = ! empty( $input['knowledge_pages_enabled'] );
		$sanitized['knowledge_posts_enabled']    = ! empty( $input['knowledge_posts_enabled'] );
		$sanitized['knowledge_products_enabled'] = ! empty( $input['knowledge_products_enabled'] );
		$sanitized['knowledge_faqs_enabled']     = ! empty( $input['knowledge_faqs_enabled'] );

		$max_chunks = isset( $input['knowledge_max_chunks'] ) ? absint( $input['knowledge_max_chunks'] ) : ( $defaults['knowledge_max_chunks'] ?? 4 );
		$sanitized['knowledge_max_chunks'] = max( 1, min( 8, $max_chunks ) );

		$max_chars = isset( $input['knowledge_max_context_chars'] ) ? absint( $input['knowledge_max_context_chars'] ) : ( $defaults['knowledge_max_context_chars'] ?? 6000 );
		$sanitized['knowledge_max_context_chars'] = max( 500, min( 12000, $max_chars ) );

		// Human Handoff Email Notifications (N17.4).
		$sanitized['handoff_email_enabled'] = ! empty( $input['handoff_email_enabled'] );

		if ( isset( $input['handoff_email_recipients'] ) ) {
			$sanitized['handoff_email_recipients'] = \SkyFish\GeminiChat\Notifications\NotificationService::format_recipients_for_display( $input['handoff_email_recipients'] );
		} else {
			$sanitized['handoff_email_recipients'] = $defaults['handoff_email_recipients'] ?? '';
		}

		$subject = isset( $input['handoff_email_subject'] ) ? (string) $input['handoff_email_subject'] : ( $defaults['handoff_email_subject'] ?? 'New Chatbot Handoff Request' );
		$sanitized['handoff_email_subject'] = \SkyFish\GeminiChat\Notifications\NotificationService::sanitize_subject( $subject );

		// Direct Contact Channels (N17.5).
		$sanitized['contact_channels_enabled'] = ! empty( $input['contact_channels_enabled'] );

		// Phone / Call
		$sanitized['contact_phone_enabled'] = ! empty( $input['contact_phone_enabled'] );
		$raw_phone                          = isset( $input['contact_phone_number'] ) ? (string) $input['contact_phone_number'] : '';
		$sanitized['contact_phone_number']  = preg_replace( '/[^0-9+\-().\s]/', '', trim( $raw_phone ) );
		$phone_label                        = isset( $input['contact_phone_label'] ) ? sanitize_text_field( trim( (string) $input['contact_phone_label'] ) ) : '';
		$sanitized['contact_phone_label']   = ! empty( $phone_label ) ? mb_substr( $phone_label, 0, 50 ) : 'Call Us';

		// Email
		$sanitized['contact_email_enabled'] = ! empty( $input['contact_email_enabled'] );
		$raw_email                          = isset( $input['contact_email_address'] ) ? (string) $input['contact_email_address'] : '';
		$clean_email                        = sanitize_email( trim( $raw_email ) );
		$sanitized['contact_email_address'] = is_email( $clean_email ) ? $clean_email : '';
		$email_label                        = isset( $input['contact_email_label'] ) ? sanitize_text_field( trim( (string) $input['contact_email_label'] ) ) : '';
		$sanitized['contact_email_label']   = ! empty( $email_label ) ? mb_substr( $email_label, 0, 50 ) : 'Email Us';

		// WhatsApp
		$sanitized['contact_whatsapp_enabled'] = ! empty( $input['contact_whatsapp_enabled'] );
		$raw_wa_num                            = isset( $input['contact_whatsapp_number'] ) ? (string) $input['contact_whatsapp_number'] : '';
		// Strip all non-digit characters except leading plus
		$sanitized['contact_whatsapp_number']  = preg_replace( '/[^0-9+]/', '', trim( $raw_wa_num ) );
		$wa_label                              = isset( $input['contact_whatsapp_label'] ) ? sanitize_text_field( trim( (string) $input['contact_whatsapp_label'] ) ) : '';
		$sanitized['contact_whatsapp_label']   = ! empty( $wa_label ) ? mb_substr( $wa_label, 0, 50 ) : 'WhatsApp';
		$wa_msg                                = isset( $input['contact_whatsapp_message'] ) ? sanitize_text_field( trim( (string) $input['contact_whatsapp_message'] ) ) : '';
		$sanitized['contact_whatsapp_message'] = mb_substr( $wa_msg, 0, 300 );

		// Access.
		$sanitized['guest_access'] = ! empty( $input['guest_access'] );

		// Limits.
		$max_len = isset( $input['max_message_length'] ) ? absint( $input['max_message_length'] ) : $defaults['max_message_length'];
		$sanitized['max_message_length'] = ( $max_len >= 100 && $max_len <= 10000 ) ? $max_len : 2000;

		$rate_5m = isset( $input['rate_limit_5m'] ) ? absint( $input['rate_limit_5m'] ) : $defaults['rate_limit_5m'];
		$sanitized['rate_limit_5m'] = ( $rate_5m >= 1 && $rate_5m <= 500 ) ? $rate_5m : 15;

		$rate_1h = isset( $input['rate_limit_1h'] ) ? absint( $input['rate_limit_1h'] ) : $defaults['rate_limit_1h'];
		$sanitized['rate_limit_1h'] = ( $rate_1h >= 5 && $rate_1h <= 5000 ) ? $rate_1h : 100;

		// Privacy.
		$sanitized['store_messages'] = ! empty( $input['store_messages'] );
		$sanitized['store_leads']    = ! empty( $input['store_leads'] );

		$retention = isset( $input['retention_days'] ) ? absint( $input['retention_days'] ) : $defaults['retention_days'];
		$sanitized['retention_days'] = ( $retention >= 1 && $retention <= 365 ) ? $retention : 30;

		// Appearance & Branding (N13).
		$sanitized['avatar_id'] = isset( $input['avatar_id'] ) ? absint( $input['avatar_id'] ) : $defaults['avatar_id'];

		$color_keys = [
			'primary_color',
			'header_bg_color',
			'header_text_color',
			'panel_bg_color',
			'text_color',
			'assistant_bubble_color',
			'assistant_text_color',
			'user_bubble_color',
			'user_text_color',
			'button_color',
			'button_text_color',
			'launcher_bg_color',
			'launcher_icon_color',
		];

		foreach ( $color_keys as $ck ) {
			if ( isset( $input[ $ck ] ) ) {
				$hex = function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( (string) $input[ $ck ] ) : null;
				if ( empty( $hex ) && preg_match( '/^#([a-fA-F0-9]{3}){1,2}$/', (string) $input[ $ck ] ) ) {
					$hex = (string) $input[ $ck ];
				}
				$sanitized[ $ck ] = ! empty( $hex ) ? strtoupper( $hex ) : $defaults[ $ck ];
			} else {
				$sanitized[ $ck ] = $defaults[ $ck ];
			}
		}

		// Position whitelist.
		$allowed_positions = [ 'bottom-right', 'bottom-left' ];
		$pos = isset( $input['widget_position'] ) ? strtolower( trim( (string) $input['widget_position'] ) ) : $defaults['widget_position'];
		$sanitized['widget_position'] = in_array( $pos, $allowed_positions, true ) ? $pos : 'bottom-right';

		// Launcher icon whitelist.
		$allowed_icons = [ 'chat', 'message', 'headset', 'sparkle' ];
		$icon = isset( $input['launcher_icon'] ) ? strtolower( trim( (string) $input['launcher_icon'] ) ) : $defaults['launcher_icon'];
		$sanitized['launcher_icon'] = in_array( $icon, $allowed_icons, true ) ? $icon : 'chat';

		// Panel Width (320 - 600px).
		$width = isset( $input['panel_width'] ) ? absint( $input['panel_width'] ) : $defaults['panel_width'];
		$sanitized['panel_width'] = max( 320, min( 600, $width ) );

		// Panel Height (450 - 850px).
		$height = isset( $input['panel_height'] ) ? absint( $input['panel_height'] ) : $defaults['panel_height'];
		$sanitized['panel_height'] = max( 450, min( 850, $height ) );

		// Border Radius (0 - 40px).
		$radius = isset( $input['border_radius'] ) ? absint( $input['border_radius'] ) : $defaults['border_radius'];
		$sanitized['border_radius'] = max( 0, min( 40, $radius ) );

		// Launcher Size (44 - 80px).
		$size = isset( $input['launcher_size'] ) ? absint( $input['launcher_size'] ) : $defaults['launcher_size'];
		$sanitized['launcher_size'] = max( 44, min( 80, $size ) );

		// Responsive visibility.
		$sanitized['tablet_enabled'] = ! empty( $input['tablet_enabled'] );

		return $sanitized;
	}
}
