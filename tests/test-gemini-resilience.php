<?php
define( 'GCA_GEMINI_TEST_HELPERS_ONLY', true );
require_once __DIR__ . '/test-gemini-client.php';

use SkyFish\GeminiChat\GeminiClient;
use SkyFish\GeminiChat\Admin\SettingsService;

class ResilientTestClient extends GeminiClient {
    public float $now = 1000;
    public array $sleeps = [];
    protected function request_now(): float { return $this->now; }
    protected function retry_sleep( float $seconds ): void { $this->sleeps[] = $seconds; $this->now += $seconds; }
}
if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
    function wp_remote_retrieve_header( $response, $header ) { return $response['headers'][$header] ?? ''; }
}
function check_resilience( bool $ok, string $label ): void {
    if ( ! $ok ) { throw new RuntimeException( $label ); }
    echo "[PASS] $label\n";
}
function upstream( int $status ): array {
    return [ 'response' => [ 'code' => $status ], 'body' => 200 === $status ? '{"candidates":[{"content":{"parts":[{"text":"OK"}]}}]}' : json_encode( [ 'error' => [ 'code' => $status, 'message' => 'upstream secret_resilience' ] ] ) ];
}
function scenario( array $responses, bool $fallback = false, array $options = [] ): array {
    update_option( SettingsService::OPTION_KEY, [ 'provider_gemini_fallback_enabled' => $fallback, 'provider_gemini_fallback_model' => 'gemini-3.7-flash' ] );
    $GLOBALS['retry_responses'] = $responses;
    $GLOBALS['retry_requests'] = [];
    $client = new ResilientTestClient();
    $result = $client->create_interaction( 'hi', null, $options + [ 'model' => 'gemini-3.8-flash', 'request_id' => 'gca_1234567890abcdef', 'history' => [ [ 'role' => 'user', 'content' => 'Earlier' ] ] ] );
    return [ $client, $result, get_option( 'gca_gemini_last_chat' ), $GLOBALS['retry_requests'] ];
}
putenv( 'GEMINI_API_KEY=secret_resilience' );
[ $client, $result, $diag, $requests ] = scenario( [ upstream( 503 ), upstream( 503 ), upstream( 200 ) ] );
check_resilience( ! is_wp_error( $result ) && 3 === count( $requests ) && 2 === $diag['retry_count'], "503 retries recover within three attempts" );
check_resilience( $client->sleeps[0] >= 1 && $client->sleeps[0] <= 1.2 && $client->sleeps[1] >= 2 && $client->sleeps[1] <= 2.2, 'Bounded exponential delays with jitter' );
check_resilience( $requests[0]['args']['body'] === $requests[2]['args']['body'] && $requests[2]['args']['timeout'] < $requests[0]['args']['timeout'], 'Identical payload, shrinking shared deadline' );

[ $client, $result, $diag, $requests ] = scenario( [ upstream( 429 ), upstream( 200 ) ] );
check_resilience( ! is_wp_error( $result ) && 2 === count( $requests ) && 1 === $diag['retry_count'], "429 performs at most one bounded retry" );
foreach ( [ 400, 401, 403, 404, 500, 504 ] as $status ) {
    [ $client, $result, $diag, $requests ] = scenario( [ upstream( $status ), upstream( 200 ) ], true );
    check_resilience( is_wp_error( $result ) && 1 === count( $requests ) && [] === $client->sleeps && 'NO' === $diag['fallback_used'], "$status never retries or falls back" );
}
[ $client, $result, $diag, $requests ] = scenario( [ new WP_Error( 'http_request_failed', 'cURL error 28: timed out' ), upstream( 200 ) ], true );
check_resilience( 'GCA_GEMINI_TIMEOUT' === $result->get_error_code() && 1 === count( $requests ), 'Ambiguous timeout never resubmitted' );
[ $client, $result, $diag, $requests ] = scenario( [ upstream( 503 ), upstream( 503 ), upstream( 200 ) ], true );
check_resilience( 'gemini-3.7-flash' === $result['model'] && 'YES' === $diag['fallback_used'] && 2 === $diag['primary_attempts'] && 3 === $diag['attempt_count'], 'Enabled fallback occupies only third attempt and reports actual model' );
check_resilience( array_column( $diag['attempts'], 'http_status' ) === [ 503, 503, 200 ] && ! str_contains( json_encode( $diag ), 'secret_resilience' ), 'Per-attempt diagnostic contains safe statuses without secrets' );
[ $client, $result, $diag, $requests ] = scenario( [ upstream( 503 ), upstream( 503 ), upstream( 503 ), upstream( 200 ) ], true );
check_resilience( is_wp_error( $result ) && 3 === count( $requests ), 'Failed fallback is never retried' );
[ $client, $result, $diag, $requests ] = scenario( [ upstream( 503 ), upstream( 503 ), upstream( 200 ) ] );
check_resilience( 'NO' === $diag['fallback_used'] && 'gemini-3.8-flash' === $result['model'], 'Disabled fallback never switches model' );
[ $client, $result, $diag, $requests ] = scenario( [ upstream( 429 ), upstream( 503 ), upstream( 200 ) ], true );
check_resilience( 'NO' === $diag['fallback_used'], 'Mixed rate-limit failures do not trigger 503-only fallback' );
[ $client, $result, $diag, $requests ] = scenario( [ upstream( 503 ), upstream( 200 ) ], true, [ 'deadline' => 1001.5 ] );
check_resilience( 1 === count( $requests ) && [] === $client->sleeps && $requests[0]['args']['timeout'] <= 1.5, 'Insufficient deadline stops retries without sleep' );
$delayed = upstream( 429 ); $delayed['headers']['retry-after'] = '60';
[ $client, $result, $diag, $requests ] = scenario( [ $delayed, upstream( 200 ) ] );
check_resilience( 1 === count( $requests ) && [] === $client->sleeps, 'Long Retry-After is not shortened or slept through' );
[ $client, $result, $diag, $requests ] = scenario( [ upstream( 503 ), upstream( 503 ), upstream( 200 ) ], true, [ 'diagnostic_test' => true ] );
check_resilience( str_contains( $requests[2]['url'], 'gemini-3.8-flash' ), 'Admin primary test cannot silently succeed on fallback' );
$settings = SettingsService::sanitize_settings( [ 'provider_gemini_fallback_enabled' => '1', 'provider_gemini_fallback_model' => 'gpt-4o-mini' ] );
check_resilience( true === $settings['provider_gemini_fallback_enabled'] && 'gemini-3.8-flash' === $settings['provider_gemini_fallback_model'], 'Settings restrict fallback to Gemini models' );
putenv( 'GEMINI_API_KEY' );
