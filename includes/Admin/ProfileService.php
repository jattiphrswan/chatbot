<?php
/**
 * AI Profiles & Prompt Management Service.
 *
 * @package SkyFish\GeminiChat\Admin
 */

namespace SkyFish\GeminiChat\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProfileService
 *
 * Manages AI profile storage (WordPress Options API), active profile selection,
 * legacy prompt migration, and deterministic effective system instruction construction.
 */
class ProfileService {

	/**
	 * WordPress option name for storing AI profiles.
	 */
	public const OPTION_KEY = 'gca_ai_profiles';

	/**
	 * Maximum number of profiles allowed to be stored.
	 */
	public const MAX_PROFILES = 25;

	/**
	 * Maximum length in characters for prompt fields.
	 */
	public const MAX_NAME_LENGTH        = 100;
	public const MAX_DESCRIPTION_LENGTH = 1000;
	public const MAX_ROLE_LENGTH        = 2000;
	public const MAX_PROMPT_LENGTH      = 15000;
	public const MAX_RULES_LENGTH       = 10000;
	public const MAX_FALLBACK_LENGTH    = 2000;

	/**
	 * Allowed tone slugs and their human-readable labels.
	 *
	 * @var array<string, string>
	 */
	public const ALLOWED_TONES = [
		'professional'   => 'Professional',
		'friendly'       => 'Friendly',
		'concise'        => 'Concise',
		'helpful'        => 'Helpful',
		'conversational' => 'Conversational',
		'formal'         => 'Formal',
	];

	/**
	 * Allowed response style slugs and their human-readable labels.
	 *
	 * @var array<string, string>
	 */
	public const ALLOWED_STYLES = [
		'concise'  => 'Concise',
		'balanced' => 'Balanced',
		'detailed' => 'Detailed',
	];

	/**
	 * Hardcoded safe default system instruction when no profiles or settings exist.
	 */
	public const DEFAULT_FALLBACK_PROMPT = 'You are a helpful customer support assistant for this website.';

	/**
	 * SettingsService instance.
	 *
	 * @var SettingsService|null
	 */
	private ?SettingsService $settings_service;

	/**
	 * Constructor.
	 *
	 * @param SettingsService|null $settings_service Optional settings service.
	 */
	public function __construct( ?SettingsService $settings_service = null ) {
		$this->settings_service = $settings_service ?? SettingsService::get_instance();
	}

	/**
	 * Migrates legacy system_instruction from Settings to a default "General Assistant" profile
	 * if no AI profiles currently exist.
	 *
	 * @return array<string, mixed> The newly initialized or existing profiles array.
	 */
	public function migrate_legacy_prompt(): array {
		$profiles = get_option( self::OPTION_KEY, null );

		// If profiles option already exists as an array, return it.
		if ( is_array( $profiles ) && ! empty( $profiles ) ) {
			return $profiles;
		}

		// Retrieve existing legacy prompt.
		$legacy_prompt = SettingsService::get( 'system_instruction', '' );
		if ( ! is_string( $legacy_prompt ) || '' === trim( $legacy_prompt ) ) {
			$legacy_prompt = self::DEFAULT_FALLBACK_PROMPT;
		}

		$default_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : 'gca-profile-default-01';
		$now        = current_time( 'mysql' );

		$default_profile = [
			'id'               => $default_id,
			'name'             => __( 'General Assistant', 'gemini-chat-assistant' ),
			'description'      => __( 'Default AI assistant persona handling general website inquiries.', 'gemini-chat-assistant' ),
			'role'             => __( 'You are the customer support assistant for this website.', 'gemini-chat-assistant' ),
			'tone'             => 'professional',
			'system_prompt'    => trim( $legacy_prompt ),
			'rules'            => "- Answer questions accurately and concisely.\n- Do not invent pricing or availability.\n- If uncertain, politely state you do not know.\n- Offer human support when appropriate.",
			'response_style'   => 'balanced',
			'fallback_message' => __( "I'm unable to answer that accurately. Please contact our team for assistance.", 'gemini-chat-assistant' ),
			'enabled'          => true,
			'created_at'       => $now,
			'updated_at'       => $now,
		];

		$profiles = [
			$default_id => $default_profile,
		];

		// Save with autoload = 'no' to prevent bloating autoload cache.
		update_option( self::OPTION_KEY, $profiles, 'no' );

		// Synchronize active_profile_id in Settings if not set.
		$this->set_active_profile_id( $default_id );

		return $profiles;
	}

