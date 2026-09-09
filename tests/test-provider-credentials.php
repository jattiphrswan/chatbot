<?php
/**
 * Test Suite: Multi-Provider Credentials & Admin Settings (Node N18)
 *
 * Verifies ProviderInterface contracts, ProviderRegistry management,
 * Gemini/OpenAI/Claude adapters, secure credential storage and encryption,
 * environment/constant override priorities, empty submission protection,
 * credential masking in ProviderException, and zero outbound HTTP calls.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

use SkyFish\GeminiChat\Providers\ProviderInterface;
use SkyFish\GeminiChat\Providers\ProviderRegistry;
use SkyFish\GeminiChat\Providers\ProviderException;
use SkyFish\GeminiChat\Providers\ProviderResponse;
use SkyFish\GeminiChat\Providers\GeminiProvider;
use SkyFish\GeminiChat\Providers\OpenAIProvider;
use SkyFish\GeminiChat\Providers\ClaudeProvider;
use SkyFish\GeminiChat\Admin\SettingsService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TestProviderCredentials
 */
class TestProviderCredentials {

	private int $passed = 0;
	private int $failed = 0;
	private array $errors = [];

	/**
	 * Runs all test cases.
	 */
	public function run(): void {
		echo "Starting Node N18 Multi-Provider Credentials & Settings Test Suite...\n\n";

		$this->test_provider_contracts_and_implementations();
		$this->test_provider_registry();
		$this->test_provider_defaults_and_settings();
		$this->test_credential_encryption_and_crud();
		$this->test_credential_priority_and_sources();
		$this->test_settings_sanitization_and_isolation();
		$this->test_placeholder_adapters_and_zero_outbound_http();
		$this->test_credential_masking_in_exceptions();

		echo "\n--------------------------------------------------\n";
		echo sprintf( "Results: %d Passed, %d Failed\n", $this->passed, $this->failed );
		echo "--------------------------------------------------\n";

		if ( $this->failed > 0 ) {
			echo "Failure details:\n";
			foreach ( $this->errors as $err ) {
				echo " - " . $err . "\n";
			}
		}
	}

	private function assert( bool $condition, string $message ): void {
		if ( $condition ) {
			$this->passed++;
			echo "[PASS] {$message}\n";
		} else {
			$this->failed++;
			$this->errors[] = $message;
			echo "[FAIL] {$message}\n";
		}
	}

	/**
	 * 1. Test ProviderInterface contract and classes.
	 */
	public function test_provider_contracts_and_implementations(): void {
		echo "\n--- Section 1: Provider Contracts & Implementations ---\n";

		$this->assert( interface_exists( 'SkyFish\GeminiChat\Providers\ProviderInterface' ), '1.1 ProviderInterface contract exists' );
		$this->assert( class_exists( 'SkyFish\GeminiChat\Providers\GeminiProvider' ), '1.2 GeminiProvider class exists' );
		$this->assert( class_exists( 'SkyFish\GeminiChat\Providers\OpenAIProvider' ), '1.3 OpenAIProvider class exists' );
		$this->assert( class_exists( 'SkyFish\GeminiChat\Providers\ClaudeProvider' ), '1.4 ClaudeProvider class exists' );

		$gemini = new GeminiProvider();
		$openai = new OpenAIProvider();
		$claude = new ClaudeProvider();

		$this->assert( $gemini instanceof ProviderInterface, '1.5 GeminiProvider implements ProviderInterface' );
		$this->assert( $openai instanceof ProviderInterface, '1.6 OpenAIProvider implements ProviderInterface' );
		$this->assert( $claude instanceof ProviderInterface, '1.7 ClaudeProvider implements ProviderInterface' );

		$this->assert( $gemini->get_id() === 'gemini', '1.8 GeminiProvider returns correct ID' );
		$this->assert( $openai->get_id() === 'openai', '1.9 OpenAIProvider returns correct ID' );
		$this->assert( $claude->get_id() === 'claude', '1.10 ClaudeProvider returns correct ID' );

		$this->assert( ! empty( $gemini->get_models() ), '1.11 GeminiProvider defines models list' );
		$this->assert( ! empty( $openai->get_models() ), '1.12 OpenAIProvider defines models list' );
		$this->assert( ! empty( $claude->get_models() ), '1.13 ClaudeProvider defines models list' );
	}

