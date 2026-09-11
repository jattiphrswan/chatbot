<?php
/**
 * REST API Controller for Gemini Chat Assistant.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

use SkyFish\GeminiChat\Admin\SettingsService;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RestController
 *
 * Exposes and handles WordPress REST API endpoints under namespace gca/v1.
 */
class RestController extends WP_REST_Controller {

	/**
	 * REST namespace for the plugin.
	 */
	public const REST_NAMESPACE = 'gca/v1';

	protected $namespace = self::REST_NAMESPACE;
	private SettingsService $settings_service;
	private ChatService $chat_service;
	private GeminiClient $gemini_client;
	private RateLimiter $rate_limiter;
	private LeadService $lead_service;

	/**
	 * RestController constructor.
	 *
	 * @param SettingsService|null $settings_service Optional settings service.
	 * @param ChatService|null     $chat_service     Optional chat service.
	 * @param GeminiClient|null    $gemini_client    Optional Gemini client.
	 * @param RateLimiter|null     $rate_limiter     Optional rate limiter service.
	 * @param LeadService|null     $lead_service     Optional lead service.
	 */
	public function __construct(
		?SettingsService $settings_service = null,
		?ChatService $chat_service = null,
		?GeminiClient $gemini_client = null,
		?RateLimiter $rate_limiter = null,
		?LeadService $lead_service = null
	) {
		$this->namespace        = self::REST_NAMESPACE;
		$this->settings_service = $settings_service ?? SettingsService::get_instance();
		$this->gemini_client    = $gemini_client ?? new GeminiClient( $this->settings_service );
		$this->chat_service     = $chat_service ?? new ChatService( $this->settings_service, null, null, null, $this->gemini_client );
		$this->rate_limiter     = $rate_limiter ?? new RateLimiter( $this->settings_service );
		$this->lead_service     = $lead_service ?? new LeadService( $this->settings_service );
	}

	/**
	 * Initializes REST routes registration on rest_api_init.
	 */
	public function init(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers REST routes for the chatbot API.
	 */
	public function register_routes(): void {
		// 1. POST /wp-json/gca/v1/chat
		register_rest_route(
			$this->namespace,
			'/chat',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_chat' ],
					'permission_callback' => [ $this, 'check_chat_permissions' ],
					'args'                => [
						'message'    => [
							'description' => __( 'User chat prompt message.', 'gemini-chat-assistant' ),
							'type'        => 'string',
							'required'    => true,
						],
						'session_id' => [
							'description' => __( 'Opaque client session identifier.', 'gemini-chat-assistant' ),
							'type'        => 'string',
							'required'    => true,
						],
						'context'    => [
							'description' => __( 'Optional page context metadata.', 'gemini-chat-assistant' ),
							'type'        => 'object',
							'required'    => false,
						],
						'provider'   => [
							'description' => __( 'Optional requested AI provider slug.', 'gemini-chat-assistant' ),
							'type'        => 'string',
							'required'    => false,
						],
						'model'      => [
							'description' => __( 'Optional requested AI model identifier.', 'gemini-chat-assistant' ),
							'type'        => 'string',
							'required'    => false,
						],
					],
				],
			]
		);

