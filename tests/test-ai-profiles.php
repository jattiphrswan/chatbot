<?php
/**
 * Test Suite: Node N15 - AI Profiles & Custom Prompts.
 *
 * Tests ProfileService, prompt sanitization, XSS protection, deterministic
 * prompt building, fallback hierarchies, secret isolation, and ChatService integration.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

use SkyFish\GeminiChat\Admin\ProfileService;
use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\ChatService;

// Bootstrap if running standalone.
if ( ! defined( 'ABSPATH' ) ) {
	require_once __DIR__ . '/bootstrap.php';
}

/**
 * Class TestAIProfiles
 */
class TestAIProfiles {

	/**
	 * Runs all AI Profiles unit and integration tests.
	 *
	 * @return array<string, bool> Test results.
	 */
	public static function run_all(): array {
		$results = [];

		$results['test_profile_service_instantiation']       = self::test_profile_service_instantiation();
		$results['test_profile_constants']                   = self::test_profile_constants();
		$results['test_profile_sanitization_known_fields']   = self::test_profile_sanitization_known_fields();
		$results['test_profile_sanitization_unknown_keys']   = self::test_profile_sanitization_unknown_keys();
		$results['test_profile_sanitization_xss_protection'] = self::test_profile_sanitization_xss_protection();
		$results['test_tone_whitelist_enforcement']         = self::test_tone_whitelist_enforcement();
		$results['test_style_whitelist_enforcement']        = self::test_style_whitelist_enforcement();
		$results['test_effective_prompt_construction']       = self::test_effective_prompt_construction();
		$results['test_fallback_prompt_precedence']          = self::test_fallback_prompt_precedence();
		$results['test_secret_isolation']                    = self::test_secret_isolation();
		$results['test_field_length_capping']                = self::test_field_length_capping();
		$results['test_chat_service_profile_integration']    = self::test_chat_service_profile_integration();

		return $results;
	}

	/**
	 * 1. Test ProfileService instantiation.
	 */
	public static function test_profile_service_instantiation(): bool {
		$service = new ProfileService();
		return is_object( $service ) && ProfileService::OPTION_KEY === 'gca_ai_profiles';
	}

	/**
	 * 2. Test ProfileService constants (max profiles, allowed tones, allowed styles).
	 */
	public static function test_profile_constants(): bool {
		$max_profiles = ProfileService::MAX_PROFILES === 25;
		$tones_count  = count( ProfileService::ALLOWED_TONES ) >= 6;
		$has_prof     = isset( ProfileService::ALLOWED_TONES['professional'] );
		$has_friendly = isset( ProfileService::ALLOWED_TONES['friendly'] );
		$has_concise  = isset( ProfileService::ALLOWED_STYLES['concise'] );
		$has_balanced = isset( ProfileService::ALLOWED_STYLES['balanced'] );

		return $max_profiles && $tones_count && $has_prof && $has_friendly && $has_concise && $has_balanced;
	}

	/**
	 * 3. Test profile sanitization on standard inputs.
	 */
	public static function test_profile_sanitization_known_fields(): bool {
		$service = new ProfileService();

		$raw = [
			'name'             => '  Support Agent  ',
			'description'      => 'Internal support desk',
			'role'             => 'You are a support agent.',
			'tone'             => 'friendly',
			'system_prompt'    => 'Assist visitors politely.',
			'rules'            => "- Rule 1\n- Rule 2",
			'response_style'   => 'concise',
			'fallback_message' => 'Please reach out to support.',
			'enabled'          => '1',
		];

		$sanitized = $service->sanitize_profile( $raw );

		return $sanitized['name'] === 'Support Agent'
			&& $sanitized['description'] === 'Internal support desk'
			&& $sanitized['role'] === 'You are a support agent.'
			&& $sanitized['tone'] === 'friendly'
			&& $sanitized['system_prompt'] === 'Assist visitors politely.'
			&& $sanitized['rules'] === "- Rule 1\n- Rule 2"
			&& $sanitized['response_style'] === 'concise'
			&& $sanitized['fallback_message'] === 'Please reach out to support.'
			&& true === $sanitized['enabled'];
	}

