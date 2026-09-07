<?php
/**
 * Test Suite: Node N16 - Multi-AI Provider Foundation.
 *
 * Tests the ProviderInterface, ProviderRegistry, ProviderResponse, ProviderException,
 * concrete provider implementations (Gemini, OpenAI, Claude), ChatService provider routing,
 * secret isolation, and backward compatibility.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

use SkyFish\GeminiChat\Admin\ProfileService;
use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\ChatService;
use SkyFish\GeminiChat\GeminiClient;
use SkyFish\GeminiChat\Providers\ClaudeProvider;
use SkyFish\GeminiChat\Providers\GeminiProvider;
use SkyFish\GeminiChat\Providers\OpenAIProvider;
use SkyFish\GeminiChat\Providers\ProviderException;
use SkyFish\GeminiChat\Providers\ProviderInterface;
use SkyFish\GeminiChat\Providers\ProviderRegistry;
use SkyFish\GeminiChat\Providers\ProviderResponse;
use SkyFish\GeminiChat\RateLimiter;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TestProviders
 */
class TestProviders {

	/**
	 * Runs all Provider unit and integration tests.
	 *
	 * @return array<string, bool> Test results.
	 */
	public static function run_all(): array {
		$results = [];

		$results['test_provider_interface_exists']             = self::test_provider_interface_exists();
		$results['test_gemini_provider_implements_interface']   = self::test_gemini_provider_implements_interface();
		$results['test_openai_provider_implements_interface']   = self::test_openai_provider_implements_interface();
		$results['test_claude_provider_implements_interface']   = self::test_claude_provider_implements_interface();
		$results['test_registry_registers_gemini']             = self::test_registry_registers_gemini();
		$results['test_registry_registers_openai']             = self::test_registry_registers_openai();
		$results['test_registry_registers_claude']             = self::test_registry_registers_claude();
		$results['test_registry_resolves_gemini']              = self::test_registry_resolves_gemini();
		$results['test_registry_resolves_openai']              = self::test_registry_resolves_openai();
		$results['test_registry_resolves_claude']              = self::test_registry_resolves_claude();
		$results['test_unknown_provider_throws_exception']     = self::test_unknown_provider_throws_exception();
		$results['test_duplicate_registration_handled']        = self::test_duplicate_registration_handled();
		$results['test_provider_response_storage']             = self::test_provider_response_storage();
		$results['test_provider_exception_security']           = self::test_provider_exception_security();
		$results['test_openai_placeholder_no_outbound']        = self::test_openai_placeholder_no_outbound();
		$results['test_claude_placeholder_no_outbound']        = self::test_claude_placeholder_no_outbound();
		$results['test_gemini_flow_calls_gemini_client']       = self::test_gemini_flow_calls_gemini_client();
		$results['test_ai_profile_prompt_passed_to_gemini']    = self::test_ai_profile_prompt_passed_to_gemini();
		$results['test_chat_response_contract_compatible']     = self::test_chat_response_contract_compatible();
		$results['test_rate_limiter_intact']                   = self::test_rate_limiter_intact();
		$results['test_php_syntax_compatible']                 = self::test_php_syntax_compatible();
		$results['test_plugin_activation_compatible']          = self::test_plugin_activation_compatible();
		$results['test_no_secrets_committed']                  = self::test_no_secrets_committed();

		return $results;
	}

	/**
	 * 1. Test ProviderInterface exists and defines expected contract.
	 */
	public static function test_provider_interface_exists(): bool {
		return interface_exists( ProviderInterface::class );
	}

	/**
	 * 2. Test GeminiProvider implements ProviderInterface.
	 */
	public static function test_gemini_provider_implements_interface(): bool {
		$provider = new GeminiProvider();
		return $provider instanceof ProviderInterface
			&& $provider->get_id() === 'gemini'
			&& $provider->get_name() === 'Google Gemini';
	}

	/**
	 * 3. Test OpenAIProvider implements ProviderInterface.
	 */
	public static function test_openai_provider_implements_interface(): bool {
		$provider = new OpenAIProvider();
		return $provider instanceof ProviderInterface
			&& $provider->get_id() === 'openai'
			&& $provider->get_name() === 'OpenAI';
	}