		// 2. POST /wp-json/gca/v1/reset
		register_rest_route(
			$this->namespace,
			'/reset',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_reset' ],
					'permission_callback' => [ $this, 'check_chat_permissions' ],
					'args'                => [
						'session_id' => [
							'description' => __( 'Opaque client session identifier to reset.', 'gemini-chat-assistant' ),
							'type'        => 'string',
							'required'    => true,
						],
					],
				],
			]
		);

		// 3. POST /wp-json/gca/v1/prechat
		register_rest_route(
			$this->namespace,
			'/prechat',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_prechat' ],
					'permission_callback' => [ $this, 'check_chat_permissions' ],
					'args'                => [
						'session_id'  => [
							'description' => __( 'Opaque client session identifier.', 'gemini-chat-assistant' ),
							'type'        => 'string',
							'required'    => true,
						],
						'name'        => [
							'description' => __( 'Visitor name.', 'gemini-chat-assistant' ),
							'type'        => 'string',
							'required'    => false,
						],
						'email'       => [
							'description' => __( 'Visitor email address.', 'gemini-chat-assistant' ),
							'type'        => 'string',
							'required'    => false,
						],
						'phone'       => [
							'description' => __( 'Visitor phone number.', 'gemini-chat-assistant' ),
							'type'        => 'string',
							'required'    => false,
						],
						'requirement' => [
							'description' => __( 'Visitor requirement or message.', 'gemini-chat-assistant' ),
							'type'        => 'string',
							'required'    => false,
						],
						'website_url' => [
							'description' => __( 'Honeypot field (must remain empty).', 'gemini-chat-assistant' ),
							'type'        => 'string',
							'required'    => false,
						],
					],
				],
			]
		);

		// 4. GET /wp-json/gca/v1/health (Admin only)
		register_rest_route(
			$this->namespace,
			'/health',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'handle_health' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
				],
			]
		);

		// 5. GET /wp-json/gca/v1/providers (Public metadata, N21)
		register_rest_route(
			$this->namespace,
			'/providers',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'handle_providers' ],
					'permission_callback' => [ $this, 'check_chat_permissions' ],
				],
			]
		);
	}

	/**
	 * Permission callback for public /chat and /reset endpoints.
	 *
	 * Checks whether guest access is permitted when user is not logged in.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return bool|WP_Error
	 */
	public function check_chat_permissions( WP_REST_Request $request ) {
		$guest_access = (bool) $this->settings_service->get( 'guest_access', true );

		if ( ! $guest_access && ! is_user_logged_in() ) {
			return new WP_Error(
				'ACCESS_DENIED',
				__( 'Guest access is disabled. Please log in to use the chat assistant.', 'gemini-chat-assistant' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Permission callback for administrative diagnostics endpoint (/health).
	 *
	 * Requires manage_options capability.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return bool|WP_Error
	 */
	public function check_admin_permissions( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'ACCESS_DENIED',
				__( 'You do not have sufficient permissions to access health diagnostics.', 'gemini-chat-assistant' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Handles POST /wp-json/gca/v1/chat request.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public function handle_chat( WP_REST_Request $request ): WP_REST_Response {
		$request_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'gca_', true );
		$client_id = method_exists( $request, 'get_header' ) ? (string) $request->get_header( 'X-GCA-Request-ID' ) : '';
		if ( preg_match( '/^gca_[a-f0-9-]{16,64}$/D', $client_id ) ) { $request_id = $client_id; }
		$this->chat_service->begin_pipeline( $request_id );
		$status = 500;
		try {
			$response = $this->execute_chat_request( $request, $request_id );
			$status = $response->get_status();
			if ( method_exists( $response, 'header' ) ) { $response->header( 'X-GCA-Request-ID', $request_id ); }
			return $response;
		} catch ( \Throwable $error ) {
			$this->chat_service->pipeline_failure( get_class( $error ) );
			$pipeline     = method_exists( $this->chat_service, 'get_pipeline' ) ? $this->chat_service->get_pipeline() : [];
			$file         = str_replace( '\\', '/', $error->getFile() );
			$root         = rtrim( str_replace( '\\', '/', GCA_PLUGIN_DIR ), '/' ) . '/';
			$safe_file    = 0 === strpos( $file, $root ) ? substr( $file, strlen( $root ) ) : basename( $file );
			$safe_message = $this->sanitize_safe_error_message( $error->getMessage() );
			if ( method_exists( $this->chat_service, 'pipeline_error_details' ) ) {
				$this->chat_service->pipeline_error_details( get_class( $error ), $safe_message, $safe_file, $error->getLine() );
			}
			update_option( 'gca_chat_server_error', [
				'checked_at'   => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
				'request_id'   => $request_id,
				'error_class'  => get_class( $error ),
				'safe_message' => $safe_message,
				'file'         => $safe_file,
				'line'         => $error->getLine(),
				'failed_stage' => $pipeline['failure_stage'] ?? 'DATABASE',
				'failed_step'  => $pipeline['failure_step'] ?? 'CONVERSATION_UPDATE',
			], false );
			return $this->format_error_response( new WP_Error( 'CHAT_SERVER_ERROR', __( 'The website encountered an error while processing your message. Please try again later.', 'gemini-chat-assistant' ), [ 'status' => 500 ] ), $request_id );
		} finally {
			$this->chat_service->finish_pipeline( $status );
		}
	}

	private function execute_chat_request( WP_REST_Request $request, string $request_id ): WP_REST_Response {

		// 1. Extract and validate message.
		$raw_message = $request->get_param( 'message' );
		$max_length  = (int) $this->settings_service->get( 'max_message_length', Validator::DEFAULT_MAX_MESSAGE_LENGTH );
		$message     = Validator::validate_message( $raw_message, $max_length );

		if ( is_wp_error( $message ) ) {
			return $this->format_error_response( $message, $request_id );
		}

		// 2. Extract and validate session ID.
		$raw_session = $request->get_param( 'session_id' );
		$session_id  = Validator::validate_session_id( $raw_session );

		if ( is_wp_error( $session_id ) ) {
			return $this->format_error_response( $session_id, $request_id );
		}

		// 3. Extract and sanitize optional page context.
		$raw_context = $request->get_param( 'context' );
		$context     = Validator::validate_context( $raw_context );

		// 4. Rate limiting check & consume before executing expensive LLM transport.
		$this->chat_service->pipeline_stage( 'rate_limit' );
		$is_fast_path = method_exists( $this->chat_service, 'get_fast_path_response' ) && null !== $this->chat_service->get_fast_path_response( $message );
		$rate_check   = $this->rate_limiter->check_and_consume( $session_id, null, $is_fast_path );
		if ( is_wp_error( $rate_check ) ) {
			return $this->format_error_response( $rate_check, $request_id );
		}

		$this->chat_service->pipeline_stage( 'validation' );
		// 5. Extract optional provider and model overrides (N21).
		$raw_provider = $request->get_param( 'provider' );
		$provider     = ! empty( $raw_provider ) && is_string( $raw_provider ) ? sanitize_key( $raw_provider ) : null;

		$raw_model    = $request->get_param( 'model' );
		$model        = ! empty( $raw_model ) && is_string( $raw_model ) ? sanitize_text_field( trim( $raw_model ) ) : null;

		// 6. Orchestrate chat interaction through ChatService.
		try {
			$result = $this->chat_service->handle_chat( $message, $session_id, $context, $request_id, $provider, $model );
		} catch ( \Throwable $error ) {
			$pipeline     = method_exists( $this->chat_service, 'get_pipeline' ) ? $this->chat_service->get_pipeline() : [];
			$file         = str_replace( '\\', '/', $error->getFile() );
			$root         = rtrim( str_replace( '\\', '/', GCA_PLUGIN_DIR ), '/' ) . '/';
			$safe_file    = 0 === strpos( $file, $root ) ? substr( $file, strlen( $root ) ) : basename( $file );
			$safe_message = $this->sanitize_safe_error_message( $error->getMessage() );
			if ( method_exists( $this->chat_service, 'pipeline_error_details' ) ) {
				$this->chat_service->pipeline_error_details( get_class( $error ), $safe_message, $safe_file, $error->getLine() );
			}
			update_option( 'gca_chat_server_error', [
				'checked_at'   => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
				'request_id'   => $request_id,
				'error_class'  => get_class( $error ),
				'safe_message' => $safe_message,
				'file'         => $safe_file,
				'line'         => $error->getLine(),
				'failed_stage' => $pipeline['failure_stage'] ?? 'DATABASE',
				'failed_step'  => $pipeline['failure_step'] ?? 'CONVERSATION_UPDATE',
			], false );
			return $this->format_error_response( new WP_Error(
				'CHAT_SERVER_ERROR',
				__( 'The website encountered an error while processing your message. Please try again later.', 'gemini-chat-assistant' ),
				[ 'status' => 500 ]
			), $request_id );
		}

		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result, $request_id );
		}

		$this->chat_service->pipeline_stage( 'rest_response' );
		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $result,
			],
			200
		);
	}

	/**
	 * Handles GET /wp-json/gca/v1/providers request (N21).
	 *
	 * Returns safe public metadata for configured AI providers and models.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public function handle_providers( WP_REST_Request $request ): WP_REST_Response {
		$selection_service = Plugin::get_instance()->get_provider_selection_service();
		$metadata          = $selection_service->get_safe_public_providers_metadata();

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $metadata,
			],
			200
		);
	}

	/**
	 * Handles POST /wp-json/gca/v1/reset request.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public function handle_reset( WP_REST_Request $request ): WP_REST_Response {
		$request_id  = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'req_', true );
		$raw_session = $request->get_param( 'session_id' );
		$session_id  = Validator::validate_session_id( $raw_session );

		if ( is_wp_error( $session_id ) ) {
			return $this->format_error_response( $session_id, $request_id );
		}

		$this->chat_service->reset_session( $session_id );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => [
					'status'     => 'reset_successful',
					'session_id' => $session_id,
				],
			],
			200
		);
	}

	/**
	 * Handles POST /wp-json/gca/v1/prechat request.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public function handle_prechat( WP_REST_Request $request ): WP_REST_Response {
		$request_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'req_', true );

		// 1. Extract and validate session ID.
		$raw_session = $request->get_param( 'session_id' );
		$session_id  = Validator::validate_session_id( $raw_session );

		if ( is_wp_error( $session_id ) ) {
			return $this->format_error_response( $session_id, $request_id );
		}

		// 2. Rate limiting check & consume before processing.
		$rate_check = $this->rate_limiter->check_and_consume( $session_id );
		if ( is_wp_error( $rate_check ) ) {
			return $this->format_error_response( $rate_check, $request_id );
		}

		// 3. Extract submitted prechat payload fields.
		$payload = [
			'name'        => $request->get_param( 'name' ),
			'email'       => $request->get_param( 'email' ),
			'phone'       => $request->get_param( 'phone' ),
			'requirement' => $request->get_param( 'requirement' ),
			'website_url' => $request->get_param( 'website_url' ),
		];

		// 4. Delegate to LeadService.
		$result = $this->lead_service->handle_prechat_submission( $payload, $session_id );

		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result, $request_id );
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $result,
			],
			200
		);
	}

	/**
	 * Handles GET /wp-json/gca/v1/health request.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public function handle_health( WP_REST_Request $request ): WP_REST_Response {
		$configured = $this->gemini_client->is_configured();
		$model      = SettingsService::get_model();

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => [
					'status'           => 'healthy',
					'plugin_version'   => defined( 'GCA_VERSION' ) ? GCA_VERSION : '1.0.0',
					'configured'       => $configured,
					'model'            => $model,
					'database_version' => defined( 'GCA_DB_VERSION' ) ? GCA_DB_VERSION : '1.0.0',
				],
			],
			200
		);
	}

	/**
	 * Formats normalized error responses following the public API contract.
	 *
	 * @param WP_Error $error      Error object.
	 * @param string   $request_id Diagnostic request ID.
	 * @return WP_REST_Response
	 */
	private function format_error_response( WP_Error $error, string $request_id ): WP_REST_Response {
		$code     = $error->get_error_code();
		$message  = $error->get_error_message();
		$err_data = $error->get_error_data();
		$status   = is_array( $err_data ) && isset( $err_data['status'] ) ? absint( $err_data['status'] ) : 400;

		$error_payload = [
			'code'       => $code,
			'message'    => $message,
			'request_id' => $request_id,
		];

		if ( is_array( $err_data ) && isset( $err_data['fields'] ) ) {
			$error_payload['fields'] = $err_data['fields'];
		}

		if ( is_array( $err_data ) && isset( $err_data['retry_after'] ) ) {
			$error_payload['retry_after'] = absint( $err_data['retry_after'] );
		}

		$response = new WP_REST_Response(
			[
				'success' => false,
				'error'   => $error_payload,
			],
			$status
		);

		if ( isset( $error_payload['retry_after'] ) ) {
			$response->header( 'Retry-After', (string) $error_payload['retry_after'] );
		}

		return $response;
	}

	/**
	 * Sanitizes PHP error messages to prevent exposing credentials, keys, or private prompts.
	 *
	 * @param string $raw_message Raw exception or error message.
	 * @return string Safe, redacted error message for administrative display.
	 */
	private function sanitize_safe_error_message( string $raw_message ): string {
		// Redact secrets, keys, passwords, credentials, tokens, or prompt content.
		$sanitized = preg_replace( '/\b(api_key|api_secret|secret|password|passwd|token|bearer|prompt|auth)[\w\-]*\b/i', '[REDACTED]', $raw_message );
		$sanitized = preg_replace( '/(AIza[a-zA-Z0-9_\-]{15,}|sk-[a-zA-Z0-9_\-]{15,}|gca_sess_[a-f0-9]{20,})/i', '[REDACTED]', $sanitized );
		// Redact absolute file paths
		$sanitized = preg_replace( '/[A-Za-z]:\\\\[^\s:;,"]+/', '[PATH]', $sanitized );
		$sanitized = preg_replace( '/\/(?:home|var|www|tmp|Users)\/[^\s:;,"]+/', '[PATH]', $sanitized );
		// Redact URLs with query parameters
		$sanitized = preg_replace( '/https?:\/\/[^\s\?]+\?[^\s"]+/', '[REDACTED_URL]', $sanitized );
		return sanitize_text_field( trim( substr( $sanitized, 0, 300 ) ) );
	}
}
