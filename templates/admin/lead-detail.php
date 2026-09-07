<?php
/**
 * Admin Lead Detail View Template.
 *
 * @package SkyFish\GeminiChat\Templates
 */

use SkyFish\GeminiChat\Admin\AdminMenu;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array<string, mixed>      $lead
 * @var array<string, mixed>|null $conversation
 * @var string                    $back_url
 */

$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
$lead_id     = (string) $lead['public_id'];
$name        = ! empty( $lead['name'] ) ? (string) $lead['name'] : __( 'Anonymous Visitor', 'gemini-chat-assistant' );
$email       = ! empty( $lead['email'] ) ? (string) $lead['email'] : '';
$phone       = ! empty( $lead['phone'] ) ? (string) $lead['phone'] : '';
$req         = ! empty( $lead['requirement'] ) ? (string) $lead['requirement'] : '';
$status      = (string) ( $lead['status'] ?? 'new' );
$created     = ! empty( $lead['created_at'] ) ? get_date_from_gmt( $lead['created_at'], $date_format ) : '—';
$updated     = ! empty( $lead['updated_at'] ) ? get_date_from_gmt( $lead['updated_at'], $date_format ) : '—';
$user_id     = absint( $lead['user_id'] ?? 0 );
$wp_user     = $user_id > 0 ? get_userdata( $user_id ) : null;
?>
<div class="wrap gca-admin-wrap">
	<!-- Breadcrumb -->
	<div class="gca-admin-breadcrumb">
		<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary">
			<span class="dashicons dashicons-arrow-left-alt" style="vertical-align: middle; margin-right: 4px;"></span>
			<?php esc_html_e( 'Back to Leads', 'gemini-chat-assistant' ); ?>
		</a>
	</div>

	<!-- Header -->
	<header class="gca-admin-header gca-admin-detail-header">
		<div class="gca-admin-header__info">
			<div class="gca-admin-header__icon" aria-hidden="true">
				<span class="dashicons dashicons-id-alt"></span>
			</div>
			<div>
				<h1 class="gca-admin-header__title">
					<?php echo esc_html( $name ); ?>
					<?php if ( 'new' === $status ) : ?>
						<span class="gca-admin-pill gca-admin-pill--info"><?php esc_html_e( 'New', 'gemini-chat-assistant' ); ?></span>
					<?php elseif ( 'contacted' === $status ) : ?>
						<span class="gca-admin-pill gca-admin-pill--success"><?php esc_html_e( 'Contacted', 'gemini-chat-assistant' ); ?></span>
					<?php else : ?>
						<span class="gca-admin-pill gca-admin-pill--muted"><?php esc_html_e( 'Closed', 'gemini-chat-assistant' ); ?></span>
					<?php endif; ?>
				</h1>
				<div class="gca-admin-detail-meta">
					<span><strong><?php esc_html_e( 'Lead ID:', 'gemini-chat-assistant' ); ?></strong> <code><?php echo esc_html( $lead_id ); ?></code></span>
					<span>&bull;</span>
					<span><strong><?php esc_html_e( 'Received:', 'gemini-chat-assistant' ); ?></strong> <?php echo esc_html( $created ); ?></span>
				</div>
			</div>
		</div>
	</header>

	<!-- Notices -->
	<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible gca-admin-notice">
			<p><?php esc_html_e( 'Lead status successfully updated.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="gca-admin-layout-2col" style="align-items: flex-start;">
		<!-- Left Column: Information & Inquiry Details -->
		<div style="display: flex; flex-direction: column; gap: 20px;">
			<!-- Contact Information Card -->
			<section class="gca-admin-card" aria-labelledby="gca-heading-contact">
				<h2 id="gca-heading-contact" class="gca-admin-card__section-title">
					<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
					<?php esc_html_e( 'Contact Information', 'gemini-chat-assistant' ); ?>
				</h2>

				<div class="gca-admin-breakdown-list">
					<div class="gca-admin-breakdown-item">
						<div class="gca-admin-breakdown-meta">
							<span><?php esc_html_e( 'Full Name:', 'gemini-chat-assistant' ); ?></span>
							<strong><?php echo esc_html( $name ); ?></strong>
						</div>
					</div>

					<div class="gca-admin-breakdown-item">
						<div class="gca-admin-breakdown-meta">
							<span><?php esc_html_e( 'Email Address:', 'gemini-chat-assistant' ); ?></span>
							<?php if ( ! empty( $email ) ) : ?>
								<a href="mailto:<?php echo esc_attr( $email ); ?>" style="font-weight: 600;">
									<span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 2px;"></span>
									<?php echo esc_html( $email ); ?>
								</a>
							<?php else : ?>
								<span style="color: #8c8f94;"><?php esc_html_e( 'Not provided', 'gemini-chat-assistant' ); ?></span>
							<?php endif; ?>
						</div>
					</div>

					<div class="gca-admin-breakdown-item">
						<div class="gca-admin-breakdown-meta">
							<span><?php esc_html_e( 'Phone Number:', 'gemini-chat-assistant' ); ?></span>
							<?php if ( ! empty( $phone ) ) : ?>
								<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>" style="font-weight: 600;">
									<span class="dashicons dashicons-phone" style="vertical-align: middle; margin-right: 2px;"></span>
									<?php echo esc_html( $phone ); ?>
								</a>
							<?php else : ?>
								<span style="color: #8c8f94;"><?php esc_html_e( 'Not provided', 'gemini-chat-assistant' ); ?></span>
							<?php endif; ?>
						</div>
					</div>

					<div class="gca-admin-breakdown-item">
						<div class="gca-admin-breakdown-meta">
							<span><?php esc_html_e( 'WordPress User:', 'gemini-chat-assistant' ); ?></span>
							<?php if ( $wp_user ) : ?>
								<span>
									<span class="dashicons dashicons-admin-users" style="vertical-align: middle; margin-right: 2px;"></span>
									<?php echo esc_html( $wp_user->display_name ); ?> (ID #<?php echo esc_html( (string) $user_id ); ?>)
								</span>
							<?php else : ?>
								<span style="color: #50575e;"><?php esc_html_e( 'Guest Visitor', 'gemini-chat-assistant' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</section>

			<!-- Inquiry / Requirement Card -->
			<section class="gca-admin-card" aria-labelledby="gca-heading-req">
				<h2 id="gca-heading-req" class="gca-admin-card__section-title">
					<span class="dashicons dashicons-format-aside" aria-hidden="true"></span>
					<?php esc_html_e( 'Inquiry / Requirement', 'gemini-chat-assistant' ); ?>
				</h2>

				<?php if ( ! empty( $req ) ) : ?>
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; font-size: 14px; line-height: 1.6; white-space: pre-wrap; word-break: break-word; color: #1e293b;">
						<?php echo esc_html( $req ); ?>
					</div>
				<?php else : ?>
					<p style="color: #64748b; margin: 0;"><?php esc_html_e( 'No requirement text submitted by visitor.', 'gemini-chat-assistant' ); ?></p>
				<?php endif; ?>
			</section>

			<!-- Associated Conversation Card -->
			<section class="gca-admin-card" aria-labelledby="gca-heading-conv">
				<h2 id="gca-heading-conv" class="gca-admin-card__section-title">
					<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
					<?php esc_html_e( 'Associated Conversation', 'gemini-chat-assistant' ); ?>
				</h2>

				<?php if ( $conversation && ! empty( $conversation['public_id'] ) ) : ?>
					<div class="gca-admin-breakdown-list">
						<div class="gca-admin-breakdown-item">
							<div class="gca-admin-breakdown-meta">
								<span><?php esc_html_e( 'Conversation Public ID:', 'gemini-chat-assistant' ); ?></span>
								<code><?php echo esc_html( (string) $conversation['public_id'] ); ?></code>
							</div>
						</div>
						<div class="gca-admin-breakdown-item">
							<div class="gca-admin-breakdown-meta">
								<span><?php esc_html_e( 'Chat Status:', 'gemini-chat-assistant' ); ?></span>
								<span class="gca-admin-pill <?php echo 'active' === $conversation['status'] ? 'gca-admin-pill--success' : 'gca-admin-pill--muted'; ?>">
									<?php echo esc_html( ucfirst( (string) $conversation['status'] ) ); ?>
								</span>
							</div>
						</div>
						<div class="gca-admin-breakdown-item">
							<div class="gca-admin-breakdown-meta">
								<span><?php esc_html_e( 'Messages Exchanged:', 'gemini-chat-assistant' ); ?></span>
								<strong><?php echo esc_html( (string) ( $conversation['message_count'] ?? 0 ) ); ?></strong>
							</div>
						</div>
					</div>

					<div style="margin-top: 16px;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::CONVERSATIONS_MENU_SLUG . '&conversation_id=' . (string) $conversation['public_id'] ) ); ?>" class="button button-primary">
							<span class="dashicons dashicons-visibility" style="vertical-align: middle; margin-right: 4px;"></span>
							<?php esc_html_e( 'View Conversation Transcript', 'gemini-chat-assistant' ); ?> &rarr;
						</a>
					</div>
				<?php else : ?>
					<p style="color: #64748b; margin: 0;"><?php esc_html_e( 'No active conversation associated with this lead, or the conversation transcript has been deleted.', 'gemini-chat-assistant' ); ?></p>
				<?php endif; ?>
			</section>
		</div>

		<!-- Right Column: Status Controls & Danger Zone -->
		<div style="display: flex; flex-direction: column; gap: 20px;">
			<!-- Status Management Card -->
			<section class="gca-admin-card" aria-labelledby="gca-heading-status-mgmt">
				<h2 id="gca-heading-status-mgmt" class="gca-admin-card__section-title">
					<span class="dashicons dashicons-flag" aria-hidden="true"></span>
					<?php esc_html_e( 'Manage Status', 'gemini-chat-assistant' ); ?>
				</h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gca_update_lead_status" />
					<input type="hidden" name="lead_id" value="<?php echo esc_attr( $lead_id ); ?>" />
					<?php wp_nonce_field( 'gca_update_lead_status_' . $lead_id ); ?>

					<div style="margin-bottom: 16px;">
						<label for="gca-lead-status-select" style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">
							<?php esc_html_e( 'Status:', 'gemini-chat-assistant' ); ?>
						</label>
						<select id="gca-lead-status-select" name="status" style="width: 100%;">
							<option value="new" <?php selected( $status, 'new' ); ?>><?php esc_html_e( 'New', 'gemini-chat-assistant' ); ?></option>
							<option value="contacted" <?php selected( $status, 'contacted' ); ?>><?php esc_html_e( 'Contacted', 'gemini-chat-assistant' ); ?></option>
							<option value="closed" <?php selected( $status, 'closed' ); ?>><?php esc_html_e( 'Closed', 'gemini-chat-assistant' ); ?></option>
						</select>
					</div>

					<button type="submit" class="button button-primary" style="width: 100%;">
						<?php esc_html_e( 'Save Status', 'gemini-chat-assistant' ); ?>
					</button>
				</form>
			</section>

			<!-- Danger Zone: Deletion Card -->
			<section class="gca-admin-card" style="border-left: 4px solid #b32d2e;" aria-labelledby="gca-heading-danger">
				<h2 id="gca-heading-danger" class="gca-admin-card__section-title" style="color: #b32d2e;">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					<?php esc_html_e( 'Delete Inquiry', 'gemini-chat-assistant' ); ?>
				</h2>
				<p style="font-size: 12px; color: #64748b; line-height: 1.45; margin-bottom: 16px;">
					<?php esc_html_e( 'Permanently delete this lead record. The associated chat conversation will be preserved.', 'gemini-chat-assistant' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to permanently delete this lead inquiry?', 'gemini-chat-assistant' ); ?>');">
					<input type="hidden" name="action" value="gca_delete_lead" />
					<input type="hidden" name="lead_id" value="<?php echo esc_attr( $lead_id ); ?>" />
					<?php wp_nonce_field( 'gca_delete_lead_' . $lead_id ); ?>
					<button type="submit" class="button button-link-delete" style="color: #b32d2e; width: 100%; text-align: center;">
						<?php esc_html_e( 'Delete Lead Record', 'gemini-chat-assistant' ); ?>
					</button>
				</form>
			</section>
		</div>
	</div>
</div>