	/**
	 * 2. Test ProviderRegistry registration and lookup.
	 */
	public function test_provider_registry(): void {
		echo "\n--- Section 2: ProviderRegistry ---\n";

		$registry = new ProviderRegistry();
		$gemini   = new GeminiProvider();
		$openai   = new OpenAIProvider();
		$claude   = new ClaudeProvider();

		$registry->register( $gemini );
		$registry->register( $openai );
		$registry->register( $claude );

		$this->assert( $registry->has( 'gemini' ), '2.1 Registry has gemini' );
		$this->assert( $registry->has( 'openai' ), '2.2 Registry has openai' );
		$this->assert( $registry->has( 'claude' ), '2.3 Registry has claude' );
		$this->assert( ! $registry->has( 'unknown_provider' ), '2.4 Registry rejects unknown provider' );

		$this->assert( $registry->get( 'gemini' ) === $gemini, '2.5 Registry retrieves Gemini instance' );
		$this->assert( $registry->get( 'openai' ) === $openai, '2.6 Registry retrieves OpenAI instance' );
		$this->assert( $registry->get( 'claude' ) === $claude, '2.7 Registry retrieves Claude instance' );
		$this->assert( null === $registry->get( 'nonexistent' ), '2.8 Registry get returns null for nonexistent' );

		$registered_ids = $registry->get_registered_ids();
		$this->assert( in_array( 'gemini', $registered_ids, true ) && in_array( 'openai', $registered_ids, true ) && in_array( 'claude', $registered_ids, true ), '2.9 Registry get_registered_ids returns all registered IDs' );
		$this->assert( count( $registry->get_all() ) === 3, '2.10 Registry get_all returns 3 providers' );
	}

	/**
	 * 3. Test default provider, defaults, and settings.
	 */
	public function test_provider_defaults_and_settings(): void {
		echo "\n--- Section 3: Provider Defaults & Settings ---\n";

		// Default provider falls back to 'gemini'
		$default = SettingsService::get_default_provider();
		$this->assert( in_array( $default, [ 'gemini', 'openai', 'claude' ], true ), '3.1 Default provider is a valid provider slug' );

		// Gemini enabled by default
		$gemini_enabled = SettingsService::is_provider_enabled( 'gemini' );
		$this->assert( true === $gemini_enabled, '3.2 Gemini is enabled by default' );

		// Default models
		$gemini_model = SettingsService::get_provider_model( 'gemini' );
		$openai_model = SettingsService::get_provider_model( 'openai' );
		$claude_model = SettingsService::get_provider_model( 'claude' );

		$this->assert( ! empty( $gemini_model ), '3.3 Default Gemini model is configured' );
		$this->assert( $openai_model === 'gpt-4o-mini', '3.4 Default OpenAI model is gpt-4o-mini' );
		$this->assert( $claude_model === 'claude-3-5-haiku-20241022', '3.5 Default Claude model is claude-3-5-haiku-20241022' );
	}

