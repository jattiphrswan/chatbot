<?php
/**
 * WooCommerce Search Products Action.
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
 * Class SearchProductsAction
 *
 * Safe read-only action searching published WooCommerce products by query string.
 */
class SearchProductsAction implements ActionInterface {

	public const ID          = 'woocommerce.search_products';
	public const MAX_RESULTS = 10;

	public function get_id(): string {
		return self::ID;
	}

	public function get_name(): string {
		return __( 'Search WooCommerce Products', 'gemini-chat-assistant' );
	}

	public function get_description(): string {
		return __( 'Searches published WooCommerce products by keyword or name. Returns matching product details.', 'gemini-chat-assistant' );
	}

	public function get_risk(): string {
		return self::RISK_READ;
	}

	public function get_input_schema(): array {
		return [
			'query' => [
				'type'        => 'string',
				'required'    => true,
				'min_length'  => 1,
				'max_length'  => 200,
				'description' => __( 'Search term, product name, or keyword.', 'gemini-chat-assistant' ),
			],
			'limit' => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 5,
				'min'         => 1,
				'max'         => self::MAX_RESULTS,
				'description' => __( 'Maximum number of items to return (up to 10).', 'gemini-chat-assistant' ),
			],
		];
	}

	public function can_execute( array $arguments = [] ): bool {
		return function_exists( 'wc_get_products' );
	}

	public function execute( array $arguments = [] ): ActionResult {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return ActionResult::failure(
				'WOOCOMMERCE_UNAVAILABLE',
				__( 'WooCommerce is not active in this environment.', 'gemini-chat-assistant' )
			);
		}

		$query = isset( $arguments['query'] ) ? trim( (string) $arguments['query'] ) : '';
		if ( '' === $query ) {
			return ActionResult::failure(
				'INVALID_QUERY',
				__( 'Search query cannot be empty.', 'gemini-chat-assistant' )
			);
		}

		$limit = isset( $arguments['limit'] ) ? (int) $arguments['limit'] : 5;
		$limit = max( 1, min( self::MAX_RESULTS, $limit ) );

		try {
			$products = wc_get_products( [
				'status'  => 'publish',
				'limit'   => $limit,
				's'       => $query,
				'orderby' => 'relevance',
				'order'   => 'DESC',
			] );

			$normalized = [];
			if ( is_array( $products ) ) {
				foreach ( $products as $product ) {
					if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
						$normalized[] = WooCommerceFormatter::format_summary( $product );
					}
				}
			}

			return ActionResult::success(
				[
					'query'    => $query,
					'count'    => count( $normalized ),
					'products' => $normalized,
				],
				sprintf(
					/* translators: 1: number of products, 2: search query */
					__( 'Found %1$d product(s) matching "%2$s".', 'gemini-chat-assistant' ),
					count( $normalized ),
					esc_html( $query )
				)
			);
		} catch ( \Throwable $e ) {
			return ActionResult::failure(
				'WOOCOMMERCE_SEARCH_FAILED',
				__( 'Failed to search WooCommerce products.', 'gemini-chat-assistant' )
			);
		}
	}
}
