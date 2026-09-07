<?php
/**
 * Admin Human Handoffs List Template.
 *
 * @package SkyFish\GeminiChat\Templates
 */

use SkyFish\GeminiChat\Admin\AdminMenu;
use SkyFish\GeminiChat\Handoff\HandoffService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array<int, array<string, mixed>> $handoffs
 * @var int                              $total_items
 * @var int                              $total_pages
 * @var int                              $current_page
 * @var string                           $current_status
 * @var string                           $current_reason
 * @var string                           $search_term
 * @var string                           $base_url
 */

$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
?>
<div class="wrap gca-admin-wrap">
	<!-- Header -->
	<header class="gca-admin-header">
		<div class="gca-admin-header__info">
			<div class="gca-admin-header__icon" aria-hidden="true">
				<span class="dashicons dashicons-businesswoman"></span>
			</div>
			<div>
				<h1 class="gca-admin-header__title">
					<?php esc_html_e( 'Human Handoff Requests', 'gemini-chat-assistant' ); ?>
					<span class="gca-admin-badge"><?php echo esc_html( number_format_i18n( $total_items ) ); ?></span>
				</h1>
				<p class="gca-admin-header__subtitle">
					<?php esc_html_e( 'Manage escalation requests from visitors who asked for a person or required human assistance.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>
		</div>
	</header>

	<!-- Notices -->
	<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible gca-admin-notice">
			<p><?php esc_html_e( 'Handoff status successfully updated.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php elseif ( ! empty( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible gca-admin-notice">
			<p><?php esc_html_e( 'Handoff record permanently deleted.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Filter & Search Bar -->
	<div class="gca-admin-card gca-admin-filter-bar">
		<div class="gca-admin-filter-bar__views">
			<ul class="subsubsub" style="margin: 0;">
				<li>
					<a href="<?php echo esc_url( add_query_arg( [ 'status' => 'all', 'paged' => 1 ], $base_url ) ); ?>" class="<?php echo 'all' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'All', 'gemini-chat-assistant' ); ?>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( [ 'status' => 'pending', 'paged' => 1 ], $base_url ) ); ?>" class="<?php echo 'pending' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Pending', 'gemini-chat-assistant' ); ?>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( [ 'status' => 'assigned', 'paged' => 1 ], $base_url ) ); ?>" class="<?php echo 'assigned' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Assigned', 'gemini-chat-assistant' ); ?>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( [ 'status' => 'resolved', 'paged' => 1 ], $base_url ) ); ?>" class="<?php echo 'resolved' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Resolved', 'gemini-chat-assistant' ); ?>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( [ 'status' => 'cancelled', 'paged' => 1 ], $base_url ) ); ?>" class="<?php echo 'cancelled' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Cancelled', 'gemini-chat-assistant' ); ?>
					</a>
				</li>
			</ul>
		</div>

		<form method="get" class="gca-admin-filter-bar__search">
			<input type="hidden" name="page" value="<?php echo esc_attr( AdminMenu::HANDOFFS_MENU_SLUG ); ?>" />
			<?php if ( 'all' !== $current_status ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>" />
			<?php endif; ?>
			<label for="gca-handoff-search" class="screen-reader-text"><?php esc_html_e( 'Search handoffs', 'gemini-chat-assistant' ); ?></label>
			<input
				type="search"
				id="gca-handoff-search"
				name="s"
				value="<?php echo esc_attr( $search_term ); ?>"
				placeholder="<?php esc_attr_e( 'Search by UUID, name, email...', 'gemini-chat-assistant' ); ?>"
				class="gca-admin-search-input"
			/>
			<button type="submit" class="button">
				<span class="dashicons dashicons-search" style="vertical-align: middle;"></span>
				<?php esc_html_e( 'Search', 'gemini-chat-assistant' ); ?>
			</button>
			<?php if ( ! empty( $search_term ) ) : ?>
				<a href="<?php echo esc_url( add_query_arg( [ 'status' => $current_status ], $base_url ) ); ?>" class="button">
					<?php esc_html_e( 'Clear', 'gemini-chat-assistant' ); ?>
				</a>
			<?php endif; ?>
		</form>
	</div>

	<!-- Handoffs Table -->
	<div class="gca-admin-card" style="padding: 0; overflow: hidden;">
		<?php if ( empty( $handoffs ) ) : ?>
			<div class="gca-admin-empty-state">
				<span class="dashicons dashicons-businesswoman"></span>
				<h3><?php esc_html_e( 'No handoff requests found', 'gemini-chat-assistant' ); ?></h3>
				<p>
					<?php if ( ! empty( $search_term ) || 'all' !== $current_status ) : ?>
						<?php esc_html_e( 'No requests matched your filter criteria.', 'gemini-chat-assistant' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'When visitors ask to speak with a human or escalate their chat, requests will appear here.', 'gemini-chat-assistant' ); ?>
					<?php endif; ?>
				</p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped table-view-list gca-admin-table">
				<thead>
					<tr>
						<th scope="col" style="width: 18%;"><?php esc_html_e( 'Request UUID', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 12%;"><?php esc_html_e( 'Status', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 18%;"><?php esc_html_e( 'Reason', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 20%;"><?php esc_html_e( 'Lead / Contact', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 18%;"><?php esc_html_e( 'Conversation', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 14%;"><?php esc_html_e( 'Date', 'gemini-chat-assistant' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $handoffs as $row ) : ?>
						<?php
						$h_id         = (string) $row['public_id'];
						$detail_url   = add_query_arg( [ 'handoff_id' => $h_id ], $base_url );
						$status       = (string) $row['status'];
						$reason       = (string) $row['reason'];
						$status_label = HandoffService::get_status_label( $status );
						$reason_label = HandoffService::get_reason_label( $reason );

						$pill_class = 'gca-admin-pill--info';
						if ( 'pending' === $status ) {
							$pill_class = 'gca-admin-pill--warning';
						} elseif ( 'assigned' === $status ) {
							$pill_class = 'gca-admin-pill--primary';
						} elseif ( 'resolved' === $status ) {
							$pill_class = 'gca-admin-pill--success';
						} elseif ( 'cancelled' === $status ) {
							$pill_class = 'gca-admin-pill--muted';
						}
						?>
						<tr>
							<td>
								<strong>
									<a href="<?php echo esc_url( $detail_url ); ?>" class="row-title" title="<?php esc_attr_e( 'Inspect Handoff Details', 'gemini-chat-assistant' ); ?>">
										<code><?php echo esc_html( substr( $h_id, 0, 16 ) . '...' ); ?></code>
									</a>
								</strong>
								<div class="row-actions">
									<span class="view">
										<a href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'View Details', 'gemini-chat-assistant' ); ?></a> |
									</span>
									<span class="trash">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e( 'Permanently delete this handoff record?', 'gemini-chat-assistant' ); ?>');">
											<input type="hidden" name="action" value="gca_delete_handoff" />
											<input type="hidden" name="handoff_id" value="<?php echo esc_attr( $h_id ); ?>" />
											<?php wp_nonce_field( 'gca_delete_handoff_' . $h_id ); ?>
											<button type="submit" class="button-link submitdelete" style="color: #b32d2e; cursor: pointer;"><?php esc_html_e( 'Delete', 'gemini-chat-assistant' ); ?></button>
										</form>
									</span>
								</div>
							</td>
							<td>
								<span class="gca-admin-pill <?php echo esc_attr( $pill_class ); ?>">
									<?php echo esc_html( $status_label ); ?>
								</span>
							</td>
							<td>
								<span class="gca-admin-pill gca-admin-pill--neutral">
									<?php echo esc_html( $reason_label ); ?>
								</span>
							</td>
							<td>
								<?php if ( ! empty( $row['lead_public_id'] ) ) : ?>
									<?php
									$lead_url = add_query_arg(
										[
											'page'    => AdminMenu::LEADS_MENU_SLUG,
											'lead_id' => $row['lead_public_id'],
										],
										admin_url( 'admin.php' )
									);
									?>
									<strong><a href="<?php echo esc_url( $lead_url ); ?>"><?php echo esc_html( ! empty( $row['lead_name'] ) ? $row['lead_name'] : __( 'Lead Captured', 'gemini-chat-assistant' ) ); ?></a></strong>
									<?php if ( ! empty( $row['lead_email'] ) ) : ?>
										<br><small style="color: #646970;"><?php echo esc_html( $row['lead_email'] ); ?></small>
									<?php endif; ?>
								<?php else : ?>
									<span style="color: #8c8f94;"><?php esc_html_e( 'Anonymous Visitor', 'gemini-chat-assistant' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $row['conversation_public_id'] ) ) : ?>
									<?php
									$conv_url = add_query_arg(
										[
											'page'            => AdminMenu::CONVERSATIONS_MENU_SLUG,
											'conversation_id' => $row['conversation_public_id'],
										],
										admin_url( 'admin.php' )
									);
									?>
									<a href="<?php echo esc_url( $conv_url ); ?>" title="<?php esc_attr_e( 'View Conversation Transcript', 'gemini-chat-assistant' ); ?>">
										<code><?php echo esc_html( substr( (string) $row['conversation_public_id'], 0, 12 ) . '...' ); ?></code>
									</a>
								<?php else : ?>
									<span style="color: #8c8f94;">#<?php echo esc_html( (string) $row['conversation_id'] ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								$created = ! empty( $row['created_at'] ) ? strtotime( $row['created_at'] ) : false;
								echo $created ? esc_html( date_i18n( $date_format, $created ) ) : '—';
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom" style="padding: 10px 16px;">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php printf( esc_html( _n( '%s item', '%s items', $total_items, 'gemini-chat-assistant' ) ), esc_html( number_format_i18n( $total_items ) ) ); ?>
						</span>
						<span class="pagination-links">
							<?php
							echo paginate_links( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								'base'      => add_query_arg( 'paged', '%#%', $base_url ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $current_page,
							] );
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
