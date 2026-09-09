<?php
/**
 * Normalized AI Provider Response Value Object.
 *
 * @package SkyFish\GeminiChat\Providers
 */

namespace SkyFish\GeminiChat\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProviderResponse
 *
 * Normalized value object encapsulating provider chat output and token usage.
 */
class ProviderResponse {

	private string $text;
	private string $provider_id;
	private string $model_id;
	private ?int $input_tokens;
	private ?int $output_tokens;
	private ?int $total_tokens;
	private string $finish_reason;
	private ?string $request_id;
	private array $metadata;

	/**
	 * Constructor.
	 */
	public function __construct(
		string $text,
		string $provider_id,
		string $model_id,
		?int $input_tokens = null,
		?int $output_tokens = null,
		?int $total_tokens = null,
		string $finish_reason = 'stop',
		?string $request_id = null,
		array $metadata = []
	) {
		$this->text          = $text;
		$this->provider_id   = sanitize_key( $provider_id );
		$this->model_id      = sanitize_text_field( $model_id );
		$this->input_tokens  = $input_tokens;
		$this->output_tokens = $output_tokens;
		$this->total_tokens  = $total_tokens ?? ( ( null !== $input_tokens && null !== $output_tokens ) ? ( $input_tokens + $output_tokens ) : null );
		$this->finish_reason = sanitize_key( $finish_reason );
		$this->request_id    = $request_id ? sanitize_text_field( $request_id ) : null;
		$this->metadata      = $metadata;
	}

	public function get_text(): string {
		return $this->text;
	}

	public function get_provider_id(): string {
		return $this->provider_id;
	}

	public function get_model_id(): string {
		return $this->model_id;
	}

	public function get_model(): string {
		return $this->model_id;
	}

	public function get_input_tokens(): ?int {
		return $this->input_tokens;
	}

	public function get_output_tokens(): ?int {
		return $this->output_tokens;
	}

	public function get_total_tokens(): ?int {
		return $this->total_tokens;
	}

	public function get_finish_reason(): string {
		return $this->finish_reason;
	}

	public function get_request_id(): ?string {
		return $this->request_id;
	}

	public function get_metadata(): array {
		return $this->metadata;
	}

	public function get_tokens(): array {
		return [
			'input'  => $this->input_tokens,
			'output' => $this->output_tokens,
			'total'  => $this->total_tokens,
		];
	}

	/**
	 * Serializes response to associative array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'text'          => $this->text,
			'provider'      => $this->provider_id,
			'model'         => $this->model_id,
			'finish_reason' => $this->finish_reason,
			'request_id'    => $this->request_id,
			'tokens'        => $this->get_tokens(),
			'metadata'      => $this->metadata,
		];
	}
}