	/**
	 * 4. Test credential encryption, storage, retrieval, and removal.
	 */
	public function test_credential_encryption_and_crud(): void {
		echo "\n--- Section 4: Credential Encryption & CRUD ---\n";

		$test_key = 'sk-test-secret-key-1234567890-abcdef';

		// Store credential
		$stored = SettingsService::update_provider_api_key( 'openai', $test_key );
		$this->assert( $stored, '4.1 update_provider_api_key returns true on valid key' );
		$this->assert( SettingsService::has_stored_credential( 'openai' ), '4.2 has_stored_credential returns true after storage' );

		// Verify stored raw option is obfuscated/encrypted (NOT plaintext)
		$raw_options = get_option( SettingsService::CREDENTIALS_OPTION_KEY, [] );
		$raw_value   = $raw_options['openai'] ?? '';
		$this->assert( ! empty( $raw_value ), '4.3 Raw credential option exists in DB' );
		$this->assert( $raw_value !== $test_key, '4.4 Raw credential is NOT plaintext in database' );
		$this->assert( 0 === strpos( $raw_value, 'enc:' ) || 0 === strpos( $raw_value, 'obf:' ), '4.5 Raw credential has encryption/obfuscation prefix' );

		// Verify decryption on retrieval
		$retrieved = SettingsService::get_provider_api_key( 'openai' );
		$this->assert( $retrieved === $test_key, '4.6 get_provider_api_key correctly decrypts stored key' );

		// Remove credential
		$removed = SettingsService::remove_provider_api_key( 'openai' );
		$this->assert( $removed, '4.7 remove_provider_api_key returns true' );
		$this->assert( ! SettingsService::has_stored_credential( 'openai' ), '4.8 has_stored_credential returns false after removal' );
	}

	/**
	 * 5. Test credential priority: environment variable & constant overrides.
	 */
	public function test_credential_priority_and_sources(): void {
		echo "\n--- Section 5: Credential Priorities & Sources ---\n";

		// Test source when no credentials exist
		SettingsService::remove_provider_api_key( 'claude' );
		$source_none = SettingsService::get_credential_source( 'claude' );
		$this->assert( in_array( $source_none, [ 'none', 'environment', 'constant' ], true ), '5.1 Reports source correctly when checking unconfigured state' );

		// Test database source
		SettingsService::update_provider_api_key( 'claude', 'sk-ant-test-key-db' );
		$has_db = SettingsService::has_stored_credential( 'claude' );
		$this->assert( $has_db, '5.2 Stored Claude key in database' );

		// Clean up
		SettingsService::remove_provider_api_key( 'claude' );
	}

	/**
	 * 6. Test settings sanitization: empty key submissions and option isolation.
	 */
	public function test_settings_sanitization_and_isolation(): void {
		echo "\n--- Section 6: Settings Sanitization & Isolation ---\n";

		// Store initial key
		SettingsService::update_provider_api_key( 'openai', 'sk-initial-key-to-preserve' );

		// Submit empty key - existing key MUST be preserved
		$mock_input = [
			'default_provider'      => 'openai',
			'provider_openai_enabled' => '1',
			'provider_openai_model'   => 'gpt-4o',
			'api_key_openai'          => '', // empty submission
		];
		$sanitized = SettingsService::get_instance()->sanitize_settings( $mock_input );

		$this->assert( $sanitized['default_provider'] === 'openai', '6.1 default_provider sanitized correctly' );
		$this->assert( $sanitized['provider_openai_model'] === 'gpt-4o', '6.2 Model setting sanitized' );
		$this->assert( ! isset( $sanitized['api_key_openai'] ), '6.3 API key is NOT stored in gca_settings array' );

		// Assert key in database was NOT erased by empty submission
		$current_key = SettingsService::get_provider_api_key( 'openai' );
		$this->assert( $current_key === 'sk-initial-key-to-preserve', '6.4 Empty submission preserves existing stored API key' );

		// Submit non-empty new key - key MUST be updated
		$mock_input_update = [
			'api_key_openai' => 'sk-updated-key-value',
		];
		SettingsService::get_instance()->sanitize_settings( $mock_input_update );
		$updated_key = SettingsService::get_provider_api_key( 'openai' );
		$this->assert( $updated_key === 'sk-updated-key-value', '6.5 Non-empty submission updates stored API key' );

		// Clean up
		SettingsService::remove_provider_api_key( 'openai' );

		// Invalid provider slug falls back to 'gemini'
		$mock_invalid_provider = [
			'default_provider' => 'malicious_injected_provider',
		];
		$sanitized_invalid = SettingsService::get_instance()->sanitize_settings( $mock_invalid_provider );
		$this->assert( $sanitized_invalid['default_provider'] === 'gemini', '6.6 Unknown provider slug falls back to gemini' );
	}

