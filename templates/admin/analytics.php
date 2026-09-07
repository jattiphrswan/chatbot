<?php
/**
 * Admin Analytics & Insights Template.
 *
 * @package SkyFish\GeminiChat\Templates
 */

use SkyFish\GeminiChat\Admin\AdminMenu;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array<string, mixed> $analytics
 * @var bool                 $store_messages
 * @var string               $base_url
 * @var string               $conversations_url
 */

$range_info = $analytics['range_info'];
$kpis       = $analytics['kpis'];
$models     = $analytics['models'];
$timeline   = $analytics['timeline'];

$current_range = $range_info['range'];
$total_conv    = (int) $kpis['total_conversations'];
?>
<div class="wrap gca-admin-wrap">
	<!-- Admin Header -->
	<header class="gca-admin-header">
		<div class="gca-admin-header__info">
			<div class="gca-admin-header__icon" aria-hidden="true">
				<span class="dashicons dashicons-chart-bar"></span>
			</div>
			<div>
				<h1 class="gca-admin-header__title">
					<?php esc_html_e( 'Analytics & Insights', 'gemini-chat-assistant' ); ?>
					<span class="gca-admin-badge"><?php echo esc_html( $range_info['label'] ); ?></span>
				</h1>
				<p class="gca-admin-header__subtitle">
					<?php esc_html_e( 'Operational metrics, conversation volume, visitor engagement, and token utilization.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>
		</div>
		<div class="gca-admin-header__actions">
			<a href="<?php echo esc_url( $conversations_url ); ?>" class="button">
				<span class="dashicons dashicons-format-chat" style="vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'View Conversations', 'gemini-chat-assistant' ); ?>
			</a>
		</div>
	</header>

	<!-- Date Range Filter Toolbar -->
	<div class="gca-admin-toolbar">
		<ul class="subsubsub gca-admin-subsub">
			<li>
				<a href="<?php echo esc_url( add_query_arg( [ 'range' => 'today' ], $base_url ) ); ?>" class="<?php echo 'today' === $current_range ? 'current' : ''; ?>">
					<?php esc_html_e( 'Today', 'gemini-chat-assistant' ); ?>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( [ 'range' => '7days' ], $base_url ) ); ?>" class="<?php echo '7days' === $current_range ? 'current' : ''; ?>">
					<?php esc_html_e( 'Last 7 Days', 'gemini-chat-assistant' ); ?>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( [ 'range' => '30days' ], $base_url ) ); ?>" class="<?php echo '30days' === $current_range ? 'current' : ''; ?>">
					<?php esc_html_e( 'Last 30 Days', 'gemini-chat-assistant' ); ?>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( [ 'range' => '90days' ], $base_url ) ); ?>" class="<?php echo '90days' === $current_range ? 'current' : ''; ?>">
					<?php esc_html_e( 'Last 90 Days', 'gemini-chat-assistant' ); ?>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( [ 'range' => 'all' ], $base_url ) ); ?>" class="<?php echo 'all' === $current_range ? 'current' : ''; ?>">
					<?php esc_html_e( 'All Time', 'gemini-chat-assistant' ); ?>
				</a>
			</li>
		</ul>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="gca-admin-search-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( AdminMenu::ANALYTICS_MENU_SLUG ); ?>" />
			<input type="hidden" name="range" value="custom" />
			<label for="gca-custom-from" class="screen-reader-text"><?php esc_html_e( 'From Date', 'gemini-chat-assistant' ); ?></label>
			<input
				type="date"
				id="gca-custom-from"
				name="from"
				value="<?php echo ! empty( $_GET['from'] ) ? esc_attr( (string) $_GET['from'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"
				class="gca-admin-search-input"
				style="min-width: 140px;"
				required
			/>
			<span style="color: #646970;">&rarr;</span>
			<label for="gca-custom-to" class="screen-reader-text"><?php esc_html_e( 'To Date', 'gemini-chat-assistant' ); ?></label>
			<input
				type="date"
				id="gca-custom-to"
				name="to"
				value="<?php echo ! empty( $_GET['to'] ) ? esc_attr( (string) $_GET['to'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"
				class="gca-admin-search-input"
				style="min-width: 140px;"
				required
			/>
			<button type="submit" class="button">
				<?php esc_html_e( 'Filter', 'gemini-chat-assistant' ); ?>
			</button>
		</form>
	</div>

	<!-- Privacy Notice if store_messages is disabled -->
	<?php if ( ! $store_messages ) : ?>
		<div class="notice notice-info gca-admin-notice inline">
			<p>
				<strong><?php esc_html_e( 'Privacy Notice:', 'gemini-chat-assistant' ); ?></strong>
				<?php esc_html_e( 'Message persistence is currently disabled in plugin settings. Message turn counts and token analytics reflect stored data and may be limited.', 'gemini-chat-assistant' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<!-- KPI Summary Cards Grid -->
	<section class="gca-admin-section" aria-labelledby="gca-heading-kpis">
		<h2 id="gca-heading-kpis" class="screen-reader-text"><?php esc_html_e( 'Key Performance Indicators', 'gemini-chat-assistant' ); ?></h2>
		<div class="gca-admin-grid gca-admin-grid--3" style="margin-bottom: 20px;">
			<!-- KPI 1: Total Conversations -->
			<div class="gca-admin-card gca-admin-kpi-card">
				<div class="gca-admin-card__header">
					<span class="gca-admin-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-format-chat"></span>
					</span>
					<span class="gca-admin-pill gca-admin-pill--info">
						<?php printf( esc_html__( '%s Unique', 'gemini-chat-assistant' ), number_format_i18n( (int) $kpis['unique_sessions'] ) ); ?>
					</span>
				</div>
				<span class="gca-admin-kpi-value"><?php echo esc_html( number_format_i18n( $total_conv ) ); ?></span>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Total Conversations', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php printf( esc_html__( '%s unique visitor sessions in this period.', 'gemini-chat-assistant' ), number_format_i18n( (int) $kpis['unique_sessions'] ) ); ?>
				</p>
			</div>

			<!-- KPI 2: Stored Messages -->
			<div class="gca-admin-card gca-admin-kpi-card">
				<div class="gca-admin-card__header">
					<span class="gca-admin-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-admin-comments"></span>
					</span>
					<span class="gca-admin-pill gca-admin-pill--success">
						<?php printf( esc_html__( '%1.1f / conv', 'gemini-chat-assistant' ), (float) $kpis['avg_messages_per_conv'] ); ?>
					</span>
				</div>
				<span class="gca-admin-kpi-value"><?php echo esc_html( number_format_i18n( (int) $kpis['total_messages'] ) ); ?></span>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Stored Messages', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php printf( esc_html__( '%1.1f average turns per conversation session.', 'gemini-chat-assistant' ), (float) $kpis['avg_messages_per_conv'] ); ?>
				</p>
			</div>

			<!-- KPI 3: Conversations Today -->
			<div class="gca-admin-card gca-admin-kpi-card">
				<div class="gca-admin-card__header">
					<span class="gca-admin-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-clock"></span>
					</span>
					<span class="gca-admin-pill gca-admin-pill--warning">
						<?php esc_html_e( 'Today', 'gemini-chat-assistant' ); ?>
					</span>
				</div>
				<span class="gca-admin-kpi-value"><?php echo esc_html( number_format_i18n( (int) $kpis['conversations_today'] ) ); ?></span>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Conversations Today', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php esc_html_e( 'Real-time sessions initiated since local midnight.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>

			<!-- KPI 4: Total Token Usage -->
			<div class="gca-admin-card gca-admin-kpi-card">
				<div class="gca-admin-card__header">
					<span class="gca-admin-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-database"></span>
					</span>
					<span class="gca-admin-pill gca-admin-pill--info">
						<?php esc_html_e( 'Tokens', 'gemini-chat-assistant' ); ?>
					</span>
				</div>
				<span class="gca-admin-kpi-value"><?php echo esc_html( number_format_i18n( (int) $kpis['total_tokens'] ) ); ?></span>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Total Tokens Processed', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php printf( esc_html__( '%1$s in / %2$s out', 'gemini-chat-assistant' ), number_format_i18n( (int) $kpis['input_tokens'] ), number_format_i18n( (int) $kpis['output_tokens'] ) ); ?>
				</p>
			</div>

			<!-- KPI 5: Avg Response Latency -->
			<div class="gca-admin-card gca-admin-kpi-card">
				<div class="gca-admin-card__header">
					<span class="gca-admin-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-performance"></span>
					</span>
					<span class="gca-admin-pill gca-admin-pill--success">
						<?php esc_html_e( 'Speed', 'gemini-chat-assistant' ); ?>
					</span>
				</div>
				<span class="gca-admin-kpi-value"><?php echo esc_html( (string) $kpis['formatted_latency'] ); ?></span>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Avg Response Time', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php esc_html_e( 'Average latency for AI assistant turn generation.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>

			<!-- KPI 6: Active vs Closed Rate -->
			<div class="gca-admin-card gca-admin-kpi-card">
				<div class="gca-admin-card__header">
					<span class="gca-admin-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-yes-alt"></span>
					</span>
					<span class="gca-admin-pill gca-admin-pill--success">
						<?php printf( esc_html__( '%d%% Active', 'gemini-chat-assistant' ), (int) $kpis['active_percentage'] ); ?>
					</span>
				</div>
				<span class="gca-admin-kpi-value"><?php echo esc_html( number_format_i18n( (int) $kpis['active_conversations'] ) ); ?></span>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Active Sessions', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php printf( esc_html__( '%s closed sessions archived.', 'gemini-chat-assistant' ), number_format_i18n( (int) $kpis['closed_conversations'] ) ); ?>
				</p>
			</div>
		</div>
	</section>

	<!-- Chart & Activity Trend Section -->
	<section class="gca-admin-card gca-admin-chart-card" aria-labelledby="gca-heading-trend">
		<div class="gca-admin-chart-card__header">
			<div>
				<h2 id="gca-heading-trend" class="gca-admin-card__section-title" style="margin-bottom: 4px; border: none;">
					<span class="dashicons dashicons-chart-line" aria-hidden="true"></span>
					<?php esc_html_e( 'Daily Activity Trends', 'gemini-chat-assistant' ); ?>
				</h2>
				<p class="gca-admin-section__desc" style="margin: 0;">
					<?php esc_html_e( 'Conversations initiated and stored turns over the selected timeframe.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>
			<div class="gca-admin-chart-legend">
				<span class="gca-admin-legend-item">
					<span class="gca-admin-legend-bullet gca-admin-legend-bullet--conv"></span>
					<?php esc_html_e( 'Conversations', 'gemini-chat-assistant' ); ?>
				</span>
				<span class="gca-admin-legend-item">
					<span class="gca-admin-legend-bullet gca-admin-legend-bullet--msg"></span>
					<?php esc_html_e( 'Stored Messages', 'gemini-chat-assistant' ); ?>
				</span>
			</div>
		</div>

		<?php if ( ! empty( $timeline ) && $total_conv > 0 ) :
			$max_val = 1;
			foreach ( $timeline as $pt ) {
				$max_val = max( $max_val, (int) $pt['conversations'], (int) $pt['messages'] );
			}
			$chart_height = 180;
		?>
			<!-- Native Lightweight Responsive SVG Bar Visualization -->
			<div class="gca-admin-svg-chart-wrap" role="img" aria-label="<?php esc_attr_e( 'Daily conversation and message volume bar chart', 'gemini-chat-assistant' ); ?>">
				<div class="gca-admin-bars-container">
					<?php foreach ( $timeline as $item ) :
						$c_val = (int) $item['conversations'];
						$m_val = (int) $item['messages'];
						$c_height = (int) round( ( $c_val / $max_val ) * $chart_height );
						$m_height = (int) round( ( $m_val / $max_val ) * $chart_height );
					?>
						<div class="gca-admin-bar-group" title="<?php echo esc_attr( sprintf( __( '%1$s: %2$d conversations, %3$d messages', 'gemini-chat-assistant' ), $item['label'], $c_val, $m_val ) ); ?>">
							<div class="gca-admin-bars">
								<div class="gca-admin-bar gca-admin-bar--conv" style="height: <?php echo esc_attr( (string) max( 2, $c_height ) ); ?>px;">
									<?php if ( $c_val > 0 ) : ?>
										<span class="gca-admin-bar__tooltip"><?php echo esc_html( (string) $c_val ); ?></span>
									<?php endif; ?>
								</div>
								<div class="gca-admin-bar gca-admin-bar--msg" style="height: <?php echo esc_attr( (string) max( 2, $m_height ) ); ?>px;">
									<?php if ( $m_val > 0 ) : ?>
										<span class="gca-admin-bar__tooltip"><?php echo esc_html( (string) $m_val ); ?></span>
									<?php endif; ?>
								</div>
							</div>
							<span class="gca-admin-bar-label"><?php echo esc_html( $item['label'] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Accessible HTML Table representation -->
			<details class="gca-admin-accessible-details" style="margin-top: 16px;">
				<summary style="cursor: pointer; font-size: 12px; color: #646970;">
					<?php esc_html_e( 'View accessible data table', 'gemini-chat-assistant' ); ?>
				</summary>
				<table class="widefat striped" style="margin-top: 8px;" aria-label="<?php esc_attr_e( 'Daily metrics table', 'gemini-chat-assistant' ); ?>">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Date', 'gemini-chat-assistant' ); ?></th>
							<th scope="col" style="text-align: right;"><?php esc_html_e( 'Conversations', 'gemini-chat-assistant' ); ?></th>
							<th scope="col" style="text-align: right;"><?php esc_html_e( 'Messages', 'gemini-chat-assistant' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $timeline as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['date'] ); ?> (<?php echo esc_html( $row['label'] ); ?>)</td>
								<td style="text-align: right;"><?php echo esc_html( number_format_i18n( (int) $row['conversations'] ) ); ?></td>
								<td style="text-align: right;"><?php echo esc_html( number_format_i18n( (int) $row['messages'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</details>

		<?php else : ?>
			<div class="gca-admin-empty-state" style="padding: 32px 16px;">
				<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
				<h3><?php esc_html_e( 'No activity recorded for this date range', 'gemini-chat-assistant' ); ?></h3>
				<p><?php esc_html_e( 'When visitors interact with the chat assistant, volume and message trends will be charted here.', 'gemini-chat-assistant' ); ?></p>
			</div>
		<?php endif; ?>
	</section>

	<!-- Breakdowns Section (Audience, Status, AI Models) -->
	<div class="gca-admin-grid gca-admin-grid--3" style="margin-top: 24px;">
		<!-- Status Breakdown -->
		<div class="gca-admin-card">
			<h3 class="gca-admin-card__section-title">
				<span class="dashicons dashicons-flag" aria-hidden="true"></span>
				<?php esc_html_e( 'Status Breakdown', 'gemini-chat-assistant' ); ?>
			</h3>
			<div class="gca-admin-breakdown-list">
				<div class="gca-admin-breakdown-item">
					<div class="gca-admin-breakdown-meta">
						<strong><?php esc_html_e( 'Active Sessions', 'gemini-chat-assistant' ); ?></strong>
						<span><?php printf( esc_html__( '%1$s (%2$d%%)', 'gemini-chat-assistant' ), number_format_i18n( (int) $kpis['active_conversations'] ), (int) $kpis['active_percentage'] ); ?></span>
					</div>
					<div class="gca-admin-progress-bar">
						<div class="gca-admin-progress-fill gca-admin-progress-fill--success" style="width: <?php echo esc_attr( (string) $kpis['active_percentage'] ); ?>%;"></div>
					</div>
				</div>

				<div class="gca-admin-breakdown-item">
					<div class="gca-admin-breakdown-meta">
						<strong><?php esc_html_e( 'Closed Sessions', 'gemini-chat-assistant' ); ?></strong>
						<span><?php printf( esc_html__( '%1$s (%2$d%%)', 'gemini-chat-assistant' ), number_format_i18n( (int) $kpis['closed_conversations'] ), (int) $kpis['closed_percentage'] ); ?></span>
					</div>
					<div class="gca-admin-progress-bar">
						<div class="gca-admin-progress-fill gca-admin-progress-fill--muted" style="width: <?php echo esc_attr( (string) $kpis['closed_percentage'] ); ?>%;"></div>
					</div>
				</div>
			</div>
		</div>

		<!-- Audience Breakdown -->
		<div class="gca-admin-card">
			<h3 class="gca-admin-card__section-title">
				<span class="dashicons dashicons-groups" aria-hidden="true"></span>
				<?php esc_html_e( 'Audience Type', 'gemini-chat-assistant' ); ?>
			</h3>
			<div class="gca-admin-breakdown-list">
				<div class="gca-admin-breakdown-item">
					<div class="gca-admin-breakdown-meta">
						<strong><?php esc_html_e( 'Guest Visitors', 'gemini-chat-assistant' ); ?></strong>
						<span><?php printf( esc_html__( '%1$s (%2$d%%)', 'gemini-chat-assistant' ), number_format_i18n( (int) $kpis['guest_conversations'] ), (int) $kpis['guest_percentage'] ); ?></span>
					</div>
					<div class="gca-admin-progress-bar">
						<div class="gca-admin-progress-fill gca-admin-progress-fill--info" style="width: <?php echo esc_attr( (string) $kpis['guest_percentage'] ); ?>%;"></div>
					</div>
				</div>

				<div class="gca-admin-breakdown-item">
					<div class="gca-admin-breakdown-meta">
						<strong><?php esc_html_e( 'Logged-In Users', 'gemini-chat-assistant' ); ?></strong>
						<span><?php printf( esc_html__( '%1$s (%2$d%%)', 'gemini-chat-assistant' ), number_format_i18n( (int) $kpis['user_conversations'] ), (int) $kpis['user_percentage'] ); ?></span>
					</div>
					<div class="gca-admin-progress-bar">
						<div class="gca-admin-progress-fill gca-admin-progress-fill--purple" style="width: <?php echo esc_attr( (string) $kpis['user_percentage'] ); ?>%;"></div>
					</div>
				</div>
			</div>
		</div>

		<!-- AI Model Usage -->
		<div class="gca-admin-card">
			<h3 class="gca-admin-card__section-title">
				<span class="dashicons dashicons-superhero" aria-hidden="true"></span>
				<?php esc_html_e( 'AI Model Distribution', 'gemini-chat-assistant' ); ?>
			</h3>
			<?php if ( ! empty( $models ) ) : ?>
				<div class="gca-admin-breakdown-list">
					<?php foreach ( $models as $m ) : ?>
						<div class="gca-admin-breakdown-item">
							<div class="gca-admin-breakdown-meta">
								<code><?php echo esc_html( $m['model'] ); ?></code>
								<span><?php printf( esc_html__( '%1$s (%2$1.1f%%)', 'gemini-chat-assistant' ), number_format_i18n( (int) $m['count'] ), (float) $m['percentage'] ); ?></span>
							</div>
							<div class="gca-admin-progress-bar">
								<div class="gca-admin-progress-fill gca-admin-progress-fill--primary" style="width: <?php echo esc_attr( (string) $m['percentage'] ); ?>%;"></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="gca-admin-card__desc" style="margin-top: 10px;">
					<?php esc_html_e( 'No stored assistant turns for this period.', 'gemini-chat-assistant' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</div>
