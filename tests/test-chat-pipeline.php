<?php
require_once __DIR__ . '/test-chat-service.php';

use SkyFish\GeminiChat\ChatService;
use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Knowledge\KnowledgeRetriever;
use SkyFish\GeminiChat\Database\KnowledgeRepository;

function pipeline_assert( bool $ok, string $label ): void {
    if ( ! $ok ) { throw new RuntimeException( $label ); }
    echo "[PASS] $label\n";
}

class PipelineMessages extends MockMessageRepo {
    public bool $fail_assistant = false;
    public function create( int $conversation_id, string $role, string $content, ?string $model = null, int $input_tokens = 0, int $output_tokens = 0, int $latency_ms = 0 ): int {
        if ( $this->fail_assistant && 'assistant' === $role ) { return 0; }
        return parent::create( $conversation_id, $role, $content, $model, $input_tokens, $output_tokens, $latency_ms );
    }
    public function get_context_messages( int $conversation_id, int $limit = 20 ): array {
        return array_fill( 0, 20, [ 'role' => 'user', 'content' => str_repeat( 'x', 3000 ) ] );
    }
}
class PipelineKnowledge extends KnowledgeRetriever {
    public int $calls = 0;
    public function retrieve( string $query ): array { ++$this->calls; return []; }
}
class PipelineHandoff extends \SkyFish\GeminiChat\Handoff\HandoffService {
    public int $calls = 0;
    public function detect_handoff_intent( string $message ): ?string { ++$this->calls; return null; }
}
class PipelineGemini extends MockGeminiClient {
    public array $options = [];
    public function create_interaction( string $input, ?string $previous_interaction_id = null, array $options = [] ) {
        $this->options = $options;
        update_option( 'gca_gemini_last_chat', [ 'request_id' => $options['request_id'], 'generation_result' => 'Passed', 'http_status' => 200, 'generation_elapsed_seconds' => 0.01, 'final_request_characters' => 12345, 'thinking_level' => 'low' ] );
        return [ 'text' => 'OK', 'usage' => [] ];
    }
}

$GLOBALS['mock_options']['gca_settings'] = [ 'enabled' => true, 'store_messages' => true, 'knowledge_enabled' => true ];
putenv( 'GEMINI_API_KEY=pipeline_test_secret' );
$messages = new PipelineMessages();
$knowledge = new PipelineKnowledge();
$handoff = new PipelineHandoff();
$gemini = new PipelineGemini();
$service = new ChatService( null, null, new MockConversationRepo(), $messages, $gemini, null, $knowledge, null, $handoff );
$result = $service->handle_chat( 'hi', 'gca_sess_0123456789abcdef0123456789abcdef', [], 'pipeline-greeting' );
$diag = get_option( 'gca_chat_pipeline' );
pipeline_assert( ! is_wp_error( $result ) && 200 === $diag['rest_status'], 'Greeting completes and records application status' );
pipeline_assert( 0 === $knowledge->calls && 0 === $handoff->calls, 'Greeting skips retrieval and handoff' );
pipeline_assert( 'NO' === $diag['provider_called'], 'Greeting fast path sets provider_called to NO' );

$result_chat = $service->handle_chat( 'Can you describe your platform?', 'gca_sess_0123456789abcdef0123456789abcdef', [], 'pipeline-success' );
$diag = get_option( 'gca_chat_pipeline' );
pipeline_assert( 45 === $gemini->options['timeout'] && 'pipeline-success' === $gemini->options['request_id'], 'Normal dispatch uses 45 seconds and correlated request ID' );
pipeline_assert( count( $gemini->options['history'] ) <= 20 && array_sum( array_map( fn( $m ) => strlen( $m['content'] ), $gemini->options['history'] ) ) <= 12000, 'Outgoing memory is bounded by messages and characters' );
pipeline_assert( 10.0 === $diag['gemini_ms'] && 12345 === $diag['final_request_characters'], 'Pipeline correlates generation time and prompt size' );
pipeline_assert( 'YES' === $diag['provider_called'], 'Normal chat sets provider_called to YES' );
pipeline_assert( ! str_contains( json_encode( $diag ), 'pipeline_test_secret' ) && ! str_contains( json_encode( $diag ), str_repeat( 'x', 50 ) ), 'Pipeline stores no prompt or credential' );
$messages->fail_assistant = true;
try {
    $service->handle_chat( 'What services do you offer?', 'gca_sess_0123456789abcdef0123456789abcdef', [], 'pipeline-db-failure' );
    throw new LogicException( 'Expected persistence failure' );
} catch ( RuntimeException $expected ) {}
$diag = get_option( 'gca_chat_pipeline' );
pipeline_assert( 'DATABASE' === $diag['failure_stage'] && 'MESSAGE_SAVE' === $diag['failure_step'] && 500 === $diag['rest_status'], 'Post-Gemini persistence failure is separate from upstream timeout' );
pipeline_assert( 200 === get_option( 'gca_gemini_last_chat' )['http_status'] && 'pipeline-db-failure' === $diag['request_id'], 'Successful Google result survives correlated application failure' );
pipeline_assert( 2 === $knowledge->calls, 'Service questions invoke relevant retrieval path' );

class OversizedKnowledge extends KnowledgeRepository {
    public function search_candidate_chunks( array $terms, int $limit = 30 ): array {
        return [ [ 'content' => str_repeat( 'services ', 2000 ), 'title' => 'Services', 'source_type' => 'page' ] ];
    }
}
$GLOBALS['mock_options']['gca_settings']['knowledge_max_context_chars'] = 500;
$chunks = ( new KnowledgeRetriever( new OversizedKnowledge() ) )->retrieve( 'services' );
pipeline_assert( 1 === count( $chunks ) && mb_strlen( $chunks[0]['content'], 'UTF-8' ) <= 500, 'Oversized first RAG chunk obeys configured budget' );

class RecentMessages extends \SkyFish\GeminiChat\Database\MessageRepository {
    public function get_by_conversation_id( int $conversation_id, int $limit = 50, string $order = 'ASC' ): array {
        pipeline_assert( 'DESC' === $order && 20 === $limit, 'Context query asks for newest bounded records' );
        return [ [ 'role' => 'assistant', 'content' => 'newest' ], [ 'role' => 'user', 'content' => 'previous' ] ];
    }
}
$recent = ( new RecentMessages() )->get_context_messages( 1 );
pipeline_assert( 'previous' === $recent[0]['content'] && 'newest' === $recent[1]['content'], 'Recent records sent in chronological order' );
putenv( 'GEMINI_API_KEY' );