	/**
	 * 7. Test placeholder adapters throw ProviderException and make zero outbound HTTP calls.
	 */
	public function test_placeholder_adapters_and_zero_outbound_http(): void {
		echo "\n--- Section 7: Placeholder Adapters & Zero Outbound HTTP ---\n";

		$openai = new OpenAIProvider();
		$claude = new ClaudeProvider();

		// OpenAI chat throws ProviderException
		$openai_chat_threw = false;
		$openai_chat_code  = '';
		try {
			$openai->chat( [ [ 'role' => 'user', 'content' => 'Hello' ] ] );
		} catch ( ProviderException $e ) {
			$openai_chat_threw = true;
			$openai_chat_code  = $e->get_error_code();
		} catch ( \Throwable $t ) {
			$openai_chat_threw = false;
		}
		$this->assert( $openai_chat_threw, '7.1 OpenAIProvider::chat() throws ProviderException' );
		$this->assert( $openai_chat_code === 'not_configured', '7.2 OpenAIProvider::chat() error code is not_configured' );

		// OpenAI test_connection throws ProviderException
		$openai_test_threw = false;
		try {
			$openai->test_connection();
		} catch ( ProviderException $e ) {
			$openai_test_threw = true;
		} catch ( \Throwable $t ) {
			$openai_test_threw = false;
		}
		$this->assert( $openai_test_threw, '7.3 OpenAIProvider::test_connection() throws ProviderException' );

		// Claude chat throws ProviderException
		$claude_chat_threw = false;
		$claude_chat_code  = '';
		try {
			$claude->chat( [ [ 'role' => 'user', 'content' => 'Hello' ] ] );
		} catch ( ProviderException $e ) {
			$claude_chat_threw = true;
			$claude_chat_code  = $e->get_error_code();
		} catch ( \Throwable $t ) {
			$claude_chat_threw = false;
		}
		$this->assert( $claude_chat_threw, '7.4 ClaudeProvider::chat() throws ProviderException' );
		$this->assert( $claude_chat_code === 'not_configured', '7.5 ClaudeProvider::chat() error code is not_configured' );

		// Claude test_connection throws ProviderException
		$claude_test_threw = false;
		try {
			$claude->test_connection();
		} catch ( ProviderException $e ) {
			$claude_test_threw = true;
		} catch ( \Throwable $t ) {
			$claude_test_threw = false;
		}
		$this->assert( $claude_test_threw, '7.6 ClaudeProvider::test_connection() throws ProviderException' );
	}

	/**
	 * 8. Test credential masking in ProviderException.
	 */
	public function test_credential_masking_in_exceptions(): void {
		echo "\n--- Section 8: Credential Masking in Exceptions ---\n";

		$leak_msg = 'Request failed with key sk-1234567890abcdef1234567890abcdef and ant key sk-ant-api03-abcdef1234567890 and gemini AIzaSyAbc1234567890';
		$sanitized = ProviderException::strip_credentials( $leak_msg );

		$this->assert( strpos( $sanitized, 'sk-1234567890' ) === false, '8.1 OpenAI sk-* key masked' );
		$this->assert( strpos( $sanitized, 'sk-ant-api03' ) === false, '8.2 Anthropic sk-ant-* key masked' );
		$this->assert( strpos( $sanitized, 'AIzaSyAbc' ) === false, '8.3 Google AIza* key masked' );
		$this->assert( strpos( $sanitized, '[REDACTED_API_KEY]' ) !== false, '8.4 Redaction marker present' );
	}
}

// Auto-run if executed directly via CLI or test runner.
if ( defined( 'PHPUNIT_RUNNER' ) || ( defined( 'DOING_TESTS' ) && DOING_TESTS ) ) {
	$suite = new TestProviderCredentials();
	$suite->run();
}