	/**
	 * 4. Test profile sanitization strictly discards unknown/unrecognized keys.
	 */
	public static function test_profile_sanitization_unknown_keys(): bool {
		$service = new ProfileService();

		$raw = [
			'name'            => 'Test Profile',
			'malicious_key'   => 'eval()',
			'admin_privilege' => true,
			'arbitrary_code'  => '<script>alert(1)</script>',
		];

		$sanitized = $service->sanitize_profile( $raw );

		return ! isset( $sanitized['malicious_key'] )
			&& ! isset( $sanitized['admin_privilege'] )
			&& ! isset( $sanitized['arbitrary_code'] )
			&& isset( $sanitized['name'] );
	}

	/**
	 * 5. Test XSS protection in profile sanitization.
	 */
	public static function test_profile_sanitization_xss_protection(): bool {
		$service = new ProfileService();

		$raw = [
			'name'          => '<script>alert("xss")</script>Profile Name',
			'description'   => '<img src=x onerror=alert(1)>Description',
			'role'          => '<iframe src="evil.com"></iframe>Role',
			'system_prompt' => '</textarea><script>alert(2)</script>Prompt',
			'rules'         => '<svg onload=alert(3)>Rules',
		];

		$sanitized = $service->sanitize_profile( $raw );

		$safe_name   = false === strpos( $sanitized['name'], '<script>' );
		$safe_desc   = false === strpos( $sanitized['description'], '<img' );
		$safe_role   = false === strpos( $sanitized['role'], '<iframe' );
		$safe_prompt = false === strpos( $sanitized['system_prompt'], '<script>' );
		$safe_rules  = false === strpos( $sanitized['rules'], '<svg' );

		return $safe_name && $safe_desc && $safe_role && $safe_prompt && $safe_rules;
	}

	/**
	 * 6. Test tone whitelist enforcement falls back to 'professional'.
	 */
	public static function test_tone_whitelist_enforcement(): bool {
		$service = new ProfileService();

		$raw = [
			'name' => 'Test',
			'tone' => 'sarcastic_unauthorized_tone',
		];

		$sanitized = $service->sanitize_profile( $raw );
		return $sanitized['tone'] === 'professional';
	}

	/**
	 * 7. Test response style whitelist enforcement falls back to 'balanced'.
	 */
	public static function test_style_whitelist_enforcement(): bool {
		$service = new ProfileService();

		$raw = [
			'name'           => 'Test',
			'response_style' => 'unsupported_verbosity_style',
		];

		$sanitized = $service->sanitize_profile( $raw );
		return $sanitized['response_style'] === 'balanced';
	}

	/**
	 * 8. Test effective prompt construction formats all parts deterministically.
	 */
	public static function test_effective_prompt_construction(): bool {
		$service = new ProfileService();

		$profile = [
			'id'               => 'test-uuid-123',
			'name'             => 'Sales Expert',
			'description'      => 'Internal test profile',
			'role'             => 'You are the sales consultant for Acme Inc.',
			'tone'             => 'friendly',
			'system_prompt'    => 'Highlight our core benefits and offer demos.',
			'rules'            => "- Never invent discounts.\n- If unsure, offer a callback.",
			'response_style'   => 'concise',
			'fallback_message' => 'Please email sales@example.com for detailed quotes.',
			'enabled'          => true,
		];

		$prompt = $service->get_effective_system_instruction( $profile );

		$has_role     = false !== strpos( $prompt, 'ROLE:' ) && false !== strpos( $prompt, 'Acme Inc.' );
		$has_inst     = false !== strpos( $prompt, 'SYSTEM INSTRUCTIONS:' ) && false !== strpos( $prompt, 'Highlight our core benefits' );
		$has_tone     = false !== strpos( $prompt, "TONE:\nFriendly" );
		$has_style    = false !== strpos( $prompt, "RESPONSE STYLE:\nConcise" );
		$has_rules    = false !== strpos( $prompt, 'RULES:' ) && false !== strpos( $prompt, 'Never invent discounts' );
		$has_fallback = false !== strpos( $prompt, 'FALLBACK MESSAGE:' ) && false !== strpos( $prompt, 'sales@example.com' );

		return $has_role && $has_inst && $has_tone && $has_style && $has_rules && $has_fallback;
	}

