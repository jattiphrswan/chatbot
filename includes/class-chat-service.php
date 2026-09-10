<?php
/**
 * Chat Application Orchestration Service.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

use SkyFish\GeminiChat\Admin\ProfileService;
use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Database\ConversationRepository;
use SkyFish\GeminiChat\Database\MessageRepository;
use SkyFish\GeminiChat\Database\SessionService;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ChatService
 *
 * Orchestrates chat interaction flow between REST controller, session state, persistence repositories, and AI providers.
 */
class ChatService {

	private SettingsService $settings_service;
	private SessionService $session_service;
	private ConversationRepository $conversation_repo;
	private MessageRepository $message_repo;
	private GeminiClient $gemini_client;
	private ProfileService $profile_service;
	private ?Knowledge\KnowledgeRetriever $knowledge_retriever;
	private ?Knowledge\KnowledgeContextBuilder $context_builder;
	private ?Handoff\HandoffService $handoff_service;
	private ?Providers\ProviderRegistry $provider_registry;
	private ?Providers\ProviderSelectionService $selection_service;

	/**
	 * ChatService constructor.
	 *
	 * @param SettingsService|null                    $settings_service    Optional settings service.
	 * @param SessionService|null                     $session_service     Optional session service.
	 * @param ConversationRepository|null             $conversation_repo   Optional conversation repository.
	 * @param MessageRepository|null                  $message_repo        Optional message repository.
	 * @param GeminiClient|null                       $gemini_client       Optional Gemini client.
	 * @param ProfileService|null                     $profile_service     Optional profile service.
	 * @param Knowledge\KnowledgeRetriever|null       $knowledge_retriever Optional knowledge retriever.
	 * @param Knowledge\KnowledgeContextBuilder|null  $context_builder     Optional knowledge context builder.
	 * @param Handoff\HandoffService|null             $handoff_service     Optional handoff service.
	 * @param Providers\ProviderRegistry|null         $provider_registry   Optional provider registry.
	 * @param Providers\ProviderSelectionService|null $selection_service   Optional provider selection service.
	 */
	public function __construct(
		?SettingsService $settings_service = null,
		?SessionService $session_service = null,
		?ConversationRepository $conversation_repo = null,
		?MessageRepository $message_repo = null,
		?GeminiClient $gemini_client = null,
		?ProfileService $profile_service = null,
		?Knowledge\KnowledgeRetriever $knowledge_retriever = null,
		?Knowledge\KnowledgeContextBuilder $context_builder = null,
		?Handoff\HandoffService $handoff_service = null,
		?Providers\ProviderRegistry $provider_registry = null,
		?Providers\ProviderSelectionService $selection_service = null
	) {
		$this->settings_service    = $settings_service ?? SettingsService::get_instance();
		$this->conversation_repo   = $conversation_repo ?? new ConversationRepository();
		$this->message_repo        = $message_repo ?? new MessageRepository();
		$this->session_service     = $session_service ?? new SessionService( $this->conversation_repo, $this->message_repo );
		$this->gemini_client       = $gemini_client ?? new GeminiClient( $this->settings_service );
		$this->profile_service     = $profile_service ?? new ProfileService( $this->settings_service );
		$this->knowledge_retriever = $knowledge_retriever ?? new Knowledge\KnowledgeRetriever();
		$this->context_builder     = $context_builder ?? new Knowledge\KnowledgeContextBuilder();
		$this->handoff_service     = $handoff_service ?? new Handoff\HandoffService( null, $this->conversation_repo );
		$this->provider_registry   = $provider_registry;
		$this->selection_service   = $selection_service;
	}

	/**
	 * Accessor to ProviderRegistry.
	 *
	 * @return Providers\ProviderRegistry
	 */
	public function get_provider_registry(): Providers\ProviderRegistry {
		if ( null === $this->provider_registry ) {
			$this->provider_registry = new Providers\ProviderRegistry();
			$this->provider_registry->register( new Providers\GeminiProvider( $this->gemini_client ) );
			$this->provider_registry->register( new Providers\OpenAIProvider() );
			$this->provider_registry->register( new Providers\ClaudeProvider() );
		}
		return $this->provider_registry;
	}