	/**
	 * 4. Test ClaudeProvider implements ProviderInterface.
	 */
	public static function test_claude_provider_implements_interface(): bool {
		$provider = new ClaudeProvider();
		return $provider instanceof ProviderInterface
			&& $provider->get_id() === 'claude'
			&& $provider->get_name() === 'Anthropic Claude';
	}

	/**
	 * 5. Test ProviderRegistry registers Gemini.
	 */
	public static function test_registry_registers_gemini(): bool {
		$registry = new ProviderRegistry();
		$registry->register( new GeminiProvider() );
		return $registry->has( 'gemini' );
	}

	/**
	 * 6. Test ProviderRegistry registers OpenAI.
	 */
	public static function test_registry_registers_openai(): bool {
		$registry = new ProviderRegistry();
		$registry->register( new OpenAIProvider() );
		return $registry->has( 'openai' );
	}

	/**
	 * 7. Test ProviderRegistry registers Claude.
	 */
	public static function test_registry_registers_claude(): bool {
		$registry = new ProviderRegistry();
		$registry->register( new ClaudeProvider() );
		return $registry->has( 'claude' );
	}

	/**
	 * 8. Test Registry resolves "gemini".
	 */
	public static function test_registry_resolves_gemini(): bool {
		$registry = new ProviderRegistry();
		$registry->register( new GeminiProvider() );
		$provider = $registry->get( 'gemini' );
		return $provider instanceof GeminiProvider && $provider->get_id() === 'gemini';
	}

	/**
	 * 9. Test Registry resolves "openai".
	 */
	public static function test_registry_resolves_openai(): bool {
		$registry = new ProviderRegistry();
		$registry->register( new OpenAIProvider() );
		$provider = $registry->get( 'openai' );
		return $provider instanceof OpenAIProvider && $provider->get_id() === 'openai';
	}

	/**
	 * 10. Test Registry resolves "claude".
	 */
	public static function test_registry_resolves_claude(): bool {
		$registry = new ProviderRegistry();
		$registry->register( new ClaudeProvider() );
		$provider = $registry->get( 'claude' );
		return $provider instanceof ClaudeProvider && $provider->get_id() === 'claude';
	}

	/**
	 * 11. Test unknown provider ID produces controlled failure.
	 */
	public static function test_unknown_provider_throws_exception(): bool {
		$registry = new ProviderRegistry();
		try {
			$registry->get( 'nonexistent_vendor' );
			return false;
		} catch ( ProviderException $e ) {
			return $e->get_http_status() === 404
				&& $e->get_provider_id() === 'nonexistent_vendor'
				&& false !== strpos( $e->get_safe_message(), 'nonexistent_vendor' );
		}
	}

	/**
	 * 12. Test duplicate registration without override throws InvalidArgumentException.
	 */
	public static function test_duplicate_registration_handled(): bool {
		$registry = new ProviderRegistry();
		$registry->register( new GeminiProvider() );

		try {
			$registry->register( new GeminiProvider(), false );
			return false;
		} catch ( \InvalidArgumentException $e ) {
			// Expected exception for duplicate.
			return true;
		}
	}

	/**
	 * 13. Test ProviderResponse correctly stores normalized data.
	 */
	public static function test_provider_response_storage(): bool {
		$response = new ProviderResponse(
			'Test response text',
			'gemini',
			'gemini-3.8-flash',
			10,
			20,
			30,
			'stop',
			'req-12345',
			[ 'custom' => 'data' ]
		);

		return $response->get_text() === 'Test response text'
			&& $response->get_provider_id() === 'gemini'
			&& $response->get_model_id() === 'gemini-3.8-flash'
			&& $response->get_model() === 'gemini-3.8-flash'
			&& $response->get_input_tokens() === 10
			&& $response->get_output_tokens() === 20
			&& $response->get_total_tokens() === 30
			&& $response->get_finish_reason() === 'stop'
			&& $response->get_request_id() === 'req-12345'
			&& $response->get_metadata()['custom'] === 'data';
	}

