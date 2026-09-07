<?php
/**
 * WooCommerce Product Normalization Formatter.
 *
 * @package SkyFish\GeminiChat\Integrations\WooCommerce
 */

namespace SkyFish\GeminiChat\Integrations\WooCommerce;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WooCommerceFormatter
 *
 * Safely normalizes WC_Product objects into structured arrays without exposing internal models or credentials.
 */
class WooCommerceFormatter {

	/**
	 * Formats a product summary item for search results.
	 *
	 * @param object $product WC_Product instance.
	 * @return array<string, mixed>
	 */
	public static function format_summary( object $product ): array {
		$id        = method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0;
		$name      = method_exists( $product, 'get_name' ) ? sanitize_text_field( $product->get_name() ) : '';
		$url       = function_exists( 'get_permalink' ) ? get_permalink( $id ) : '';
		$sku       = method_exists( $product, 'get_sku' ) ? sanitize_text_field( (string) $product->get_sku() ) : '';
		$price     = method_exists( $product, 'get_price' ) ? sanitize_text_field( (string) $product->get_price() ) : '';
		$sale      = method_exists( $product, 'get_sale_price' ) ? sanitize_text_field( (string) $product->get_sale_price() ) : '';
		$stock     = method_exists( $product, 'get_stock_status' ) ? sanitize_text_field( (string) $product->get_stock_status() ) : '';
		$short_des = method_exists( $product, 'get_short_description' ) ? wp_strip_all_tags( (string) $product->get_short_description() ) : '';

		// Resolve primary category
		$category = '';
		if ( function_exists( 'wp_get_post_terms' ) && $id > 0 ) {
			$terms = wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'names' ] );
			if ( ! empty( $terms ) && is_array( $terms ) && ! is_wp_error( $terms ) ) {
				$category = (string) reset( $terms );
			}
		}

		return [
			'id'                => $id,
			'name'              => $name,
			'url'               => esc_url_raw( (string) $url ),
			'sku'               => $sku,
			'price'             => $price,
			'sale_price'        => $sale,
			'stock_status'      => $stock,
			'category'          => $category,
			'short_description' => mb_substr( trim( $short_des ), 0, 300, 'UTF-8' ),
		];
	}

	/**
	 * Formats full product details.
	 *
	 * @param object $product WC_Product instance.
	 * @return array<string, mixed>
	 */
	public static function format_detail( object $product ): array {
		$summary = self::format_summary( $product );
		$id      = $summary['id'];

		$description = method_exists( $product, 'get_description' ) ? wp_strip_all_tags( (string) $product->get_description() ) : '';

		$categories = [];
		if ( function_exists( 'wp_get_post_terms' ) && $id > 0 ) {
			$terms = wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'names' ] );
			if ( ! empty( $terms ) && is_array( $terms ) && ! is_wp_error( $terms ) ) {
				$categories = array_values( array_map( 'strval', $terms ) );
			}
		}

		return [
			'id'           => $summary['id'],
			'name'         => $summary['name'],
			'url'          => $summary['url'],
			'sku'          => $summary['sku'],
			'description'  => mb_substr( trim( $description ), 0, 1500, 'UTF-8' ),
			'price'        => $summary['price'],
			'sale_price'   => $summary['sale_price'],
			'stock_status' => $summary['stock_status'],
			'categories'   => $categories,
		];
	}
}
