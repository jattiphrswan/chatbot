<?php
/**
 * WooCommerce Get Product Action.
 *
 * @package SkyFish\GeminiChat\Integrations\WooCommerce
 */

namespace SkyFish\GeminiChat\Integrations\WooCommerce;

use SkyFish\GeminiChat\Integrations\ActionInterface;
use SkyFish\GeminiChat\Integrations\ActionResult;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GetProductAction
 *
 * Safe read-only action retrieving detailed information for a single WooCommerce product.
 */
class GetProductAction implements ActionInterface {

	public const ID = 'woocommerce.get_product';

	public function get_id(): string {
		return self::ID;
	}

	public function get_name(): string {
		return __( 'Get WooCommerce Product Details', 'gemini-chat-assistant' );
	}

	public function get_description(): string {
		return __( 'Retrieves full details for a specific WooCommerce product by product ID.', 'gemini-chat-assistant' );
	}

	public function get_risk(): string {
		return self::RISK_READ;
	}

	public function get_input_schema(): array {
		return [
			'product_id' => [
				'type'        => 'integer',
				'required'    => true,
				'min'         => 1,
				'description' => __( 'The unique numeric WooCommerce product ID.', 'gemini-chat-assistant' ),
			],
		];
	}

	public function can_execute( array $arguments = [] ): bool {
		return function_exists( 'wc_get_product' );
	}

	public function execute( array $arguments = [] ): ActionResult {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return ActionResult::failure(
				'WOOCOMMERCE_UNAVAILABLE',
				__( 'WooCommerce is not active in this environment.', 'gemini-chat-assistant' )
			);
		}

		$product_id = isset( $arguments['product_id'] ) ? (int) $arguments['product_id'] : 0;
		if ( $product_id <= 0 ) {
			return ActionResult::failure(
				'INVALID_PRODUCT_ID',
				__( 'A valid positive product ID is required.', 'gemini-chat-assistant' )
			);
		}

		try {
			$product = wc_get_product( $product_id );

			if ( ! $product || ! is_object( $product ) ) {
				return ActionResult::failure(
					'PRODUCT_NOT_FOUND',
					sprintf(
						/* translators: %d: product ID */
						__( 'Product #%d was not found.', 'gemini-chat-assistant' ),
						$product_id
					)
				);
			}

			// Check publication status (only published products).
			if ( method_exists( $product, 'get_status' ) && 'publish' !== $product->get_status() ) {
				return ActionResult::failure(
					'PRODUCT_NOT_FOUND',
					sprintf(
						/* translators: %d: product ID */
						__( 'Product #%d is not published or currently unavailable.', 'gemini-chat-assistant' ),
						$product_id
					)
				);
			}

			$data = WooCommerceFormatter::format_detail( $product );

			return ActionResult::success(
				$data,
				sprintf(
					/* translators: %s: product name */
					__( 'Retrieved details for "%s".', 'gemini-chat-assistant' ),
					esc_html( $data['name'] ?? '' )
				)
			);
		} catch ( \Throwable $e ) {
			return ActionResult::failure(
				'WOOCOMMERCE_LOOKUP_FAILED',
				__( 'Failed to retrieve product details.', 'gemini-chat-assistant' )
			);
		}
	}
}
