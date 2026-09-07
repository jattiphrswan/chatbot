<?php
/**
 * Admin Human Handoff Detail Template.
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
 * @var array<string, mixed>      $handoff
 * @var array<string, mixed>|null $conversation
 * @var array<string, mixed>|null $lead
 * @var string                    $back_url
 */

$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

$h_id         = (string) $handoff['public_id'];
$status       = (string) $handoff['status'];
$reason       = (string) $handoff['reason'];
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
<div class="wrap gca-admin-wrap">
	<!-- Header -->
	<header class="gca-admin-header">
		<div class="gca-admin-header__info">
			<div class="gca-admin-header__icon" aria-hidden="true">
				<span class="dashicons dashicons-businesswoman"></span>
			</div>
			<div>
				<h1 class="gca-admin-header__title">
					<?php esc_html_e( 'Handoff Request Details', 'gemini-chat-assistant' ); ?>
					<span class="gca-admin-pill <?php echo esc_attr( $pill_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
				</h1>
				<p class="gca-admin-header__subtitle">
					<code><?php echo esc_html( $h_id ); ?></code>
				</p>
			</div>
		</div>
		<div class="gca-admin-header__actions">
			<a href="<?php echo esc_url( $back_url ); ?>" class="button">
				&larr; <?php esc_html_e( 'Back to Handoffs', 'gemini-chat-assistant' ); ?>
			</a>
		</div>
	</header>

	<!-- Notices -->
	<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible gca-admin-notice">
			<p><?php esc_html_e( 'Handoff status successfully updated.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="gca-admin-layout-2col">
		<!-- Left: Details & Actions -->
		<div style="display: flex; flex-direction: column; gap: 20px;">
			<!-- Request Overview Card -->
			<div class="gca-admin-card">
				<h2 class="gca-admin-card__section-title" style="margin-bottom: 16px;">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
					<?php esc_html_e( 'Request Overview', 'gemini-chat-assistant' ); ?>
				</h2>

				<table class="form-table" role="presentation" style="margin: 0;">
					<tbody>
						<tr>
							<th scope="row" style="width: 140px; font-weight: 600;"><?php esc_html_e( 'Status', 'gemini-chat-assistant' ); ?></th>
							<td>
								<span class="gca-admin-pill <?php echo esc_attr( $pill_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
							</td>
						</tr>
						<tr>
							<th scope="row" style="font-weight: 600;"><?php esc_html_e( 'Reason', 'gemini-chat-assistant' ); ?></th>
							<td>
								<span class="gca-admin-pill gca-admin-pill--neutral"><?php echo esc_html( $reason_label ); ?></span>
							</td>
						</tr>
						<tr>
							<th scope="row" style="font-weight: 600;"><?php esc_html_e( 'Created At', 'gemini-chat-assistant' ); ?></th>
							<td>
								<?php
								$created = ! empty( $handoff['created_at'] ) ? strtotime( $handoff['created_at'] ) : false;
								echo $created ? esc_html( date_i18n( $date_format, $created ) ) : '—';
								?>
							</td>
						</tr>
						<tr>
							<th scope="row" style="font-weight: 600;"><?php esc_html_e( 'Last Updated', 'gemini-chat-assistant' ); ?></th>
							<td>
								<?php
								$updated = ! empty( $handoff['updated_at'] ) ? strtotime( $handoff['updated_at'] ) : false;
								echo $updated ? esc_html( date_i18n( $date_format, $updated ) ) : '—';
								?>
							</td>
						</tr>
						<tr>
							<th scope="row" style="font-weight: 600;"><?php esc_html_e( 'Email Notification', 'gemini-chat-assistant' ); ?></th>
							<td>
								<?php
								$notif_service = new \SkyFish\GeminiChat\Notifications\NotificationService();
								$is_notified   = $notif_service->has_been_sent( $h_id );
								$is_enabled    = $notif_service->is_enabled();
								if ( ! $is_enabled ) {
									echo '<span class="gca-admin-pill gca-admin-pill--muted">' . esc_html__( 'Disabled in Settings', 'gemini-chat-assistant' ) . '</span>';
								} elseif ( $is_notified ) {
									echo '<span class="gca-admin-pill gca-admin-pill--success">' . esc_html__( 'Sent (Accepted by Transport)', 'gemini-chat-assistant' ) . '</span>';
								} else {
									echo '<span class="gca-admin-pill gca-admin-pill--warning">' . esc_html__( 'Pending / Not Sent', 'gemini-chat-assistant' ) . '</span>';
								}
								?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Status Transition Action Card -->
			<div class="gca-admin-card">
				<h2 class="gca-admin-card__section-title" style="margin-bottom: 16px;">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e( 'Update Status', 'gemini-chat-assistant' ); ?>
				</h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
					<input type="hidden" name="action" value="gca_update_handoff_status" />
					<input type="hidden" name="handoff_id" value="<?php echo esc_attr( $h_id ); ?>" />
					<?php wp_nonce_field( 'gca_update_handoff_' . $h_id ); ?>

					<label for="gca-handoff-status-select" class="screen-reader-text"><?php esc_html_e( 'Select status', 'gemini-chat-assistant' ); ?></label>
					<select name="status" id="gca-handoff-status-select" style="min-width: 160px;">
						<?php foreach ( HandoffService::ALLOWED_STATUSES as $candidate_status ) : ?>
							<option value="<?php echo esc_attr( $candidate_status ); ?>" <?php selected( $status, $candidate_status ); ?>>
								<?php echo esc_html( HandoffService::get_status_label( $candidate_status ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Status', 'gemini-chat-assistant' ); ?>
					</button>
				</form>
			</div>
		</div>

		<!-- Right: Linked Conversation & Lead Cards -->
		<div style="display: flex; flex-direction: column; gap: 20px;">
			<!-- Linked Conversation Card -->
			<div class="gca-admin-card">
				<h2 class="gca-admin-card__section-title" style="margin-bottom: 16px;">
					<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
					<?php esc_html_e( 'Linked Conversation', 'gemini-chat-assistant' ); ?>
				</h2>

				<?php if ( $conversation ) : ?>
					<p style="margin: 0 0 12px; color: #50575e; font-size: 13px;">
						<strong><?php esc_html_e( 'Conversation UUID:', 'gemini-chat-assistant' ); ?></strong><br>
						<code><?php echo esc_html( (string) $conversation['public_id'] ); ?></code>
					</p>
					<p style="margin: 0 0 16px; color: #50575e; font-size: 13px;">
						<strong><?php esc_html_e( 'Messages Exchanged:', 'gemini-chat-assistant' ); ?></strong>
						<?php echo esc_html( (string) ( $conversation['message_count'] ?? 0 ) ); ?>
					</p>
					<?php
					$conv_link = add_query_arg(
						[
							'page'            => AdminMenu::CONVERSATIONS_MENU_SLUG,
							'conversation_id' => $conversation['public_id'],
						],
						admin_url( 'admin.php' )
					);
					?>
					<a href="<?php echo esc_url( $conv_link ); ?>" class="button button-secondary">
						<span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span>
						<?php esc_html_e( 'View Conversation Transcript', 'gemini-chat-assistant' ); ?>
					</a>
				<?php else : ?>
					<p style="margin: 0; color: #8c8f94;">
						<?php esc_html_e( 'No active conversation found.', 'gemini-chat-assistant' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<!-- Linked Lead Card -->
			<div class="gca-admin-card">
				<h2 class="gca-admin-card__section-title" style="margin-bottom: 16px;">
					<span class="dashicons dashicons-id-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Contact Information (Lead)', 'gemini-chat-assistant' ); ?>
				</h2>

				<?php if ( $lead ) : ?>
					<table class="form-table" role="presentation" style="margin: 0 0 16px;">
						<tbody>
							<tr>
								<th scope="row" style="width: 80px; font-weight: 600; padding: 6px 0;"><?php esc_html_e( 'Name', 'gemini-chat-assistant' ); ?></th>
								<td style="padding: 6px 0;"><?php echo esc_html( ! empty( $lead['name'] ) ? $lead['name'] : '—' ); ?></td>
							</tr>
							<tr>
								<th scope="row" style="font-weight: 600; padding: 6px 0;"><?php esc_html_e( 'Email', 'gemini-chat-assistant' ); ?></th>
								<td style="padding: 6px 0;">
									<?php if ( ! empty( $lead['email'] ) ) : ?>
										<a href="<?php echo esc_url( 'mailto:' . $lead['email'] ); ?>"><?php echo esc_html( $lead['email'] ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th scope="row" style="font-weight: 600; padding: 6px 0;"><?php esc_html_e( 'Phone', 'gemini-chat-assistant' ); ?></th>
								<td style="padding: 6px 0;">
									<?php if ( ! empty( $lead['phone'] ) ) : ?>
										<a href="<?php echo esc_url( 'tel:' . $lead['phone'] ); ?>"><?php echo esc_html( $lead['phone'] ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						</tbody>
					</table>
					<?php
					$lead_link = add_query_arg(
						[
							'page'    => AdminMenu::LEADS_MENU_SLUG,
							'lead_id' => $lead['public_id'],
						],
						admin_url( 'admin.php' )
					);
					?>
					<a href="<?php echo esc_url( $lead_link ); ?>" class="button button-secondary">
						<span class="dashicons dashicons-external" style="vertical-align: middle;"></span>
						<?php esc_html_e( 'Inspect Full Lead Record', 'gemini-chat-assistant' ); ?>
					</a>
				<?php else : ?>
					<div class="gca-admin-empty-state" style="padding: 16px; margin: 0;">
						<span class="dashicons dashicons-admin-users" style="font-size: 28px; width: 28px; height: 28px;"></span>
						<p style="margin: 6px 0 0; font-size: 13px;">
							<?php esc_html_e( 'Visitor has not submitted contact details via pre-chat capture.', 'gemini-chat-assistant' ); ?>
						</p>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
