<?php
/**
 * Admin Leads List Template.
 *
 * @package SkyFish\GeminiChat\Templates
 */

use SkyFish\GeminiChat\Admin\AdminMenu;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array<int, array<string, mixed>> $leads
 * @var int                              $total_items
 * @var int                              $total_pages
 * @var int                              $current_page
 * @var string                           $current_status
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
				<span class="dashicons dashicons-id-alt"></span>
			</div>
			<div>
				<h1 class="gca-admin-header__title">
					<?php esc_html_e( 'Lead Inquiries', 'gemini-chat-assistant' ); ?>
					<span class="gca-admin-badge"><?php echo esc_html( number_format_i18n( $total_items ) ); ?></span>
				</h1>
				<p class="gca-admin-header__subtitle">
					<?php esc_html_e( 'View, search, and manage contact inquiries captured from the pre-chat form.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>
		</div>
	</header>

	<!-- Notices -->
	<?php if ( ! empty( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible gca-admin-notice">
			<p><?php esc_html_e( 'Lead inquiry successfully deleted.', 'gemini-chat-assistant' ); ?></p>
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
					<a href="<?php echo esc_url( add_query_arg( [ 'status' => 'new', 'paged' => 1 ], $base_url ) ); ?>" class="<?php echo 'new' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'New', 'gemini-chat-assistant' ); ?>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( [ 'status' => 'contacted', 'paged' => 1 ], $base_url ) ); ?>" class="<?php echo 'contacted' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Contacted', 'gemini-chat-assistant' ); ?>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( [ 'status' => 'closed', 'paged' => 1 ], $base_url ) ); ?>" class="<?php echo 'closed' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Closed', 'gemini-chat-assistant' ); ?>
					</a>
				</li>
			</ul>
		</div>

		<form method="get" class="gca-admin-filter-bar__search">
			<input type="hidden" name="page" value="<?php echo esc_attr( AdminMenu::LEADS_MENU_SLUG ); ?>" />
			<?php if ( 'all' !== $current_status ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>" />
			<?php endif; ?>
			<label for="gca-lead-search" class="screen-reader-text"><?php esc_html_e( 'Search leads', 'gemini-chat-assistant' ); ?></label>
			<input
				type="search"
				id="gca-lead-search"
				name="s"
				value="<?php echo esc_attr( $search_term ); ?>"
				placeholder="<?php esc_attr_e( 'Search by name, email, phone...', 'gemini-chat-assistant' ); ?>"
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

	<!-- Leads Table -->
	<div class="gca-admin-card" style="padding: 0; overflow: hidden;">
		<?php if ( empty( $leads ) ) : ?>
			<div class="gca-admin-empty-state">
				<span class="dashicons dashicons-id-alt"></span>
				<h3><?php esc_html_e( 'No lead inquiries found', 'gemini-chat-assistant' ); ?></h3>
				<p>
					<?php if ( ! empty( $search_term ) || 'all' !== $current_status ) : ?>
						<?php esc_html_e( 'No leads matched your filter criteria.', 'gemini-chat-assistant' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'When visitors complete the pre-chat form before chatting, their inquiries will appear here.', 'gemini-chat-assistant' ); ?>
					<?php endif; ?>
				</p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped table-view-list gca-admin-table">
				<thead>
					<tr>
						<th scope="col" style="width: 20%;"><?php esc_html_e( 'Lead / Name', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 22%;"><?php esc_html_e( 'Contact Info', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 24%;"><?php esc_html_e( 'Requirement / Question', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 10%;"><?php esc_html_e( 'Status', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 12%;"><?php esc_html_e( 'Conversation', 'gemini-chat-assistant' ); ?></th>
						<th scope="col" style="width: 12%;"><?php esc_html_e( 'Date', 'gemini-chat-assistant' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $leads as $lead ) : ?>
						<?php
						$lead_id    = (string) $lead['public_id'];
						$detail_url = add_query_arg( [ 'lead_id' => $lead_id ], $base_url );
						$name       = ! empty( $lead['name'] ) ? (string) $lead['name'] : __( 'Anonymous Visitor', 'gemini-chat-assistant' );
						$email      = ! empty( $lead['email'] ) ? (string) $lead['email'] : '';
						$phone      = ! empty( $lead['phone'] ) ? (string) $lead['phone'] : '';
						$req        = ! empty( $lead['requirement'] ) ? (string) $lead['requirement'] : '';
						$status     = (string) ( $lead['status'] ?? 'new' );
						$conv_uuid  = ! empty( $lead['conversation_public_id'] ) ? (string) $lead['conversation_public_id'] : '';
						$created    = ! empty( $lead['created_at'] ) ? get_date_from_gmt( $lead['created_at'], $date_format ) : '—';
						?>
						<tr>
							<!-- Lead Name & Row Actions -->
							<td>
								<strong>
									<a href="<?php echo esc_url( $detail_url ); ?>" class="row-title">
										<?php echo esc_html( $name ); ?>
									</a>
								</strong>
								<div class="row-actions">
									<span class="view">
										<a href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'View Details', 'gemini-chat-assistant' ); ?></a> |
									</span>
									<span class="trash">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to permanently delete this lead inquiry?', 'gemini-chat-assistant' ); ?>');">
											<input type="hidden" name="action" value="gca_delete_lead" />
											<input type="hidden" name="lead_id" value="<?php echo esc_attr( $lead_id ); ?>" />
											<?php wp_nonce_field( 'gca_delete_lead_' . $lead_id ); ?>
											<button type="submit" class="button-link button-link-delete" style="color: #b32d2e; cursor: pointer; padding: 0; font-size: 13px;">
												<?php esc_html_e( 'Delete', 'gemini-chat-assistant' ); ?>
											</button>
										</form>
									</span>
								</div>
							</td>

							<!-- Contact Info -->
							<td>
								<?php if ( ! empty( $email ) ) : ?>
									<div>
										<a href="mailto:<?php echo esc_attr( $email ); ?>" style="display: inline-flex; align-items: center; gap: 4px;">
											<span class="dashicons dashicons-email-alt" style="font-size: 14px; width: 14px; height: 14px; color: #2271b1;"></span>
											<?php echo esc_html( $email ); ?>
										</a>
									</div>
								<?php endif; ?>
								<?php if ( ! empty( $phone ) ) : ?>
									<div style="margin-top: 2px;">
										<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>" style="display: inline-flex; align-items: center; gap: 4px; color: #50575e;">
											<span class="dashicons dashicons-phone" style="font-size: 14px; width: 14px; height: 14px;"></span>
											<?php echo esc_html( $phone ); ?>
										</a>
									</div>
								<?php endif; ?>
								<?php if ( empty( $email ) && empty( $phone ) ) : ?>
									<span style="color: #8c8f94;">—</span>
								<?php endif; ?>
							</td>

							<!-- Requirement Preview -->
							<td>
								<?php if ( ! empty( $req ) ) : ?>
									<span title="<?php echo esc_attr( $req ); ?>">
										<?php echo esc_html( mb_substr( $req, 0, 90 ) . ( mb_strlen( $req ) > 90 ? '…' : '' ) ); ?>
									</span>
								<?php else : ?>
									<span style="color: #8c8f94;">—</span>
								<?php endif; ?>
							</td>

							<!-- Status Pill -->
							<td>
								<?php if ( 'new' === $status ) : ?>
									<span class="gca-admin-pill gca-admin-pill--info"><?php esc_html_e( 'New', 'gemini-chat-assistant' ); ?></span>
								<?php elseif ( 'contacted' === $status ) : ?>
									<span class="gca-admin-pill gca-admin-pill--success"><?php esc_html_e( 'Contacted', 'gemini-chat-assistant' ); ?></span>
								<?php else : ?>
									<span class="gca-admin-pill gca-admin-pill--muted"><?php esc_html_e( 'Closed', 'gemini-chat-assistant' ); ?></span>
								<?php endif; ?>
							</td>

							<!-- Associated Conversation Link -->
							<td>
								<?php if ( ! empty( $conv_uuid ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::CONVERSATIONS_MENU_SLUG . '&conversation_id=' . $conv_uuid ) ); ?>" class="button button-small">
										<span class="dashicons dashicons-format-chat" style="vertical-align: middle; margin-right: 2px;"></span>
										<?php esc_html_e( 'View Chat', 'gemini-chat-assistant' ); ?>
									</a>
								<?php else : ?>
									<span style="color: #8c8f94;"><?php esc_html_e( 'No chat', 'gemini-chat-assistant' ); ?></span>
								<?php endif; ?>
							</td>

							<!-- Date -->
							<td>
								<span style="font-size: 12px; color: #50575e;"><?php echo esc_html( $created ); ?></span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Pagination Bar -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="gca-admin-pagination">
					<div class="gca-admin-pagination__info">
						<?php
						printf(
							/* translators: 1: Current page, 2: Total pages, 3: Total items */
							esc_html__( 'Page %1$d of %2$d (%3$d total inquiries)', 'gemini-chat-assistant' ),
							$current_page,
							$total_pages,
							$total_items
						);
						?>
					</div>
					<div class="gca-admin-pagination__links">
						<?php
						echo paginate_links( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo; ' . __( 'Previous', 'gemini-chat-assistant' ),
							'next_text' => __( 'Next', 'gemini-chat-assistant' ) . ' &raquo;',
							'total'     => $total_pages,
							'current'   => $current_page,
						] );
						?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