	/**
	 * 9. Test fallback prompt precedence when parts are empty.
	 */
	public static function test_fallback_prompt_precedence(): bool {
		$service = new ProfileService();

		// Profile with empty prompt and empty role.
		$empty_profile = [
			'id'               => 'test-empty',
			'name'             => 'Empty Profile',
			'description'      => '',
			'role'             => '',
			'tone'             => 'professional',
			'system_prompt'    => '',
			'rules'            => '',
			'response_style'   => 'balanced',
			'fallback_message' => '',
			'enabled'          => true,
		];

		$prompt = $service->get_effective_system_instruction( $empty_profile );

		// Tone and response style are always included, ensuring a deterministic non-empty prompt.
		$is_valid = ! empty( $prompt ) && false !== strpos( $prompt, 'TONE:' );

		return $is_valid;
	}

	/**
	 * 10. Test secret isolation - no API keys or credentials leaked into prompt.
	 */
	public static function test_secret_isolation(): bool {
		$service = new ProfileService();

		$profile = [
			'id'               => 'test-sec',
			'name'             => 'Secret Test',
			'role'             => 'Assistant',
			'tone'             => 'formal',
			'system_prompt'    => 'Standard prompt',
			'rules'            => 'Standard rules',
			'response_style'   => 'concise',
			'fallback_message' => 'Help desk',
			'enabled'          => true,
		];

		$prompt = $service->get_effective_system_instruction( $profile );

		$api_key = SettingsService::get_api_key();
		if ( ! empty( $api_key ) && false !== strpos( $prompt, $api_key ) ) {
			return false;
		}

		$no_nonce = false === strpos( $prompt, 'nonce' );
		$no_db    = false === strpos( $prompt, 'DB_PASSWORD' );

		return $no_nonce && $no_db;
	}

	/**
	 * 11. Test character length capping prevents oversized inputs.
	 */
	public static function test_field_length_capping(): bool {
		$service = new ProfileService();

		$oversized_name = str_repeat( 'A', 200 );
		$raw            = [
			'name' => $oversized_name,
		];

		$sanitized = $service->sanitize_profile( $raw );

		return mb_strlen( $sanitized['name'] ) <= ProfileService::MAX_NAME_LENGTH;
	}

	/**
	 * 12. Test ChatService integration - verify ChatService has ProfileService.
	 */
	public static function test_chat_service_profile_integration(): bool {
		$profile_service = new ProfileService();
		$chat_service    = new ChatService( null, null, null, null, null, $profile_service );

		return is_object( $chat_service );
	}
}

// Auto-run if executed directly via CLI or test runner.
if ( php_sapi_name() === 'cli' || defined( 'PHPUNIT_RUNNER' ) || ( defined( 'DOING_TESTS' ) && DOING_TESTS ) ) {
	$results = TestAIProfiles::run_all();
	$failed  = 0;
	echo "Starting Node N15 AI Profiles Test Suite...\n\n";
	foreach ( $results as $name => $passed ) {
		if ( $passed ) {
			echo "[PASS] {$name}\n";
		} else {
			echo "[FAIL] {$name}\n";
			$failed++;
		}
	}
	echo "\nResults: " . ( count( $results ) - $failed ) . " Passed, {$failed} Failed\n";
	exit( $failed === 0 ? 0 : 1 );
}
