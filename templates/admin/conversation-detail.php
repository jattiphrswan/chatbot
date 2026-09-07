<?php
/**
 * Admin Conversation Detail & Transcript Template.
 *
 * @package SkyFish\GeminiChat\Templates
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array<string, mixed>            $conversation
 * @var array<int, array<string, mixed>> $messages
 * @var string                          $back_url
 */

$date_format = get_option( 'date_format', 'Y-m-d' );
$time_format = get_option( 'time_format', 'H:i' );

$pub_id     = (string) $conversation['public_id'];
$status     = strtolower( (string) $conversation['status'] );
$user_id    = absint( $conversation['user_id'] ?? 0 );
$user_info  = esc_html__( 'Guest Visitor', 'gemini-chat-assistant' );
if ( $user_id > 0 ) {
	$user_obj = get_userdata( $user_id );
	if ( $user_obj ) {
		$user_info = esc_html( $user_obj->display_name ) . ' (' . esc_html( $user_obj->user_login ) . ' — ' . esc_html( $user_obj->user_email ) . ')';
	}
}

$created_ts = strtotime( (string) $conversation['created_at'] );
$created_dt = $created_ts ? wp_date( "{$date_format} {$time_format}", $created_ts ) : '—';
$updated_ts = strtotime( (string) $conversation['updated_at'] );
$updated_dt = $updated_ts ? wp_date( "{$date_format} {$time_format}", $updated_ts ) : '—';
?>
<div class="wrap gca-admin-wrap">
	<!-- Navigation Breadcrumb -->
	<div class="gca-admin-breadcrumb">
		<a href="<?php echo esc_url( $back_url ); ?>" class="button">
			&larr; <?php esc_html_e( 'Back to Conversations', 'gemini-chat-assistant' ); ?>
		</a>
	</div>

	<!-- Detail Header Card -->
	<header class="gca-admin-header gca-admin-detail-header">
		<div class="gca-admin-header__info">
			<div>
				<h1 class="gca-admin-header__title">
					<code><?php echo esc_html( $pub_id ); ?></code>
					<span class="gca-admin-pill <?php echo 'active' === $status ? 'gca-admin-pill--success' : 'gca-admin-pill--muted'; ?>">
						<?php echo 'active' === $status ? esc_html__( 'Active', 'gemini-chat-assistant' ) : esc_html__( 'Closed', 'gemini-chat-assistant' ); ?>
					</span>
				</h1>
				<div class="gca-admin-detail-meta">
					<span><strong><?php esc_html_e( 'User:', 'gemini-chat-assistant' ); ?></strong> <?php echo esc_html( $user_info ); ?></span>
					<span>&bull;</span>
					<span><strong><?php esc_html_e( 'Started:', 'gemini-chat-assistant' ); ?></strong> <?php echo esc_html( $created_dt ); ?></span>
					<span>&bull;</span>
					<span><strong><?php esc_html_e( 'Last Activity:', 'gemini-chat-assistant' ); ?></strong> <?php echo esc_html( $updated_dt ); ?></span>
					<span>&bull;</span>
					<span><strong><?php esc_html_e( 'Total Turns:', 'gemini-chat-assistant' ); ?></strong> <?php echo absint( $conversation['message_count'] ?? count( $messages ) ); ?></span>
				</div>
			</div>
		</div>

		<div class="gca-admin-header__actions">
			<?php if ( 'active' === $status ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block;">
					<input type="hidden" name="action" value="gca_close_conversation" />
					<input type="hidden" name="conversation_id" value="<?php echo esc_attr( $pub_id ); ?>" />
					<?php wp_nonce_field( 'gca_close_conversation_' . $pub_id ); ?>
					<button type="submit" class="button">
						<span class="dashicons dashicons-lock" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Close Conversation', 'gemini-chat-assistant' ); ?>
					</button>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block;">
					<input type="hidden" name="action" value="gca_reopen_conversation" />
					<input type="hidden" name="conversation_id" value="<?php echo esc_attr( $pub_id ); ?>" />
					<?php wp_nonce_field( 'gca_reopen_conversation_' . $pub_id ); ?>
					<button type="submit" class="button">
						<span class="dashicons dashicons-unlock" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Reopen Conversation', 'gemini-chat-assistant' ); ?>
					</button>
				</form>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to permanently delete this conversation and all its messages?', 'gemini-chat-assistant' ); ?>');">
				<input type="hidden" name="action" value="gca_delete_conversation" />
				<input type="hidden" name="conversation_id" value="<?php echo esc_attr( $pub_id ); ?>" />
				<?php wp_nonce_field( 'gca_delete_conversation_' . $pub_id ); ?>
				<button type="submit" class="button button-link-delete" style="color: #b32d2e;">
					<span class="dashicons dashicons-trash" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Delete', 'gemini-chat-assistant' ); ?>
				</button>
			</form>
		</div>
	</header>

	<!-- Message Transcript Stream -->
	<section class="gca-admin-card gca-admin-thread-card" aria-labelledby="gca-heading-transcript">
		<h2 id="gca-heading-transcript" class="gca-admin-card__section-title">
			<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
			<?php esc_html_e( 'Conversation Transcript', 'gemini-chat-assistant' ); ?>
		</h2>

		<?php if ( ! empty( $messages ) ) : ?>
			<div class="gca-admin-thread">
				<?php foreach ( $messages as $msg ) :
					$role    = strtolower( (string) $msg['role'] );
					$msg_ts  = strtotime( (string) $msg['created_at'] );
					$msg_dt  = $msg_ts ? wp_date( "{$date_format} {$time_format}", $msg_ts ) : '—';
					$content = (string) $msg['content'];
					$model   = ! empty( $msg['model'] ) ? (string) $msg['model'] : '';
				?>
					<div class="gca-admin-msg gca-admin-msg--<?php echo esc_attr( $role ); ?>">
						<div class="gca-admin-msg__header">
							<strong class="gca-admin-msg__author">
								<?php
								if ( 'assistant' === $role ) {
									esc_html_e( 'AI Assistant', 'gemini-chat-assistant' );
								} elseif ( 'user' === $role ) {
									esc_html_e( 'Visitor', 'gemini-chat-assistant' );
								} else {
									esc_html_e( 'System', 'gemini-chat-assistant' );
								}
								?>
							</strong>
							<span class="gca-admin-msg__time"><?php echo esc_html( $msg_dt ); ?></span>
							<?php if ( ! empty( $model ) ) : ?>
								<span class="gca-admin-pill gca-admin-pill--info" style="font-size: 10px; padding: 1px 6px;">
									<?php echo esc_html( $model ); ?>
								</span>
							<?php endif; ?>
						</div>
						<div class="gca-admin-msg__body">
							<p><?php echo esc_html( $content ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="gca-admin-empty-state">
				<span class="dashicons dashicons-archive" aria-hidden="true"></span>
				<h3><?php esc_html_e( 'No stored messages for this conversation', 'gemini-chat-assistant' ); ?></h3>
				<p><?php esc_html_e( 'Message history storage may have been disabled in Privacy Settings when this session took place, or no turns were recorded.', 'gemini-chat-assistant' ); ?></p>
			</div>
		<?php endif; ?>
	</section>
</div>
