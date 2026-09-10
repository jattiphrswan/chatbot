<?php
/**
 * Analytics Business Logic Service.
 *
 * @package SkyFish\GeminiChat\Admin
 */

namespace SkyFish\GeminiChat\Admin;

use SkyFish\GeminiChat\Database\AnalyticsRepository;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AnalyticsService
 *
 * Orchestrates date filtering, KPI derivations, time-series continuity, and chart datasets.
 */
class AnalyticsService {

	public const ALLOWED_RANGES = [ 'today', '7days', '30days', '90days', 'all', 'custom' ];
	public const DEFAULT_RANGE  = '30days';

	private AnalyticsRepository $repository;

	/**
	 * AnalyticsService constructor.
	 *
	 * @param AnalyticsRepository|null $repository Optional analytics repository.
	 */
	public function __construct( ?AnalyticsRepository $repository = null ) {
		$this->repository = $repository ?? new AnalyticsRepository();
	}

	/**
	 * Resolves date range parameter into UTC start and end bounds.
	 *
	 * @param string      $range_key   Predefined range key ('today', '7days', '30days', '90days', 'all', 'custom').
	 * @param string|null $custom_from Optional custom start date ('Y-m-d').
	 * @param string|null $custom_to   Optional custom end date ('Y-m-d').
	 * @return array{range: string, label: string, start_utc: string|null, end_utc: string|null, start_local: string|null, end_local: string|null, is_continuous: bool}
	 */
	public function resolve_date_range( string $range_key = self::DEFAULT_RANGE, ?string $custom_from = null, ?string $custom_to = null ): array {
		if ( ! in_array( $range_key, self::ALLOWED_RANGES, true ) ) {
			$range_key = self::DEFAULT_RANGE;
		}

		$wp_tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$utc_tz = new \DateTimeZone( 'UTC' );
		$now_local = new \DateTimeImmutable( 'now', $wp_tz );

		$start_utc = null;
		$end_utc   = null;
		$start_local = null;
		$end_local   = null;
		$is_continuous = true;
		$label = __( 'Last 30 Days', 'gemini-chat-assistant' );

		switch ( $range_key ) {
			case 'today':
				$label       = __( 'Today', 'gemini-chat-assistant' );
				$start_dt    = $now_local->setTime( 0, 0, 0 );
				$end_dt      = $now_local->setTime( 23, 59, 59 );
				$start_local = $start_dt->format( 'Y-m-d H:i:s' );
				$end_local   = $end_dt->format( 'Y-m-d H:i:s' );
				$start_utc   = $start_dt->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
				$end_utc     = $end_dt->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
				break;

			case '7days':
				$label       = __( 'Last 7 Days', 'gemini-chat-assistant' );
				$start_dt    = $now_local->sub( new \DateInterval( 'P6D' ) )->setTime( 0, 0, 0 );
				$end_dt      = $now_local->setTime( 23, 59, 59 );
				$start_local = $start_dt->format( 'Y-m-d H:i:s' );
				$end_local   = $end_dt->format( 'Y-m-d H:i:s' );
				$start_utc   = $start_dt->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
				$end_utc     = $end_dt->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
				break;

			case '90days':
				$label       = __( 'Last 90 Days', 'gemini-chat-assistant' );
				$start_dt    = $now_local->sub( new \DateInterval( 'P89D' ) )->setTime( 0, 0, 0 );
				$end_dt      = $now_local->setTime( 23, 59, 59 );
				$start_local = $start_dt->format( 'Y-m-d H:i:s' );
				$end_local   = $end_dt->format( 'Y-m-d H:i:s' );
				$start_utc   = $start_dt->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
				$end_utc     = $end_dt->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
				break;

			case 'all':
				$label         = __( 'All Time', 'gemini-chat-assistant' );
				$start_utc     = null;
				$end_utc       = null;
				$start_local   = null;
				$end_local     = null;
				$is_continuous = false;
				break;

			case 'custom':
				if ( ! empty( $custom_from ) && ! empty( $custom_to ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $custom_from ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $custom_to ) ) {
					$from_dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $custom_from, $wp_tz );
					$to_dt   = \DateTimeImmutable::createFromFormat( 'Y-m-d', $custom_to, $wp_tz );

					if ( $from_dt && $to_dt && $from_dt <= $to_dt ) {
						$label       = sprintf( __( '%1$s to %2$s', 'gemini-chat-assistant' ), $custom_from, $custom_to );
						$start_dt    = $from_dt->setTime( 0, 0, 0 );
						$end_dt      = $to_dt->setTime( 23, 59, 59 );
						$start_local = $start_dt->format( 'Y-m-d H:i:s' );
						$end_local   = $end_dt->format( 'Y-m-d H:i:s' );
						$start_utc   = $start_dt->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
						$end_utc     = $end_dt->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
						break;
					}
				}
				// Fallback to 30days if custom range invalid
				$range_key = '30days';
				// Intentional fallthrough to 30days.

			case '30days':
			default:
				$range_key   = '30days';
				$label       = __( 'Last 30 Days', 'gemini-chat-assistant' );
				$start_dt    = $now_local->sub( new \DateInterval( 'P29D' ) )->setTime( 0, 0, 0 );
				$end_dt      = $now_local->setTime( 23, 59, 59 );
				$start_local = $start_dt->format( 'Y-m-d H:i:s' );
				$end_local   = $end_dt->format( 'Y-m-d H:i:s' );
				$start_utc   = $start_dt->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
				$end_utc     = $end_dt->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
				break;
		}