	/**
	 * Accessor to ProviderSelectionService (N21).
	 *
	 * @return Providers\ProviderSelectionService
	 */
	public function get_provider_selection_service(): Providers\ProviderSelectionService {
		if ( null === $this->selection_service ) {
			$this->selection_service = new Providers\ProviderSelectionService(
				$this->get_provider_registry(),
				$this->settings_service
			);
		}
		return $this->selection_service;
	}

	/**
	 * Handles a validated user chat message turn.
	 *
	 * @param string      $message            Sanitized user message.
	 * @param string      $session_id         Validated client session token.
	 * @param array       $context            Optional page context metadata.
	 * @param string|null $request_id         Diagnostic request UUID.
	 * @param string|null $requested_provider Optional visitor-requested provider slug (N21).
	 * @param string|null $requested_model    Optional visitor-requested model ID (N21).
	 * @return array|WP_Error Normalized response array or WP_Error.
	 */
	public function handle_chat(
		string $message,
		string $session_id,
		array $context = [],
		?string $request_id = null,
		?string $requested_provider = null,
		?string $requested_model = null
	) {
		// 1. Verify chatbot enabled setting.
		if ( ! (bool) $this->settings_service->get( 'enabled', true ) ) {
			return new WP_Error(
				'CHAT_DISABLED',
				__( 'The chat assistant is currently disabled.', 'gemini-chat-assistant' ),
				[ 'status' => 503 ]
			);
		}

		// 2. Generate or assign request ID.
		$req_id = ! empty( $request_id )
			? $request_id
			: ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'req_', true ) );

		// 3. Resolve user ID and session conversation state.
		$user_id    = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		$session    = $this->session_service->get_or_create_session( $session_id, $user_id );
		$conv_db_id = (int) $session['conversation_id'];
		$public_id  = (string) $session['public_id'];

		// 4. Check message persistence preference.
		$store_messages = (bool) $this->settings_service->get( 'store_messages', true );
		if ( $store_messages && $conv_db_id > 0 ) {
			$this->message_repo->create( $conv_db_id, 'user', $message );
		}

		// 5. Retrieve active conversation memory (previous_interaction_id).
		$previous_interaction_id = $this->session_service->get_interaction_id( $session_id, $conv_db_id );

		// 6. Check human handoff intent (Node N17.3).
		$handoff_meta = $this->check_handoff_intent( $message, $conv_db_id );

		// 7. Resolve active AI provider and model (N18/N19/N20/N21).
		try {
			$selection   = $this->get_provider_selection_service()->resolve_effective_selection(
				$requested_provider,
				$requested_model
			);
			$provider_id = $selection['provider_id'];
			$model_id    = $selection['model_id'];
		} catch ( Providers\ProviderException $e ) {
			return $this->map_provider_error( $e, $req_id );
		}

		// Mid-conversation provider switching (N21):
		// When switching to Gemini, if the previous turn was from another provider,
		// clear stale interaction ID so Gemini starts a clean interaction for this turn.
		if ( 'gemini' === $provider_id && $conv_db_id > 0 ) {
			$latest_messages = $this->message_repo->get_by_conversation_id( $conv_db_id, 2, 'DESC' );
			if ( ! empty( $latest_messages ) ) {
				foreach ( $latest_messages as $prev_msg ) {
					if ( 'assistant' === ( $prev_msg['role'] ?? '' ) && ! empty( $prev_msg['model'] ) ) {
						if ( ! \SkyFish\GeminiChat\Providers\ModelRegistry::has_model( 'gemini', (string) $prev_msg['model'] ) ) {
							$this->session_service->clear_interaction_id( $session_id, $conv_db_id );
							$previous_interaction_id = null;
						}
						break;
					}
				}
			}
		}

		// 8. Construct effective prompt grounded with retrieved knowledge (N16 RAG).
		$system_instruction = $this->get_grounded_instruction(
			$message,
			$this->profile_service->get_effective_system_instruction()
		);

		// 9. Dispatch chat interaction to active provider.
		$dispatch_result = $this->dispatch_provider_chat(
			$provider_id,
			$model_id,
			$message,
			$system_instruction,
			$conv_db_id,
			$previous_interaction_id,
			$session_id,
			$req_id
		);

		if ( is_wp_error( $dispatch_result ) ) {
			return $dispatch_result;
		}

		// 10. Persist assistant message if enabled.
		if ( $store_messages && $conv_db_id > 0 ) {
			$this->message_repo->create(
				$conv_db_id,
				'assistant',
				$dispatch_result['assistant_text'],
				$dispatch_result['model'],
				$dispatch_result['input_tokens'],
				$dispatch_result['output_tokens'],
				$dispatch_result['latency_ms']
			);

			$this->conversation_repo->update_last_active( $conv_db_id );
			$this->conversation_repo->increment_message_count( $conv_db_id, 2 );
		}

		// 11. Return normalized public response shape.
		$response_payload = [
			'message'         => $dispatch_result['assistant_text'],
			'conversation_id' => $public_id,
			'request_id'      => $req_id,
			'meta'            => [
				'model'    => $dispatch_result['model'],
				'provider' => $provider_id,
			],
		];

		if ( ! empty( $handoff_meta ) ) {
			$response_payload['meta']['handoff'] = $handoff_meta;
		}

		return $response_payload;
	}

	/**
	 * Dispatches chat turn to the designated AI provider.
	 *
	 * @param string      $provider_id             Provider identifier ('gemini', 'openai', 'claude').
	 * @param string      $model_id                Effective model identifier.
	 * @param string      $message                 User message string.
	 * @param string      $system_instruction      Grounded system prompt.
	 * @param int         $conv_db_id              Conversation database ID.
	 * @param string|null $previous_interaction_id Gemini previous interaction UUID.
	 * @param string      $session_id              Client session identifier.
	 * @param string      $req_id                  Request UUID for diagnostics.
	 * @return array{assistant_text: string, model: string, input_tokens: int, output_tokens: int, latency_ms: int}|WP_Error
	 */
	private function dispatch_provider_chat(
		string $provider_id,
		string $model_id,
		string $message,
		string $system_instruction,
		int $conv_db_id,
		?string $previous_interaction_id,
		string $session_id,
		string $req_id
	) {
		$start_time = microtime( true );

		if ( 'gemini' === $provider_id ) {
			return $this->dispatch_gemini_turn( $model_id, $message, $system_instruction, $previous_interaction_id, $session_id, $conv_db_id, $start_time, $req_id );
		}

		return $this->dispatch_multi_turn_provider( $provider_id, $model_id, $conv_db_id, $message, $system_instruction, $start_time, $req_id );
	}

	/**
	 * Dispatches chat interaction via a multi-turn history-based AI provider (e.g. OpenAI, Claude).
	 *
	 * @param string $provider_id        Provider identifier slug ('openai', 'claude').
	 * @param string $model_id           Selected model identifier.
	 * @param int    $conv_db_id         Conversation database ID.
	 * @param string $message             User message string.
	 * @param string $system_instruction Grounded system instruction prompt.
	 * @param float  $start_time         Dispatch start timestamp for latency tracking.
	 * @param string $req_id             Request identifier.
	 * @return array{assistant_text: string, model: string, input_tokens: int, output_tokens: int, latency_ms: int}|WP_Error
	 */
	private function dispatch_multi_turn_provider(
		string $provider_id,
		string $model_id,
		int $conv_db_id,
		string $message,
		string $system_instruction,
		float $start_time,
		string $req_id
	) {
		$context_messages = $this->build_context_messages( $conv_db_id, $message );

		try {
			$provider = $this->get_provider_registry()->get( $provider_id );
			if ( null === $provider ) {
				return new WP_Error(
					'PROVIDER_NOT_FOUND',
					sprintf(
						/* translators: %s: Provider ID */
						__( 'AI provider "%s" is not registered.', 'gemini-chat-assistant' ),
						$provider_id
					),
					[ 'status' => 500 ]
				);
			}

			$response = $provider->chat(
				$context_messages,
				[
					'model'              => $model_id,
					'system_instruction' => $system_instruction,
				]
			);

			return [
				'assistant_text' => $response->get_content(),
				'input_tokens'   => $response->get_input_tokens(),
				'output_tokens'  => $response->get_output_tokens(),
				'model'          => $response->get_model() ?: $model_id,
				'latency_ms'     => (int) round( ( microtime( true ) - $start_time ) * 1000 ),
			];
		} catch ( Providers\ProviderException $e ) {
			return $this->map_provider_error( $e, $req_id );
		}
	}

	/**
	 * Dispatches chat interaction via Google Gemini Interactions API with stale interaction retry.
	 *
	 * @param string      $model_id                Selected Gemini model identifier.
	 * @param string      $message                 User message string.
	 * @param string      $system_instruction      Grounded system prompt.
	 * @param string|null $previous_interaction_id Previous interaction ID.
	 * @param string      $session_id              Client session identifier.
	 * @param int         $conv_db_id              Conversation database ID.
	 * @param float       $start_time              Turn start timestamp.
	 * @param string      $req_id                  Request UUID for diagnostics.
	 * @return array{assistant_text: string, model: string, input_tokens: int, output_tokens: int, latency_ms: int}|WP_Error
	 */
	private function dispatch_gemini_turn(
		string $model_id,
		string $message,
		string $system_instruction,
		?string $previous_interaction_id,
		string $session_id,
		int $conv_db_id,
		float $start_time,
		string $req_id
	) {
		$history = $this->build_context_messages( $conv_db_id, $message );
		array_pop( $history ); // The client appends the current message once.
		$ai_response = $this->gemini_client->create_interaction(
			$message,
			$previous_interaction_id,
			[
				'model'              => $model_id,
				'system_instruction' => $system_instruction,
				'history'            => $history,
				'request_id'         => $req_id,
			]
		);
		$latency_ms  = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $ai_response ) ) {
			if ( ! empty( $previous_interaction_id ) && $this->is_stale_interaction_error( $ai_response ) ) {
				$this->session_service->clear_interaction_id( $session_id, $conv_db_id );
				$start_time  = microtime( true );
				$ai_response = $this->gemini_client->create_interaction(
					$message,
					null,
					[
						'model'              => $model_id,
						'system_instruction' => $system_instruction,
					]
				);
				$latency_ms  = (int) round( ( microtime( true ) - $start_time ) * 1000 );
			}

			if ( is_wp_error( $ai_response ) ) {
				return $this->map_gemini_error( $ai_response, $req_id );
			}
		}

		$usage         = $ai_response['usage'] ?? [];
		$input_tokens  = absint( $usage['input_tokens'] ?? 0 );
		$output_tokens = absint( $usage['output_tokens'] ?? 0 );

		$new_interaction_id = $ai_response['interaction_id'] ?? '';
		if ( ! empty( $new_interaction_id ) ) {
			$this->session_service->set_interaction_id( $session_id, $conv_db_id, (string) $new_interaction_id );
		}

		return [
			'assistant_text' => $ai_response['text'] ?? '',
			'input_tokens'   => $input_tokens,
			'output_tokens'  => $output_tokens,
			'model'          => $model_id,
			'latency_ms'     => $latency_ms,
		];
	}

	/**
	 * Detects and records human handoff requests if intent is present.
	 *
	 * @param string $message    User message.
	 * @param int    $conv_db_id Conversation database ID.
	 * @return array{status: string, public_id: string}|null
	 */
	private function check_handoff_intent( string $message, int $conv_db_id ): ?array {
		if ( null === $this->handoff_service || $conv_db_id <= 0 ) {
			return null;
		}

		$detected_reason = $this->handoff_service->detect_handoff_intent( $message );
		if ( null === $detected_reason ) {
			return null;
		}

		$handoff_result = $this->handoff_service->create_handoff( $conv_db_id, $detected_reason );
		if ( is_array( $handoff_result ) && ! empty( $handoff_result['public_id'] ) ) {
			return [
				'status'    => 'requested',
				'public_id' => (string) $handoff_result['public_id'],
			];
		}

		return null;
	}

	/**
	 * Augments the base system instruction with retrieved RAG website knowledge if enabled.
	 *
	 * @param string $message          User message query.
	 * @param string $base_instruction Base system instruction.
	 * @return string Augmented instruction.
	 */
	private function get_grounded_instruction( string $message, string $base_instruction ): string {
		if ( ! (bool) $this->settings_service->get( 'knowledge_enabled', false ) || null === $this->knowledge_retriever || null === $this->context_builder ) {
			return $base_instruction;
		}

		$chunks = $this->knowledge_retriever->retrieve( $message );
		if ( empty( $chunks ) ) {
			return $base_instruction;
		}

		$rag_context = $this->context_builder->build( $chunks );
		return ! empty( $rag_context ) ? $base_instruction . "\n\n" . $rag_context : $base_instruction;
	}

	/**
	 * Compiles conversation history into normalized multi-turn context messages.
	 *
	 * @param int    $conv_db_id Conversation primary ID.
	 * @param string $message    Current user message.
	 * @return array<int, array{role: string, content: string}>
	 */
	private function build_context_messages( int $conv_db_id, string $message ): array {
		$context_messages = [];
		if ( $conv_db_id > 0 ) {
			$context_messages = $this->message_repo->get_context_messages( $conv_db_id, 20 );
		}

		$has_current = false;
		if ( ! empty( $context_messages ) ) {
			$last_msg = end( $context_messages );
			if ( 'user' === ( $last_msg['role'] ?? '' ) && ( $last_msg['content'] ?? '' ) === $message ) {
				$has_current = true;
			}
		}

		if ( ! $has_current ) {
			$context_messages[] = [
				'role'    => 'user',
				'content' => $message,
			];
		}

		return $context_messages;
	}

	/**
	 * Detects whether an API error indicates a stale, expired, or invalid interaction ID.
	 *
	 * @param WP_Error $error API Error.
	 * @return bool
	 */
	private function is_stale_interaction_error( WP_Error $error ): bool {
		$msg = strtolower( $error->get_error_message() );
		return false !== strpos( $msg, 'previous_interaction_id' )
			|| false !== strpos( $msg, 'interaction not found' )
			|| false !== strpos( $msg, 'invalid interaction' )
			|| false !== strpos( $msg, 'interaction expired' );
	}

	/**
	 * Resets session state for a given session identifier.
	 *
	 * @param string $session_id Validated client session token.
	 * @return bool
	 */
	public function reset_session( string $session_id ): bool {
		return $this->session_service->reset_session( $session_id );
	}

	/**
	 * Maps internal GeminiClient error codes to standardized public error codes.
	 *
	 * @param WP_Error $error      Internal error.
	 * @param string   $request_id Diagnostic request ID.
	 * @return WP_Error
	 */
	private function map_gemini_error( WP_Error $error, string $request_id ): WP_Error {
		$code     = $error->get_error_code();
		$err_data = $error->get_error_data();
		$status   = is_array( $err_data ) && isset( $err_data['status'] ) ? absint( $err_data['status'] ) : 500;

		switch ( $code ) {
			case 'GCA_GEMINI_NOT_CONFIGURED':
				$public_code    = 'GEMINI_NOT_CONFIGURED';
				$public_message = __( 'Chat is not configured yet.', 'gemini-chat-assistant' );
				$status         = 500;
				break;

			case 'GCA_GEMINI_AUTH_ERROR':
				$public_code    = 'GEMINI_AUTH_FAILED';
				$public_message = __( 'Assistant configuration error.', 'gemini-chat-assistant' );
				$status         = 500;
				break;

			case 'GCA_GEMINI_MODEL_UNAVAILABLE':
				$public_code    = 'GEMINI_MODEL_UNAVAILABLE';
				$public_message = __( 'Assistant model is currently unavailable.', 'gemini-chat-assistant' );
				$status         = 503;
				break;

			case 'GCA_GEMINI_QUOTA_ERROR':
			case 'GCA_GEMINI_RATE_LIMITED':
				$public_code    = 'RATE_LIMITED';
				$public_message = __( 'Too many messages. Please try again shortly.', 'gemini-chat-assistant' );
				$status         = 429;
				break;

			case 'GCA_GEMINI_TIMEOUT':
				$public_code    = 'GEMINI_TIMEOUT';
				$public_message = __( 'The assistant took too long to respond. Please try again.', 'gemini-chat-assistant' );
				$status         = 504;
				break;

			case 'GCA_GEMINI_UNAVAILABLE':
				$public_code    = 'GEMINI_UNAVAILABLE';
				$public_message = __( 'The assistant is temporarily unavailable. Please try again shortly.', 'gemini-chat-assistant' );
				$status         = 503;
				break;

			case 'GCA_GEMINI_EMPTY_RESPONSE':
			case 'GCA_GEMINI_INVALID_RESPONSE':
				$public_code    = 'INTERNAL_ERROR';
				$public_message = __( 'Received an invalid or empty response from the AI service.', 'gemini-chat-assistant' );
				$status         = 502;
				break;

			default:
				$public_code    = 'INTERNAL_ERROR';
				$public_message = __( 'An internal error occurred while processing your request.', 'gemini-chat-assistant' );
				$status         = 500;
				break;
		}

		return new WP_Error(
			$public_code,
			$public_message,
			[
				'status'     => $status,
				'request_id' => $request_id,
			]
		);
	}

	/**
	 * Maps a ProviderException to a user-facing WP_Error.
	 *
	 * @param Providers\ProviderException $e          Provider exception.
	 * @param string                      $request_id Request identifier.
	 * @return WP_Error
	 */
	private function map_provider_error( Providers\ProviderException $e, string $request_id ): WP_Error {
		$error_type = $e->get_error_type();
		$status     = $e->get_http_status();
		$message    = $e->get_safe_message();

		switch ( $error_type ) {
			case Providers\ProviderException::TYPE_NOT_CONFIGURED:
			case Providers\ProviderException::TYPE_CONFIGURATION_ERROR:
				$public_code    = 'GEMINI_NOT_CONFIGURED';
				$public_message = __( 'Chat is not configured yet.', 'gemini-chat-assistant' );
				$status         = 500;
				break;

			case Providers\ProviderException::TYPE_AUTH_FAILED:
			case Providers\ProviderException::TYPE_AUTHENTICATION_ERROR:
				$public_code    = 'GEMINI_AUTH_FAILED';
				$public_message = __( 'Assistant configuration error.', 'gemini-chat-assistant' );
				$status         = 500;
				break;

			case Providers\ProviderException::TYPE_MODEL_UNAVAILABLE:
				$public_code    = 'GEMINI_MODEL_UNAVAILABLE';
				$public_message = __( 'Assistant model is currently unavailable.', 'gemini-chat-assistant' );
				$status         = 503;
				break;

			case Providers\ProviderException::TYPE_RATE_LIMITED:
			case Providers\ProviderException::TYPE_RATE_LIMIT:
				$public_code    = 'RATE_LIMITED';
				$public_message = __( 'Too many messages. Please try again shortly.', 'gemini-chat-assistant' );
				$status         = 429;
				break;

			case Providers\ProviderException::TYPE_TIMEOUT:
				$public_code    = 'GEMINI_TIMEOUT';
				$public_message = __( 'The assistant took too long to respond. Please try again.', 'gemini-chat-assistant' );
				$status         = 504;
				break;

			case Providers\ProviderException::TYPE_PROVIDER_UNAVAILABLE:
				$public_code    = 'GEMINI_UNAVAILABLE';
				$public_message = __( 'The assistant is temporarily unavailable. Please try again shortly.', 'gemini-chat-assistant' );
				$status         = 503;
				break;

			case Providers\ProviderException::TYPE_MALFORMED_RESPONSE:
			case Providers\ProviderException::TYPE_INVALID_RESPONSE:
				$public_code    = 'INTERNAL_ERROR';
				$public_message = __( 'Received an invalid or empty response from the AI service.', 'gemini-chat-assistant' );
				$status         = 502;
				break;

			default:
				$public_code    = 'INTERNAL_ERROR';
				$public_message = ! empty( $message ) ? $message : __( 'An internal error occurred while processing your request.', 'gemini-chat-assistant' );
				break;
		}

		return new WP_Error(
			$public_code,
			$public_message,
			[
				'status'     => $status,
				'provider'   => $e->get_provider_id(),
				'error_type' => $error_type,
				'request_id' => $request_id,
			]
		);
	}
}
