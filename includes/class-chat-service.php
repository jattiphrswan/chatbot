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
 * Orchestrates chat interaction flow between REST controller, session state, persistence repositories, and Gemini API client.
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
		?Providers\ProviderRegistry $provider_registry = null
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
	 * Handles a validated user chat message turn.
	 *
	 * @param string      $message    Sanitized user message.
	 * @param string      $session_id Validated client session token.
	 * @param array       $context    Optional page context metadata.
	 * @param string|null $request_id Diagnostic request UUID.
	 * @return array|WP_Error Normalized response array or WP_Error.
	 */
	public function handle_chat(
		string $message,
		string $session_id,
		array $context = [],
		?string $request_id = null
	) {
		// 1. Verify chatbot enabled setting.
		$is_enabled = (bool) $this->settings_service->get( 'enabled', true );
		if ( ! $is_enabled ) {
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
		$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		$session = $this->session_service->get_or_create_session( $session_id, $user_id );

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
		$handoff_meta = null;
		if ( null !== $this->handoff_service && $conv_db_id > 0 ) {
			$detected_reason = $this->handoff_service->detect_handoff_intent( $message );
			if ( null !== $detected_reason ) {
				$handoff_result = $this->handoff_service->create_handoff( $conv_db_id, $detected_reason );
				if ( is_array( $handoff_result ) && ! empty( $handoff_result['public_id'] ) ) {
					$handoff_meta = [
						'status'    => 'requested',
						'public_id' => (string) $handoff_result['public_id'],
					];
				}
			}
		}

		// 7. Determine active AI provider (N18/N19).
		$provider_id = SettingsService::get_default_provider();

		// Check if provider is enabled.
		if ( ! SettingsService::is_provider_enabled( $provider_id ) ) {
			return new WP_Error(
				'PROVIDER_DISABLED',
				sprintf( __( 'The configured AI provider (%s) is disabled.', 'gemini-chat-assistant' ), esc_html( ucfirst( $provider_id ) ) ),
				[ 'status' => 503 ]
			);
		}

		$system_instruction = $this->profile_service->get_effective_system_instruction();

		// Ground system instruction with retrieved website knowledge context (N16 RAG).
		if ( (bool) $this->settings_service->get( 'knowledge_enabled', false ) && null !== $this->knowledge_retriever && null !== $this->context_builder ) {
			$chunks = $this->knowledge_retriever->retrieve( $message );
			if ( ! empty( $chunks ) ) {
				$rag_context = $this->context_builder->build( $chunks );
				if ( ! empty( $rag_context ) ) {
					$system_instruction .= "\n\n" . $rag_context;
				}
			}
		}

		$start_time = microtime( true );

		if ( 'openai' === $provider_id ) {
			$model = SettingsService::get_provider_model( 'openai' );

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

			try {
				$provider = $this->get_provider_registry()->get( 'openai' );
				if ( null === $provider ) {
					return new WP_Error( 'PROVIDER_NOT_FOUND', __( 'OpenAI provider is not registered.', 'gemini-chat-assistant' ), [ 'status' => 500 ] );
				}

				$provider_response = $provider->chat(
					$context_messages,
					[
						'model'              => $model,
						'system_instruction' => $system_instruction,
					]
				);

				$assistant_text = $provider_response->get_content();
				$input_tokens   = $provider_response->get_input_tokens();
				$output_tokens  = $provider_response->get_output_tokens();
				$model          = $provider_response->get_model();
				$latency_ms     = (int) round( ( microtime( true ) - $start_time ) * 1000 );
			} catch ( Providers\ProviderException $e ) {
				return $this->map_provider_error( $e, $req_id );
			}
		} elseif ( 'claude' === $provider_id ) {
			try {
				$provider = $this->get_provider_registry()->get( 'claude' );
				if ( null === $provider ) {
					return new WP_Error( 'PROVIDER_NOT_FOUND', __( 'Claude provider is not registered.', 'gemini-chat-assistant' ), [ 'status' => 500 ] );
				}
				$provider->chat( [ [ 'role' => 'user', 'content' => $message ] ] );
				return new WP_Error( 'PROVIDER_ERROR', __( 'Claude chat is not available.', 'gemini-chat-assistant' ), [ 'status' => 500 ] );
			} catch ( Providers\ProviderException $e ) {
				return $this->map_provider_error( $e, $req_id );
			}
		} else {
			// Google Gemini (Default / Native).
			$model       = SettingsService::get_provider_model( 'gemini' );
			$ai_response = $this->gemini_client->create_interaction(
				$message,
				$previous_interaction_id,
				[
					'model'              => $model,
					'system_instruction' => $system_instruction,
				]
			);
			$latency_ms  = (int) round( ( microtime( true ) - $start_time ) * 1000 );

			// Handle AI transport or API error with stale interaction recovery.
			if ( is_wp_error( $ai_response ) ) {
				if ( ! empty( $previous_interaction_id ) && $this->is_stale_interaction_error( $ai_response ) ) {
					$this->session_service->clear_interaction_id( $session_id, $conv_db_id );
					$start_time  = microtime( true );
					$ai_response = $this->gemini_client->create_interaction(
						$message,
						null,
						[
							'model'              => $model,
							'system_instruction' => $system_instruction,
						]
					);
					$latency_ms  = (int) round( ( microtime( true ) - $start_time ) * 1000 );
				}

				if ( is_wp_error( $ai_response ) ) {
					return $this->map_gemini_error( $ai_response, $req_id );
				}
			}

			$assistant_text = $ai_response['text'] ?? '';
			$usage          = $ai_response['usage'] ?? [];
			$input_tokens   = absint( $usage['input_tokens'] ?? 0 );
			$output_tokens  = absint( $usage['output_tokens'] ?? 0 );

			// Synchronize new interaction ID into session transient and database.
			$new_interaction_id = $ai_response['interaction_id'] ?? '';
			if ( ! empty( $new_interaction_id ) ) {
				$this->session_service->set_interaction_id( $session_id, $conv_db_id, (string) $new_interaction_id );
			}
		}

		// 8. Persist assistant message if enabled.
		if ( $store_messages && $conv_db_id > 0 ) {
			$this->message_repo->create(
				$conv_db_id,
				'assistant',
				$assistant_text,
				$model,
				$input_tokens,
				$output_tokens,
				$latency_ms
			);

			$this->conversation_repo->update_last_active( $conv_db_id );
			$this->conversation_repo->increment_message_count( $conv_db_id, 2 );
		}

		// 9. Return normalized public response shape (zero database IDs, hashes, or interaction IDs).
		$response_payload = [
			'message'         => $assistant_text,
			'conversation_id' => $public_id,
			'request_id'      => $req_id,
			'meta'            => [
				'model'    => $model,
				'provider' => $provider_id,
			],
		];

		if ( ! empty( $handoff_meta ) ) {
			$response_payload['meta']['handoff'] = $handoff_meta;
		}

		return $response_payload;
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
			case 'GCA_GEMINI_AUTH_ERROR':
				$public_code    = 'AI_AUTH_ERROR';
				$public_message = __( 'AI service authentication failed or is unconfigured.', 'gemini-chat-assistant' );
				$status         = 500;
				break;

			case 'GCA_GEMINI_QUOTA_ERROR':
				$public_code    = 'AI_QUOTA_ERROR';
				$public_message = __( 'AI service quota exceeded. Please try again later.', 'gemini-chat-assistant' );
				$status         = 429;
				break;

			case 'GCA_GEMINI_RATE_LIMITED':
				$public_code    = 'AI_RATE_LIMITED';
				$public_message = __( 'Too many requests. Please wait a moment before sending another message.', 'gemini-chat-assistant' );
				$status         = 429;
				break;

			case 'GCA_GEMINI_TIMEOUT':
				$public_code    = 'AI_TIMEOUT';
				$public_message = __( 'The AI service timed out responding to your request.', 'gemini-chat-assistant' );
				$status         = 504;
				break;

			case 'GCA_GEMINI_UNAVAILABLE':
				$public_code    = 'AI_UNAVAILABLE';
				$public_message = __( 'The AI service is temporarily unavailable. Please try again shortly.', 'gemini-chat-assistant' );
				$status         = 503;
				break;

			case 'GCA_GEMINI_EMPTY_RESPONSE':
			case 'GCA_GEMINI_INVALID_RESPONSE':
				$public_code    = 'AI_INVALID_RESPONSE';
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
			case Providers\ProviderException::TYPE_AUTH_FAILED:
			case Providers\ProviderException::TYPE_AUTHENTICATION_ERROR:
			case Providers\ProviderException::TYPE_NOT_CONFIGURED:
			case Providers\ProviderException::TYPE_CONFIGURATION_ERROR:
				$public_code    = 'AI_AUTH_ERROR';
				$public_message = __( 'AI service authentication failed or is unconfigured.', 'gemini-chat-assistant' );
				$status         = 500;
				break;

			case Providers\ProviderException::TYPE_RATE_LIMITED:
			case Providers\ProviderException::TYPE_RATE_LIMIT:
				$public_code    = 'AI_RATE_LIMITED';
				$public_message = __( 'Too many requests. Please wait a moment before sending another message.', 'gemini-chat-assistant' );
				$status         = 429;
				break;

			case Providers\ProviderException::TYPE_TIMEOUT:
				$public_code    = 'AI_TIMEOUT';
				$public_message = __( 'The AI service timed out responding to your request.', 'gemini-chat-assistant' );
				$status         = 504;
				break;

			case Providers\ProviderException::TYPE_MODEL_UNAVAILABLE:
			case Providers\ProviderException::TYPE_PROVIDER_UNAVAILABLE:
				$public_code    = 'AI_UNAVAILABLE';
				$public_message = __( 'The AI service is temporarily unavailable. Please try again shortly.', 'gemini-chat-assistant' );
				$status         = 503;
				break;

			case Providers\ProviderException::TYPE_MALFORMED_RESPONSE:
			case Providers\ProviderException::TYPE_INVALID_RESPONSE:
				$public_code    = 'AI_INVALID_RESPONSE';
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
