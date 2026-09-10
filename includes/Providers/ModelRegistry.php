<?php
/**
 * Central Model Registry for AI Providers.
 *
 * @package SkyFish\GeminiChat\Providers
 */

namespace SkyFish\GeminiChat\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ModelRegistry
 *
 * Single maintainable source of truth for supported AI models across all providers.
 */
class ModelRegistry {

	/**
	 * Supported models catalog indexed by provider identifier.
	 *
	 * @var array<string, array<int, array{id: string, name: string, provider: string, context_window: int, max_output_tokens: int, description: string, recommended: bool}>>
	 */
	public const MODELS = [
		'gemini' => [
			[
				'id'                => 'gemini-2.5-flash',
				'name'              => 'Gemini 2.5 Flash',
				'provider'          => 'gemini',
				'context_window'    => 1048576,
				'max_output_tokens' => 8192,
				'description'       => 'High-speed, cost-effective multimodal model for real-time chat interactions.',
				'recommended'       => true,
			],
			[
				'id'                => 'gemini-2.0-flash',
				'name'              => 'Gemini 2.0 Flash',
				'provider'          => 'gemini',
				'context_window'    => 1048576,
				'max_output_tokens' => 8192,
				'description'       => 'Next-generation fast model optimized for chat and agentic workflows.',
				'recommended'       => false,
			],
			[
				'id'                => 'gemini-2.5-pro',
				'name'              => 'Gemini 2.5 Pro',
				'provider'          => 'gemini',
				'context_window'    => 1048576,
				'max_output_tokens' => 8192,
				'description'       => 'Advanced reasoning and complex problem-solving model.',
				'recommended'       => false,
			],
			[
				'id'                => 'gemini-3.8-flash',
				'name'              => 'Gemini 3.8 Flash',
				'provider'          => 'gemini',
				'context_window'    => 1048576,
				'max_output_tokens' => 8192,
				'description'       => 'Fast multimodal model for conversational interactions.',
				'recommended'       => false,
			],
		],
		'openai' => [
			[
				'id'                => 'gpt-4o-mini',
				'name'              => 'GPT-4o mini',
				'provider'          => 'openai',
				'context_window'    => 128000,
				'max_output_tokens' => 4096,
				'description'       => 'Cost-effective small model for lightweight conversational workflows.',
				'recommended'       => true,
			],
			[
				'id'                => 'gpt-4o',
				'name'              => 'GPT-4o',
				'provider'          => 'openai',
				'context_window'    => 128000,
				'max_output_tokens' => 4096,
				'description'       => 'Flagship omni-model for complex tasks and fast general intelligence.',
				'recommended'       => false,
			],
			[
				'id'                => 'gpt-4-turbo',
				'name'              => 'GPT-4 Turbo',
				'provider'          => 'openai',
				'context_window'    => 128000,
				'max_output_tokens' => 4096,
				'description'       => 'High-intelligence model for broad reasoning tasks.',
				'recommended'       => false,
			],
		],
		'claude' => [
			[
				'id'                => 'claude-3-5-haiku-20241022',
				'name'              => 'Claude 3.5 Haiku',
				'provider'          => 'claude',
				'context_window'    => 200000,
				'max_output_tokens' => 8192,
				'description'       => 'Ultra-fast lightweight model for low-latency chat interactions.',
				'recommended'       => true,
			],
			[
				'id'                => 'claude-3-5-sonnet-20241022',
				'name'              => 'Claude 3.5 Sonnet',
				'context_window'    => 200000,
				'max_output_tokens' => 8192,
				'description'       => 'High-intelligence model with strong reasoning and coding comprehension.',
				'recommended'       => false,
			],
			[
				'id'                => 'claude-3-opus-20240229',
				'name'              => 'Claude 3 Opus',
				'provider'          => 'claude',
				'context_window'    => 200000,
				'max_output_tokens' => 4096,
				'description'       => 'Deep analysis model for complex nuance.',
				'recommended'       => false,
			],
			[
				'id'                => 'claude-sonnet-4-6',
				'name'              => 'Claude Sonnet 4.6',
				'provider'          => 'claude',
				'context_window'    => 200000,
				'max_output_tokens' => 8192,
				'description'       => 'Next-generation Sonnet foundation model.',
				'recommended'       => false,
			],
			[
				'id'                => 'claude-opus-4-8',
				'name'              => 'Claude Opus 4.8',
				'provider'          => 'claude',
				'context_window'    => 200000,
				'max_output_tokens' => 8192,
				'description'       => 'Next-generation Opus frontier model.',
				'recommended'       => false,
			],
			[
				'id'                => 'claude-haiku-4-5-20251001',
				'name'              => 'Claude Haiku 4.5',
				'provider'          => 'claude',
				'context_window'    => 200000,
				'max_output_tokens' => 8192,
				'description'       => 'Next-generation Haiku high-efficiency model.',
				'recommended'       => false,
			],
		],
	];

