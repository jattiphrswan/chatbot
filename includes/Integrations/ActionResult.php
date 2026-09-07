<?php
/**
 * Normalized Action Result Object.
 *
 * @package SkyFish\GeminiChat\Integrations
 */

namespace SkyFish\GeminiChat\Integrations;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ActionResult
 *
 * Immutable, normalized result object produced by an integration action execution.
 * Guarantees zero credential leakage, zero raw stack traces, and deterministic result contracts.
 */
class ActionResult {

	private bool $success;
	private mixed $data;
	private ?string $message;
	private ?array $error;

	/**
	 * ActionResult constructor.
	 *
	 * @param bool         $success Whether the action executed successfully.
	 * @param mixed        $data    Action payload data (must not contain secrets/credentials).
	 * @param string|null  $message Optional human-readable message.
	 * @param array|null   $error   Structured error info with 'code' and 'message'.
	 */
	private function __construct( bool $success, mixed $data = null, ?string $message = null, ?array $error = null ) {
		$this->success = $success;
		$this->data    = $data;
		$this->message = $message;
		$this->error   = $error;
	}

	/**
	 * Factory method for a successful action execution.
	 *
	 * @param mixed       $data    Output data payload.
	 * @param string|null $message Optional success message.
	 * @return self
	 */
	public static function success( mixed $data = null, ?string $message = null ): self {
		return new self( true, $data, $message, null );
	}

	/**
	 * Factory method for a failed action execution.
	 *
	 * @param string      $error_code    Controlled machine-readable error code.
	 * @param string      $error_message Safe human-readable error description (no traces or raw exceptions).
	 * @param mixed       $data          Optional diagnostic data (clean of secrets).
	 * @return self
	 */
	public static function failure( string $error_code, string $error_message, mixed $data = null ): self {
		return new self(
			false,
			$data,
			null,
			[
				'code'    => sanitize_key( $error_code ),
				'message' => sanitize_text_field( $error_message ),
			]
		);
	}

	/**
	 * Checks if the action succeeded.
	 *
	 * @return bool
	 */
	public function is_success(): bool {
		return $this->success;
	}

	/**
	 * Returns the data payload.
	 *
	 * @return mixed
	 */
	public function get_data(): mixed {
		return $this->data;
	}

	/**
	 * Returns the human-readable message.
	 *
	 * @return string|null
	 */
	public function get_message(): ?string {
		return $this->message;
	}

	/**
	 * Returns structured error information if failed.
	 *
	 * @return array{code: string, message: string}|null
	 */
	public function get_error(): ?array {
		return $this->error;
	}

	/**
	 * Normalizes result to an associative array.
	 *
	 * @return array{success: bool, data: mixed, message: string|null, error: array{code: string, message: string}|null}
	 */
	public function to_array(): array {
		return [
			'success' => $this->success,
			'data'    => $this->data,
			'message' => $this->message,
			'error'   => $this->error,
		];
	}
}