		return [
			'range'         => $range_key,
			'label'         => $label,
			'start_utc'     => $start_utc,
			'end_utc'       => $end_utc,
			'start_local'   => $start_local,
			'end_local'     => $end_local,
			'is_continuous' => $is_continuous,
		];
	}

	/**
	 * Gathers full analytics report for a date range.
	 *
	 * @param string      $range_key   Predefined range key.
	 * @param string|null $custom_from Optional custom start.
	 * @param string|null $custom_to   Optional custom end.
	 * @return array<string, mixed>
	 */
	public function get_analytics_data( string $range_key = self::DEFAULT_RANGE, ?string $custom_from = null, ?string $custom_to = null ): array {
		$range_info = $this->resolve_date_range( $range_key, $custom_from, $custom_to );
		$start_utc  = $range_info['start_utc'];
		$end_utc    = $range_info['end_utc'];

		$kpis = $this->repository->get_kpis( $start_utc, $end_utc );

		// Local today calculation for Today KPI
		$wp_tz     = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$utc_tz    = new \DateTimeZone( 'UTC' );
		$now_local = new \DateTimeImmutable( 'now', $wp_tz );
		$t_start   = $now_local->setTime( 0, 0, 0 )->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
		$t_end     = $now_local->setTime( 23, 59, 59 )->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
		$today_count = $this->repository->get_conversations_today( $t_start, $t_end );

		// Compute derived metrics safely
		$total_conv = $kpis['total_conversations'];
		$total_msg  = $kpis['total_messages'];
		$avg_msg_per_conv = $total_conv > 0 ? round( $total_msg / $total_conv, 1 ) : 0.0;

		$total_tokens = $kpis['total_input_tokens'] + $kpis['total_output_tokens'];

		// Formatted latency
		$avg_latency = $kpis['avg_latency_ms'];
		$formatted_latency = __( 'Not enough data', 'gemini-chat-assistant' );
		if ( $avg_latency > 0 ) {
			if ( $avg_latency >= 1000 ) {
				$formatted_latency = sprintf( __( '%1.1fs', 'gemini-chat-assistant' ), round( $avg_latency / 1000, 1, PHP_ROUND_HALF_UP ) );
			} else {
				$formatted_latency = sprintf( __( '%dms', 'gemini-chat-assistant' ), (int) $avg_latency );
			}
		}

		// Status breakdown percentages
		$active_conv = $kpis['active_conversations'];
		$closed_conv = $kpis['closed_conversations'];
		$active_pct  = $total_conv > 0 ? (int) round( ( $active_conv / $total_conv ) * 100 ) : 0;
		$closed_pct  = $total_conv > 0 ? (int) round( ( $closed_conv / $total_conv ) * 100 ) : 0;

		// Audience breakdown percentages
		$guest_conv = $kpis['guest_conversations'];
		$user_conv  = $kpis['user_conversations'];
		$guest_pct  = $total_conv > 0 ? (int) round( ( $guest_conv / $total_conv ) * 100 ) : 0;
		$user_pct   = $total_conv > 0 ? (int) round( ( $user_conv / $total_conv ) * 100 ) : 0;

		// Model usage distribution
		$raw_models = $this->repository->get_model_usage( $start_utc, $end_utc );
		$models     = [];
		$total_model_msgs = array_sum( array_column( $raw_models, 'count' ) );
		foreach ( $raw_models as $m ) {
			$m_count = (int) $m['count'];
			$m_pct   = $total_model_msgs > 0 ? round( ( $m_count / $total_model_msgs ) * 100, 1 ) : 0.0;
			$models[] = [
				'model'      => (string) $m['model'],
				'count'      => $m_count,
				'percentage' => $m_pct,
			];
		}

		// Time series continuous dataset
		$daily_convs = $this->repository->get_daily_conversations( $start_utc, $end_utc );
		$daily_msgs  = $this->repository->get_daily_messages( $start_utc, $end_utc );
		$timeline    = $this->build_continuous_timeline(
			$range_info['start_local'],
			$range_info['end_local'],
			$daily_convs,
			$daily_msgs,
			$range_info['is_continuous']
		);

		return [
			'range_info' => $range_info,
			'kpis'       => [
				'total_conversations'       => $total_conv,
				'unique_sessions'           => $kpis['unique_sessions'],
				'conversations_today'       => $today_count,
				'total_messages'            => $total_msg,
				'user_messages'             => $kpis['user_messages'],
				'assistant_messages'        => $kpis['assistant_messages'],
				'avg_messages_per_conv'     => $avg_msg_per_conv,
				'total_tokens'              => $total_tokens,
				'input_tokens'              => $kpis['total_input_tokens'],
				'output_tokens'             => $kpis['total_output_tokens'],
				'avg_latency_ms'            => $avg_latency,
				'formatted_latency'         => $formatted_latency,
				'active_conversations'      => $active_conv,
				'closed_conversations'      => $closed_conv,
				'active_percentage'         => $active_pct,
				'closed_percentage'         => $closed_pct,
				'guest_conversations'       => $guest_conv,
				'user_conversations'        => $user_conv,
				'guest_percentage'          => $guest_pct,
				'user_percentage'           => $user_pct,
			],
			'models'     => $models,
			'timeline'   => $timeline,
		];
	}

	/**
	 * Builds a continuous day-by-day timeline ensuring every date has a row.
	 *
	 * @param string|null $start_local Local start date/time ('Y-m-d H:i:s').
	 * @param string|null $end_local   Local end date/time ('Y-m-d H:i:s').
	 * @param array       $daily_convs DB daily conversations.
	 * @param array       $daily_msgs  DB daily messages.
	 * @param bool        $continuous  Whether to fill intermediate missing days.
	 * @return array<int, array{date: string, label: string, conversations: int, messages: int}>
	 */
	private function build_continuous_timeline(
		?string $start_local,
		?string $end_local,
		array $daily_convs,
		array $daily_msgs,
		bool $continuous
	): array {
		$conv_map = [];
		foreach ( $daily_convs as $row ) {
			$conv_map[ $row['date_bucket'] ] = (int) $row['count'];
		}

		$msg_map = [];
		foreach ( $daily_msgs as $row ) {
			$msg_map[ $row['date_bucket'] ] = (int) $row['count'];
		}

		if ( ! $continuous || empty( $start_local ) || empty( $end_local ) ) {
			// For All Time or unbound queries, merge existing keys
			$all_dates = array_unique( array_merge( array_keys( $conv_map ), array_keys( $msg_map ) ) );
			sort( $all_dates );

			$timeline = [];
			foreach ( $all_dates as $date_str ) {
				$ts = strtotime( $date_str );
				$timeline[] = [
					'date'          => $date_str,
					'label'         => $ts ? date( 'M j', $ts ) : $date_str,
					'conversations' => $conv_map[ $date_str ] ?? 0,
					'messages'      => $msg_map[ $date_str ] ?? 0,
				];
			}
			return $timeline;
		}

		$start_ts = strtotime( substr( $start_local, 0, 10 ) );
		$end_ts   = strtotime( substr( $end_local, 0, 10 ) );

		if ( ! $start_ts || ! $end_ts || $start_ts > $end_ts ) {
			return [];
		}

		$timeline = [];
		$curr_ts  = $start_ts;
		$max_days = 120; // safety ceiling
		$day_idx  = 0;

		while ( $curr_ts <= $end_ts && $day_idx < $max_days ) {
			$date_str = date( 'Y-m-d', $curr_ts );
			$timeline[] = [
				'date'          => $date_str,
				'label'         => date( 'M j', $curr_ts ),
				'conversations' => $conv_map[ $date_str ] ?? 0,
				'messages'      => $msg_map[ $date_str ] ?? 0,
			];
			$curr_ts = strtotime( '+1 day', $curr_ts );
			$day_idx++;
		}

		return $timeline;
	}
}
