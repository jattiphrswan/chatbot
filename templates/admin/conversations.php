<?php
/**
 * Admin Conversations List Template.
 *
 * @package SkyFish\GeminiChat\Templates
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array<int, array<string, mixed>> $conversations
 * @var int                              $total_items
 * @var int                              $current_page
 * @var int                              $per_page
 * @var int                              $total_pages
 * @var string                           $current_status
 * @var string                           $search_term
 * @var string                           $base_url
 */

$date_format = get_option( 'date_format', 'Y-m-d' );
$time_format = get_option( 'time_format', 'H:i' );
?>
<div class="wrap gca-admin-wrap">
	<!-- Header -->
	<header class="gca-admin-header">
		<div class="gca-admin-header__info">
			<div class="gca-admin-header__icon" aria-hidden="true">
				<span class="dashicons dashicons-format-chat"></span>
			</div>
			<div>
				<h1 class="gca-admin-header__title">
					<?php esc_html_e( 'Conversations Management', 'gemini-chat-assistant' ); ?>
					<span class="gca-admin-badge"><?php echo esc_html( (string) $total_items ); ?></span>
				</h1>
				<p class="gca-admin-header__subtitle">
					<?php esc_html_e( 'Browse, inspect, and manage visitor chat sessions and stored transcripts.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>
		</div>
	</header>

	<!-- Action Feedback Notices -->
	<?php if ( ! empty( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible gca-admin-notice">
			<p><?php esc_html_e( 'Conversation and associated messages permanently deleted.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php elseif ( ! empty( $_GET['closed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible gca-admin-notice">
			<p><?php esc_html_e( 'Conversation status updated to Closed.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php elseif ( ! empty( $_GET['reopened'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible gca-admin-notice">
			<p><?php esc_html_e( 'Conversation status updated to Active.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Filter Bar & Search -->
	<div class="gca-admin-toolbar">
		<ul class="subsubsub gca-admin-subsub">
			<li>
				<a href="<?php echo esc_url( remove_query_arg( [ 'status', 'paged' ], $base_url ) ); ?>" class="<?php echo empty( $current_status ) || 'all' === $current_status ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'gemini-chat-assistant' ); ?>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( [ 'status' => 'active', 'paged' => 1 ], $base_url ) ); ?>" class="<?php echo 'active' === $current_status ? 'current' : ''; ?>">
					<?php esc_html_e( 'Active', 'gemini-chat-assistant' ); ?>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( [ 'status' => 'closed', 'paged' => 1 ], $base_url ) ); ?>" class="<?php echo 'closed' === $current_status ? 'current' : ''; ?>">
					<?php esc_html_e( 'Closed', 'gemini-chat-assistant' ); ?>
				</a>
			</li>
		</ul>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="gca-admin-search-form">
			<input type="hidden" name="page" value="gca-conversations" />
			<?php if ( ! empty( $current_status ) && 'all' !== $current_status ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>" />
			<?php endif; ?>
			<input
				type="search"
				name="s"
				value="<?php echo esc_attr( $search_term ); ?>"
				placeholder="<?php esc_attr_e( 'Search by UUID or Title...', 'gemini-chat-assistant' ); ?>"
				class="gca-admin-search-input"
			/>
			<button type="submit" class="button">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<?php esc_html_e( 'Search', 'gemini-chat-assistant' ); ?>
			</button>
			<?php if ( ! empty( $search_term ) ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( [ 's', 'paged' ], $base_url ) ); ?>" class="button">
					<?php esc_html_e( 'Clear', 'gemini-chat-assistant' ); ?>
				</a>
			<?php endif; ?>
		</form>
	</div>

	<!-- Conversations Table -->
	<div class="gca-admin-card gca-admin-table-card">
		<?php if ( ! empty( $conversations ) ) : ?>
			<table class="wp-list-table widefat fixed striped gca-admin-table" aria-label="<?php esc_attr_e( 'Conversations list', 'gemini-chat-assistant' ); ?>">
				<thead>
					<tr>
						<th scope="col" style="width: 220px;"><?php esc_html_e( 'Conversation UUID', 'gemini-chat-assistant' ); ?></th>
						<th scope="col"><?php esc_html_e( 'User', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 110px;"><?php esc_html_e( 'Status', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 90px; text-align: center;"><?php esc_html_e( 'Turns', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 170px;"><?php esc_html_e( 'Started', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 170px;"><?php esc_html_e( 'Last Activity', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 160px; text-align: right;"><?php esc_html_e( 'Actions', 'gemini-chat-assistant' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $conversations as $row ) :
						$pub_id     = (string) $row['public_id'];
						$detail_url = add_query_arg(
							[
								'page'            => 'gca-conversations',
								'conversation_id' => $pub_id,
							],
							admin_url( 'admin.php' )
						);

						$user_id   = absint( $row['user_id'] ?? 0 );
						$user_info = esc_html__( 'Guest', 'gemini-chat-assistant' );
						if ( $user_id > 0 ) {
							$user_obj = get_userdata( $user_id );
							if ( $user_obj ) {
								$user_info = esc_html( $user_obj->display_name ) . ' (' . esc_html( $user_obj->user_login ) . ')';
							}
						}

						$status     = strtolower( (string) $row['status'] );
						$created_ts = strtotime( (string) $row['created_at'] );
						$created_dt = $created_ts ? wp_date( "{$date_format} {$time_format}", $created_ts ) : '—';
						$last_msg   = ! empty( $row['last_message_at'] ) ? (string) $row['last_message_at'] : (string) $row['updated_at'];
						$last_ts    = strtotime( $last_msg );
						$last_dt    = $last_ts ? wp_date( "{$date_format} {$time_format}", $last_ts ) : '—';
					?>
						<tr>
							<td class="gca-admin-col-id">
								<a href="<?php echo esc_url( $detail_url ); ?>" class="gca-admin-link-bold" title="<?php echo esc_attr( $pub_id ); ?>">
									<code><?php echo esc_html( substr( $pub_id, 0, 8 ) . '...' ); ?></code>
								</a>
								<?php if ( ! empty( $row['title'] ) ) : ?>
									<span class="gca-admin-title-hint"><?php echo esc_html( (string) $row['title'] ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<span class="gca-admin-user-tag">
									<span class="dashicons <?php echo $user_id > 0 ? 'dashicons-admin-users' : 'dashicons-id'; ?>"></span>
									<?php echo esc_html( $user_info ); ?>
								</span>
							</td>
							<td>
								<span class="gca-admin-pill <?php echo 'active' === $status ? 'gca-admin-pill--success' : 'gca-admin-pill--muted'; ?>">
									<?php echo 'active' === $status ? esc_html__( 'Active', 'gemini-chat-assistant' ) : esc_html__( 'Closed', 'gemini-chat-assistant' ); ?>
								</span>
							</td>
							<td style="text-align: center;">
								<strong><?php echo absint( $row['message_count'] ?? 0 ); ?></strong>
							</td>
							<td><?php echo esc_html( $created_dt ); ?></td>
							<td><?php echo esc_html( $last_dt ); ?></td>
							<td style="text-align: right;">
								<a href="<?php echo esc_url( $detail_url ); ?>" class="button button-small" title="<?php esc_attr_e( 'View message transcript', 'gemini-chat-assistant' ); ?>">
									<?php esc_html_e( 'View', 'gemini-chat-assistant' ); ?>
								</a>

								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block; margin-left: 4px;" onsubmit="return confirm('<?php esc_attr_e( 'Permanently delete this conversation and stored messages?', 'gemini-chat-assistant' ); ?>');">
									<input type="hidden" name="action" value="gca_delete_conversation" />
									<input type="hidden" name="conversation_id" value="<?php echo esc_attr( $pub_id ); ?>" />
									<?php wp_nonce_field( 'gca_delete_conversation_' . $pub_id ); ?>
									<button type="submit" class="button button-small button-link-delete" title="<?php esc_attr_e( 'Delete conversation', 'gemini-chat-assistant' ); ?>">
										<span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Pagination Controls -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="gca-admin-pagination">
					<span class="gca-admin-pagination__info">
						<?php printf( esc_html__( 'Page %1$d of %2$d (%3$d items)', 'gemini-chat-assistant' ), absint( $current_page ), absint( $total_pages ), absint( $total_items ) ); ?>
					</span>
					<div class="gca-admin-pagination__links">
						<?php if ( $current_page > 1 ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ); ?>" class="button button-small">
								&larr; <?php esc_html_e( 'Previous', 'gemini-chat-assistant' ); ?>
							</a>
						<?php endif; ?>

						<?php if ( $current_page < $total_pages ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Next', 'gemini-chat-assistant' ); ?> &rarr;
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

		<?php else : ?>
			<div class="gca-admin-empty-state">
				<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
				<h3><?php esc_html_e( 'No conversations found', 'gemini-chat-assistant' ); ?></h3>
				<p><?php esc_html_e( 'When visitors start chatting with your AI assistant, conversation records will appear here.', 'gemini-chat-assistant' ); ?></p>
				<?php if ( ! empty( $search_term ) || ! empty( $current_status ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gca-conversations' ) ); ?>" class="button">
						<?php esc_html_e( 'View All Conversations', 'gemini-chat-assistant' ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