	/**
	 * Retrieves all stored AI profiles.
	 *
	 * @return array<string, array<string, mixed>> Array of profiles keyed by UUID.
	 */
	public function get_all_profiles(): array {
		$profiles = get_option( self::OPTION_KEY, null );

		if ( ! is_array( $profiles ) || empty( $profiles ) ) {
			return $this->migrate_legacy_prompt();
		}

		return $profiles;
	}

	/**
	 * Retrieves a single profile by its UUID.
	 *
	 * @param string $id Profile UUID.
	 * @return array<string, mixed>|null Profile data or null if not found.
	 */
	public function get_profile( string $id ): ?array {
		$profiles = $this->get_all_profiles();
		return $profiles[ $id ] ?? null;
	}

	/**
	 * Retrieves the active profile ID from gca_settings.
	 *
	 * @return string Active profile UUID.
	 */
	public function get_active_profile_id(): string {
		$active_id = SettingsService::get( 'active_profile_id', '' );
		if ( is_string( $active_id ) && ! empty( $active_id ) ) {
			return trim( $active_id );
		}
		return '';
	}

	/**
	 * Updates the active profile ID in gca_settings.
	 *
	 * @param string $id Profile UUID.
	 * @return bool
	 */
	public function set_active_profile_id( string $id ): bool {
		$settings = SettingsService::get_all();
		$settings['active_profile_id'] = sanitize_text_field( $id );
		return update_option( SettingsService::OPTION_KEY, $settings );
	}

	/**
	 * Resolves the currently active AI profile.
	 *
	 * If the configured active profile is missing or disabled, safely falls back
	 * to the first enabled profile, or generates a safe in-memory default.
	 *
	 * @return array<string, mixed>
	 */
	public function get_active_profile(): array {
		$profiles  = $this->get_all_profiles();
		$active_id = $this->get_active_profile_id();

		if ( ! empty( $active_id ) && isset( $profiles[ $active_id ] ) && ! empty( $profiles[ $active_id ]['enabled'] ) ) {
			return $profiles[ $active_id ];
		}

		// Fallback: search for first enabled profile.
		foreach ( $profiles as $profile ) {
			if ( ! empty( $profile['enabled'] ) ) {
				// Re-align active ID.
				$this->set_active_profile_id( (string) $profile['id'] );
				return $profile;
			}
		}

		// Ultimate safe default in-memory fallback.
		return [
			'id'               => 'gca-fallback-safe',
			'name'             => __( 'Safe Default Assistant', 'gemini-chat-assistant' ),
			'description'      => '',
			'role'             => '',
			'tone'             => 'professional',
			'system_prompt'    => SettingsService::get( 'system_instruction', self::DEFAULT_FALLBACK_PROMPT ),
			'rules'            => '',
			'response_style'   => 'balanced',
			'fallback_message' => '',
			'enabled'          => true,
		];
	}