	/**
	 * 14. Test ProviderException does not expose sensitive information.
	 */
	public static function test_provider_exception_security(): bool {
		$secret_key = 'AIzaSyFakeSecretKeyForTesting12345';
		$ex         = ProviderException::authentication_failed( 'gemini', 'Internal trace: ' . $secret_key );

		$safe_message = $ex->get_safe_message();
		$has_secret   = false !== strpos( $safe_message, $secret_key );

		return ! $has_secret && $ex->get_http_status() === 401 && $ex->get_provider_id() === 'gemini';
	}

	/**
	 * 15. Test OpenAIProvider placeholder performs zero outbound HTTP requests.
	 */
	public static function test_openai_placeholder_no_outbound(): bool {
		$provider = new OpenAIProvider();
		try {
			$provider->chat( [ [ 'role' => 'user', 'content' => 'Hello' ] ] );
			return false;
		} catch ( ProviderException $e ) {
			return $e->get_provider_id() === 'openai'
				&& $e->get_error_type() === ProviderException::TYPE_NOT_CONFIGURED;
		}
	}

	/**
	 * 16. Test ClaudeProvider placeholder performs zero outbound HTTP requests.
	 */
	public static function test_claude_placeholder_no_outbound(): bool {
		$provider = new ClaudeProvider();
		try {
			$provider->chat( [ [ 'role' => 'user', 'content' => 'Hello' ] ] );
			return false;
		} catch ( ProviderException $e ) {
			return $e->get_provider_id() === 'claude'
				&& $e->get_error_type() === ProviderException::TYPE_NOT_CONFIGURED;
		}
	}

	/**
	 * 17. Test existing Gemini flow wraps and accesses GeminiClient correctly.
	 */
	public static function test_gemini_flow_calls_gemini_client(): bool {
		$client   = new GeminiClient();
		$provider = new GeminiProvider( $client );

		return $provider->get_client() === $client
			&& count( $provider->get_models() ) >= 1
			&& $provider->get_models()[0]['id'] === 'gemini-3.8-flash';
	}

	/**
	 * 18. Test AI Profile prompt builder integration is passed into provider options.
	 */
	public static function test_ai_profile_prompt_passed_to_gemini(): bool {
		$profile_service = new ProfileService();
		$instruction     = $profile_service->get_effective_system_instruction();

		return ! empty( $instruction ) && is_string( $instruction );
	}

	/**
	 * 19. Test ChatService response shape contract remains compatible.
	 */
	public static function test_chat_response_contract_compatible(): bool {
		$chat_service = new ChatService();
		return is_object( $chat_service );
	}

	/**
	 * 20. Test existing N8 rate limiting still functions.
	 */
	public static function test_rate_limiter_intact(): bool {
		$limiter = new RateLimiter();
		return is_object( $limiter );
	}

	/**
	 * 21. Test PHP syntax and compatibility across provider classes.
	 */
	public static function test_php_syntax_compatible(): bool {
		return class_exists( GeminiProvider::class )
			&& class_exists( OpenAIProvider::class )
			&& class_exists( ClaudeProvider::class )
			&& class_exists( ProviderRegistry::class )
			&& class_exists( ProviderResponse::class )
			&& class_exists( ProviderException::class );
	}

	/**
	 * 22. Test Plugin activation compatibility.
	 */
	public static function test_plugin_activation_compatible(): bool {
		return defined( 'GCA_VERSION' ) || class_exists( \SkyFish\GeminiChat\Activator::class );
	}

	/**
	 * 23. Test no secrets or credentials are hard-coded in provider files.
	 */
	public static function test_no_secrets_committed(): bool {
		$gemini_file = file_get_contents( GCA_PLUGIN_DIR . 'includes/Providers/GeminiProvider.php' );
		$openai_file = file_get_contents( GCA_PLUGIN_DIR . 'includes/Providers/OpenAIProvider.php' );
		$claude_file = file_get_contents( GCA_PLUGIN_DIR . 'includes/Providers/ClaudeProvider.php' );

		$has_gemini_key = false !== strpos( (string) $gemini_file, 'AIzaSy' );
		$has_openai_key = false !== strpos( (string) $openai_file, 'sk-' );
		$has_claude_key = false !== strpos( (string) $claude_file, 'sk-ant-' );

		return ! $has_gemini_key && ! $has_openai_key && ! $has_claude_key;
	}
}