	/**
	 * Returns all models grouped by provider.
	 *
	 * @return array<string, array<int, array{id: string, name: string, provider: string, context_window: int, max_output_tokens: int, description: string, recommended: bool}>>
	 */
	public static function get_all(): array {
		return self::MODELS;
	}

	/**
	 * Returns models supported by a specific provider.
	 *
	 * @param string $provider_id Provider identifier ('gemini', 'openai', 'claude').
	 * @return array<int, array{id: string, name: string, provider: string, context_window: int, max_output_tokens: int, description: string, recommended: bool}>
	 */
	public static function get_models_for_provider( string $provider_id ): array {
		$provider_id   = sanitize_key( $provider_id );
		$static_models = self::MODELS[ $provider_id ] ?? [];

		if ( 'gemini' === $provider_id ) {
			$discovered = get_option( 'gca_discovered_models_gemini', [] );
			if ( is_array( $discovered ) && ! empty( $discovered ) ) {
				return $discovered;
			}
		}

		return $static_models;
	}

	/**
	 * Checks if a specific model ID belongs to a given provider.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $model_id    Model identifier.
	 * @return bool
	 */
	public static function has_model( string $provider_id, string $model_id ): bool {
		$provider_id = sanitize_key( $provider_id );
		$model_id    = trim( $model_id );
		$models      = self::get_models_for_provider( $provider_id );

		foreach ( $models as $m ) {
			if ( $m['id'] === $model_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retrieves model definition for a given provider and model ID.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $model_id    Model identifier.
	 * @return array{id: string, name: string, provider: string, context_window: int, max_output_tokens: int, description: string, recommended: bool}|null
	 */
	public static function get_model( string $provider_id, string $model_id ): ?array {
		$provider_id = sanitize_key( $provider_id );
		$model_id    = trim( $model_id );
		$models      = self::get_models_for_provider( $provider_id );

		foreach ( $models as $m ) {
			if ( $m['id'] === $model_id ) {
				return $m;
			}
		}

		return null;
	}

	/**
	 * Returns friendly display name for a model ID.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $model_id    Model identifier.
	 * @return string Friendly display name or the model ID if not found.
	 */
	public static function get_model_name( string $provider_id, string $model_id ): string {
		$model = self::get_model( $provider_id, $model_id );
		return $model['name'] ?? $model_id;
	}

	/**
	 * Returns default / recommended model ID for a given provider.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return string Model identifier.
	 */
	public static function get_default_model( string $provider_id ): string {
		$models = self::get_models_for_provider( $provider_id );

		foreach ( $models as $m ) {
			if ( ! empty( $m['recommended'] ) ) {
				return $m['id'];
			}
		}

		return ! empty( $models[0]['id'] ) ? $models[0]['id'] : '';
	}

	/**
	 * Returns all registered model IDs across all providers as a flat array.
	 *
	 * @return string[]
	 */
	public static function get_all_model_ids(): array {
		$ids = [];
		foreach ( self::MODELS as $provider_models ) {
			foreach ( $provider_models as $m ) {
				$ids[] = $m['id'];
			}
		}
		return $ids;
	}
}