	/**
	 * Constructs the effective deterministic system instruction for GeminiClient.
	 *
	 * Precedence:
	 * 1. Active Profile constructed prompt.
	 * 2. Legacy Settings system_instruction fallback.
	 * 3. Hardcoded safe default.
	 *
	 * @param array<string, mixed>|null $profile Optional specific profile to build from.
	 * @return string Constructed system instruction text.
	 */
	public function get_effective_system_instruction( ?array $profile = null ): string {
		$active = $profile ?? $this->get_active_profile();

		$parts = [];

		// 1. Role / Persona.
		$role = ! empty( $active['role'] ) ? trim( (string) $active['role'] ) : '';
		if ( '' !== $role ) {
			$parts[] = "ROLE:\n" . $role;
		}

		// 2. Base System Prompt / Instructions.
		$prompt = ! empty( $active['system_prompt'] ) ? trim( (string) $active['system_prompt'] ) : '';
		if ( '' !== $prompt ) {
			$parts[] = "SYSTEM INSTRUCTIONS:\n" . $prompt;
		}

		// 3. Tone & Response Style.
		$tone_slug  = ! empty( $active['tone'] ) ? (string) $active['tone'] : 'professional';
		$style_slug = ! empty( $active['response_style'] ) ? (string) $active['response_style'] : 'balanced';

		$tone_label  = self::ALLOWED_TONES[ $tone_slug ] ?? 'Professional';
		$style_label = self::ALLOWED_STYLES[ $style_slug ] ?? 'Balanced';

		$parts[] = "TONE:\n" . $tone_label . "\n\nRESPONSE STYLE:\n" . $style_label;

		// 4. Behavioral Rules.
		$rules = ! empty( $active['rules'] ) ? trim( (string) $active['rules'] ) : '';
		if ( '' !== $rules ) {
			$parts[] = "RULES:\n" . $rules;
		}

		// 5. Fallback Response Guidance (if defined).
		$fallback = ! empty( $active['fallback_message'] ) ? trim( (string) $active['fallback_message'] ) : '';
		if ( '' !== $fallback ) {
			$parts[] = "FALLBACK MESSAGE:\nIf you cannot answer accurately or lack necessary knowledge, respond with:\n\"" . $fallback . '"';
		}

		$effective = trim( implode( "\n\n", $parts ) );

		if ( '' !== $effective ) {
			return $effective;
		}

		// Secondary fallback: legacy setting.
		$legacy = SettingsService::get( 'system_instruction', '' );
		if ( is_string( $legacy ) && '' !== trim( $legacy ) ) {
			return trim( $legacy );
		}

		return self::DEFAULT_FALLBACK_PROMPT;
	}

	/**
	 * Sanitizes raw profile input fields.
	 *
	 * Unknown keys are strictly discarded.
	 *
	 * @param array<string, mixed> $raw Raw submitted input.
	 * @return array<string, mixed> Sanitized profile data array.
	 */
	public function sanitize_profile( array $raw ): array {
		$sanitized = [];

		// Profile Name (max 100 chars, required).
		$name = isset( $raw['name'] ) ? sanitize_text_field( (string) $raw['name'] ) : '';
		if ( mb_strlen( $name ) > self::MAX_NAME_LENGTH ) {
			$name = mb_substr( $name, 0, self::MAX_NAME_LENGTH );
		}
		$sanitized['name'] = ! empty( $name ) ? $name : __( 'Untitled Profile', 'gemini-chat-assistant' );

		// Description (admin metadata only, max 1000 chars).
		$desc = isset( $raw['description'] ) ? sanitize_textarea_field( (string) $raw['description'] ) : '';
		if ( mb_strlen( $desc ) > self::MAX_DESCRIPTION_LENGTH ) {
			$desc = mb_substr( $desc, 0, self::MAX_DESCRIPTION_LENGTH );
		}
		$sanitized['description'] = $desc;

		// Role / Purpose (max 2000 chars).
		$role = isset( $raw['role'] ) ? sanitize_textarea_field( (string) $raw['role'] ) : '';
		if ( mb_strlen( $role ) > self::MAX_ROLE_LENGTH ) {
			$role = mb_substr( $role, 0, self::MAX_ROLE_LENGTH );
		}
		$sanitized['role'] = $role;

		// Tone (whitelist).
		$tone = isset( $raw['tone'] ) ? sanitize_key( (string) $raw['tone'] ) : 'professional';
		$sanitized['tone'] = array_key_exists( $tone, self::ALLOWED_TONES ) ? $tone : 'professional';

		// System Prompt (max 15000 chars).
		$prompt = isset( $raw['system_prompt'] ) ? sanitize_textarea_field( (string) $raw['system_prompt'] ) : '';
		if ( mb_strlen( $prompt ) > self::MAX_PROMPT_LENGTH ) {
			$prompt = mb_substr( $prompt, 0, self::MAX_PROMPT_LENGTH );
		}
		$sanitized['system_prompt'] = $prompt;

		// Behavior Rules (max 10000 chars).
		$rules = isset( $raw['rules'] ) ? sanitize_textarea_field( (string) $raw['rules'] ) : '';
		if ( mb_strlen( $rules ) > self::MAX_RULES_LENGTH ) {
			$rules = mb_substr( $rules, 0, self::MAX_RULES_LENGTH );
		}
		$sanitized['rules'] = $rules;

		// Response Style (whitelist).
		$style = isset( $raw['response_style'] ) ? sanitize_key( (string) $raw['response_style'] ) : 'balanced';
		$sanitized['response_style'] = array_key_exists( $style, self::ALLOWED_STYLES ) ? $style : 'balanced';

		// Fallback Message (max 2000 chars).
		$fallback = isset( $raw['fallback_message'] ) ? sanitize_textarea_field( (string) $raw['fallback_message'] ) : '';
		if ( mb_strlen( $fallback ) > self::MAX_FALLBACK_LENGTH ) {
			$fallback = mb_substr( $fallback, 0, self::MAX_FALLBACK_LENGTH );
		}
		$sanitized['fallback_message'] = $fallback;

		// Enabled.
		$sanitized['enabled'] = ! empty( $raw['enabled'] );

		return $sanitized;
	}

