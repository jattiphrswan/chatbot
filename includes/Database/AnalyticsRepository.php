<?php
/**
 * Analytics Database Repository.
 *
 * @package SkyFish\GeminiChat\Database
 */

namespace SkyFish\GeminiChat\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AnalyticsRepository
 *
 * Provides high-performance SQL aggregate queries for chatbot metrics.
 */
class AnalyticsRepository {

	/**
	 * Returns the conversations table name with dynamic WordPress prefix.
	 *
	 * @return string
	 */
	public static function get_conversations_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'gca_conversations';
	}

	/**
	 * Returns the messages table name with dynamic WordPress prefix.
	 *
	 * @return string
	 */
	public static function get_messages_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'gca_messages';
	}

	/**
	 * Fetches aggregated KPI totals for a specific UTC date range.
	 *
	 * @param string|null $start_date UTC start timestamp ('Y-m-d H:i:s') or null for all time.
	 * @param string|null $end_date   UTC end timestamp ('Y-m-d H:i:s') or null for all time.
	 * @return array<string, int|float>
	 */
	public function get_kpis( ?string $start_date = null, ?string $end_date = null ): array {
		global $wpdb;

		$conv_table = self::get_conversations_table();
		$msg_table  = self::get_messages_table();

		// 1. Conversation Aggregates
		$conv_where = '1=1';
		$conv_params = [];
		if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
			$conv_where .= ' AND created_at >= %s AND created_at <= %s';
			$conv_params[] = $start_date;
			$conv_params[] = $end_date;
		}

		$conv_sql = "SELECT
			COUNT(*) as total_conversations,
			COUNT(DISTINCT session_hash) as unique_sessions,
			SUM(CASE WHEN user_id > 0 THEN 1 ELSE 0 END) as user_conversations,
			SUM(CASE WHEN user_id IS NULL OR user_id = 0 THEN 1 ELSE 0 END) as guest_conversations,
			SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_conversations,
			SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_conversations
		FROM {$conv_table} WHERE {$conv_where}";

		if ( ! empty( $conv_params ) ) {
			$conv_query = $wpdb->prepare( $conv_sql, $conv_params );
		} else {
			$conv_query = $conv_sql;
		}

		$conv_res = $wpdb->get_row( $conv_query, ARRAY_A ) ?: [];

		// 2. Message Aggregates
		$msg_where = '1=1';
		$msg_params = [];
		if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
			$msg_where .= ' AND created_at >= %s AND created_at <= %s';
			$msg_params[] = $start_date;
			$msg_params[] = $end_date;
		}

		$msg_sql = "SELECT
			COUNT(*) as total_messages,
			SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_messages,
			SUM(CASE WHEN role = 'assistant' THEN 1 ELSE 0 END) as assistant_messages,
			COALESCE(SUM(input_tokens), 0) as total_input_tokens,
			COALESCE(SUM(output_tokens), 0) as total_output_tokens,
			COALESCE(AVG(CASE WHEN role = 'assistant' AND latency_ms > 0 THEN latency_ms ELSE NULL END), 0) as avg_latency_ms
		FROM {$msg_table} WHERE {$msg_where}";

		if ( ! empty( $msg_params ) ) {
			$msg_query = $wpdb->prepare( $msg_sql, $msg_params );
		} else {
			$msg_query = $msg_sql;
		}

		$msg_res = $wpdb->get_row( $msg_query, ARRAY_A ) ?: [];

		return [
			'total_conversations'   => (int) ( $conv_res['total_conversations'] ?? 0 ),
			'unique_sessions'       => (int) ( $conv_res['unique_sessions'] ?? 0 ),
			'user_conversations'    => (int) ( $conv_res['user_conversations'] ?? 0 ),
			'guest_conversations'   => (int) ( $conv_res['guest_conversations'] ?? 0 ),
			'active_conversations'  => (int) ( $conv_res['active_conversations'] ?? 0 ),
			'closed_conversations'  => (int) ( $conv_res['closed_conversations'] ?? 0 ),
			'total_messages'        => (int) ( $msg_res['total_messages'] ?? 0 ),
			'user_messages'         => (int) ( $msg_res['user_messages'] ?? 0 ),
			'assistant_messages'    => (int) ( $msg_res['assistant_messages'] ?? 0 ),
			'total_input_tokens'    => (int) ( $msg_res['total_input_tokens'] ?? 0 ),
			'total_output_tokens'   => (int) ( $msg_res['total_output_tokens'] ?? 0 ),
			'avg_latency_ms'        => (float) ( $msg_res['avg_latency_ms'] ?? 0.0 ),
		];
	}

	/**
	 * Returns conversation counts today using WordPress local timezone boundaries.
	 *
	 * @param string $today_start_utc UTC start datetime for local today.
	 * @param string $today_end_utc   UTC end datetime for local today.
	 * @return int
	 */
	public function get_conversations_today( string $today_start_utc, string $today_end_utc ): int {
		global $wpdb;

		$table = self::get_conversations_table();
		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND created_at <= %s",
			$today_start_utc,
			$today_end_utc
		);

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Fetches daily conversation volume time series.
	 *
	 * @param string|null $start_date UTC start timestamp ('Y-m-d H:i:s') or null.
	 * @param string|null $end_date   UTC end timestamp ('Y-m-d H:i:s') or null.
	 * @return array<int, array{date_bucket: string, count: int}>
	 */
	public function get_daily_conversations( ?string $start_date = null, ?string $end_date = null ): array {
		global $wpdb;

		$table = self::get_conversations_table();
		$where = '1=1';
		$params = [];

		if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
			$where .= ' AND created_at >= %s AND created_at <= %s';
			$params[] = $start_date;
			$params[] = $end_date;
		}

		$sql = "SELECT DATE(created_at) as date_bucket, COUNT(*) as count FROM {$table} WHERE {$where} GROUP BY DATE(created_at) ORDER BY date_bucket ASC";

		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( $sql, $params );
		} else {
			$query = $sql;
		}

		$results = $wpdb->get_results( $query, ARRAY_A );
		if ( ! is_array( $results ) ) {
			return [];
		}

		return array_map(
			function( $row ) {
				return [
					'date_bucket' => (string) $row['date_bucket'],
					'count'       => (int) $row['count'],
				];
			},
			$results
		);
	}

	/**
	 * Fetches daily stored message volume time series.
	 *
	 * @param string|null $start_date UTC start timestamp ('Y-m-d H:i:s') or null.
	 * @param string|null $end_date   UTC end timestamp ('Y-m-d H:i:s') or null.
	 * @return array<int, array{date_bucket: string, count: int}>
	 */
	public function get_daily_messages( ?string $start_date = null, ?string $end_date = null ): array {
		global $wpdb;

		$table = self::get_messages_table();
		$where = '1=1';
		$params = [];

		if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
			$where .= ' AND created_at >= %s AND created_at <= %s';
			$params[] = $start_date;
			$params[] = $end_date;
		}

		$sql = "SELECT DATE(created_at) as date_bucket, COUNT(*) as count FROM {$table} WHERE {$where} GROUP BY DATE(created_at) ORDER BY date_bucket ASC";

		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( $sql, $params );
		} else {
			$query = $sql;
		}

		$results = $wpdb->get_results( $query, ARRAY_A );
		if ( ! is_array( $results ) ) {
			return [];
		}

		return array_map(
			function( $row ) {
				return [
					'date_bucket' => (string) $row['date_bucket'],
					'count'       => (int) $row['count'],
				];
			},
			$results
		);
	}

	/**
	 * Fetches model usage distribution for assistant responses.
	 *
	 * @param string|null $start_date UTC start timestamp ('Y-m-d H:i:s') or null.
	 * @param string|null $end_date   UTC end timestamp ('Y-m-d H:i:s') or null.
	 * @return array<int, array{model: string, count: int}>
	 */
	public function get_model_usage( ?string $start_date = null, ?string $end_date = null ): array {
		global $wpdb;

		$table = self::get_messages_table();
		$where = "role = 'assistant'";
		$params = [];

		if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
			$where .= ' AND created_at >= %s AND created_at <= %s';
			$params[] = $start_date;
			$params[] = $end_date;
		}

		$sql = "SELECT COALESCE(NULLIF(model, ''), 'default') as model_name, COUNT(*) as count FROM {$table} WHERE {$where} GROUP BY model_name ORDER BY count DESC";

		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( $sql, $params );
		} else {
			$query = $sql;
		}

		$results = $wpdb->get_results( $query, ARRAY_A );
		if ( ! is_array( $results ) ) {
			return [];
		}

		return array_map(
			function( $row ) {
				return [
					'model' => (string) $row['model_name'],
					'count' => (int) $row['count'],
				];
			},
			$results
		);
	}
}