	/**
	 * Creates a new AI profile.
	 *
	 * @param array<string, mixed> $data Raw profile input.
	 * @return string|\WP_Error Generated UUID on success, \WP_Error on failure.
	 */
	public function create_profile( array $data ) {
		$profiles = $this->get_all_profiles();

		if ( count( $profiles ) >= self::MAX_PROFILES ) {
			return new \WP_Error(
				'PROFILE_LIMIT_REACHED',
				sprintf(
					/* translators: %d: Maximum allowed profiles */
					__( 'Maximum profile limit of %d has been reached. Please delete an existing profile first.', 'gemini-chat-assistant' ),
					self::MAX_PROFILES
				)
			);
		}

		$sanitized = $this->sanitize_profile( $data );
		$id        = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'gca_prof_', true );
		$now       = current_time( 'mysql' );

		$sanitized['id']         = $id;
		$sanitized['created_at'] = $now;
		$sanitized['updated_at'] = $now;

		$profiles[ $id ] = $sanitized;

		update_option( self::OPTION_KEY, $profiles, 'no' );

		// If this is the only profile or first enabled profile, make it active.
		if ( empty( $this->get_active_profile_id() ) && $sanitized['enabled'] ) {
			$this->set_active_profile_id( $id );
		}

		return $id;
	}

	/**
	 * Updates an existing AI profile.
	 *
	 * @param string               $id   Profile UUID.
	 * @param array<string, mixed> $data Raw updated fields.
	 * @return bool|\WP_Error True on success, \WP_Error on failure.
	 */
	public function update_profile( string $id, array $data ) {
		$profiles = $this->get_all_profiles();

		if ( ! isset( $profiles[ $id ] ) ) {
			return new \WP_Error( 'PROFILE_NOT_FOUND', __( 'AI profile not found.', 'gemini-chat-assistant' ) );
		}

		$sanitized = $this->sanitize_profile( $data );
		$existing  = $profiles[ $id ];

		// Preserve immutable metadata.
		$sanitized['id']         = $id;
		$sanitized['created_at'] = $existing['created_at'] ?? current_time( 'mysql' );
		$sanitized['updated_at'] = current_time( 'mysql' );

		// If currently active profile is being disabled, ensure another enabled profile is selected.
		$is_active = ( $this->get_active_profile_id() === $id );
		if ( $is_active && ! $sanitized['enabled'] ) {
			// Try to find another enabled profile.
			$switched = false;
			foreach ( $profiles as $other_id => $other_profile ) {
				if ( $other_id !== $id && ! empty( $other_profile['enabled'] ) ) {
					$this->set_active_profile_id( $other_id );
					$switched = true;
					break;
				}
			}

			if ( ! $switched ) {
				return new \WP_Error(
					'CANNOT_DISABLE_ACTIVE',
					__( 'Cannot disable the active profile because no other enabled profile exists. Please enable another profile first.', 'gemini-chat-assistant' )
				);
			}
		}

		$profiles[ $id ] = $sanitized;
		update_option( self::OPTION_KEY, $profiles, 'no' );

		return true;
	}

	/**
	 * Duplicates an existing AI profile.
	 *
	 * @param string $id Source profile UUID.
	 * @return string|\WP_Error New profile UUID on success, \WP_Error on failure.
	 */
	public function duplicate_profile( string $id ) {
		$profiles = $this->get_all_profiles();

		if ( ! isset( $profiles[ $id ] ) ) {
			return new \WP_Error( 'PROFILE_NOT_FOUND', __( 'AI profile not found.', 'gemini-chat-assistant' ) );
		}

		if ( count( $profiles ) >= self::MAX_PROFILES ) {
			return new \WP_Error(
				'PROFILE_LIMIT_REACHED',
				sprintf(
					/* translators: %d: Maximum allowed profiles */
					__( 'Maximum profile limit of %d reached. Cannot duplicate profile.', 'gemini-chat-assistant' ),
					self::MAX_PROFILES
				)
			);
		}

		$source = $profiles[ $id ];
		$new_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'gca_prof_', true );
		$now    = current_time( 'mysql' );

		$copy = $source;
		$copy['id']         = $new_id;
		$copy['name']       = sprintf( __( '%s Copy', 'gemini-chat-assistant' ), $source['name'] );
		$copy['created_at'] = $now;
		$copy['updated_at'] = $now;
		// A duplicate is never set as active automatically.

		$profiles[ $new_id ] = $copy;
		update_option( self::OPTION_KEY, $profiles, 'no' );

		return $new_id;
	}

	/**
	 * Deletes an AI profile safely.
	 *
	 * Cannot delete the active profile unless another enabled profile is available.
	 * Never leaves 0 profiles without recreating the safe default.
	 *
	 * @param string $id Profile UUID to delete.
	 * @return bool|\WP_Error True on success, \WP_Error on failure.
	 */
	public function delete_profile( string $id ) {
		$profiles = $this->get_all_profiles();

		if ( ! isset( $profiles[ $id ] ) ) {
			return new \WP_Error( 'PROFILE_NOT_FOUND', __( 'AI profile not found.', 'gemini-chat-assistant' ) );
		}

		$is_active = ( $this->get_active_profile_id() === $id );

		// If deleting the active profile, try to switch active to another enabled profile first.
		if ( $is_active ) {
			$next_active = null;
			foreach ( $profiles as $other_id => $other_profile ) {
				if ( $other_id !== $id && ! empty( $other_profile['enabled'] ) ) {
					$next_active = $other_id;
					break;
				}
			}

			if ( null !== $next_active ) {
				$this->set_active_profile_id( $next_active );
			} elseif ( count( $profiles ) <= 1 ) {
				return new \WP_Error(
					'CANNOT_DELETE_LAST_PROFILE',
					__( 'Cannot delete the only remaining profile. At least one valid profile must exist.', 'gemini-chat-assistant' )
				);
			} else {
				return new \WP_Error(
					'CANNOT_DELETE_ACTIVE_WITHOUT_ENABLED',
					__( 'Cannot delete the active profile because no other enabled profile exists to take its place. Please enable another profile first.', 'gemini-chat-assistant' )
				);
			}
		}

		unset( $profiles[ $id ] );

		// If 0 profiles remain, regenerate safe default.
		if ( empty( $profiles ) ) {
			$this->migrate_legacy_prompt();
			return true;
		}

		update_option( self::OPTION_KEY, $profiles, 'no' );

		return true;
	}

	/**
	 * Activates an AI profile.
	 *
	 * Requires the profile to exist and be enabled.
	 *
	 * @param string $id Profile UUID to activate.
	 * @return bool|\WP_Error True on success, \WP_Error on failure.
	 */
	public function activate_profile( string $id ) {
		$profiles = $this->get_all_profiles();

		if ( ! isset( $profiles[ $id ] ) ) {
			return new \WP_Error( 'PROFILE_NOT_FOUND', __( 'AI profile not found.', 'gemini-chat-assistant' ) );
		}

		if ( empty( $profiles[ $id ]['enabled'] ) ) {
			return new \WP_Error(
				'CANNOT_ACTIVATE_DISABLED',
				__( 'Cannot activate a disabled profile. Please enable it first.', 'gemini-chat-assistant' )
			);
		}

		$this->set_active_profile_id( $id );
		return true;
	}
}
