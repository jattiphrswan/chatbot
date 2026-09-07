<?php
/**
 * Admin AI Assistant Profiles List Template.
 *
 * @package SkyFish\GeminiChat\Templates
 */

use SkyFish\GeminiChat\Admin\ProfileService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array<string, array<string, mixed>> $profiles
 * @var string                              $active_id
 * @var array<string, mixed>                $active_profile
 * @var string                              $preview_text
 * @var string                              $add_url
 * @var int                                 $max_profiles
 * @var bool                                $at_limit
 */

$created    = ! empty( $_GET['created'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$updated    = ! empty( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$duplicated = ! empty( $_GET['duplicated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$deleted    = ! empty( $_GET['deleted'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$activated  = ! empty( $_GET['activated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap gca-admin-wrap">
	<!-- Admin Header -->
	<header class="gca-admin-header">
		<div class="gca-admin-header__info">
			<div class="gca-admin-header__icon" aria-hidden="true">
				<span class="dashicons dashicons-superhero"></span>
			</div>
			<div>
				<h1 class="gca-admin-header__title">
					<?php esc_html_e( 'AI Assistant & Profiles', 'gemini-chat-assistant' ); ?>
					<span class="gca-admin-badge gca-admin-badge--count">
						<?php printf( esc_html__( '%1$d / %2$d Profiles', 'gemini-chat-assistant' ), count( $profiles ), $max_profiles ); ?>
					</span>
				</h1>
				<p class="gca-admin-header__subtitle">
					<?php esc_html_e( 'Configure personas, instructions, tone, and behavioral rules for the Gemini chatbot.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>
		</div>
		<div class="gca-admin-header__actions">
			<?php if ( ! $at_limit ) : ?>
				<a href="<?php echo esc_url( $add_url ); ?>" class="button button-primary">
					<span class="dashicons dashicons-plus-alt2" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Add New Profile', 'gemini-chat-assistant' ); ?>
				</a>
			<?php else : ?>
				<button class="button button-primary" disabled="disabled" title="<?php esc_attr_e( 'Maximum profile limit reached', 'gemini-chat-assistant' ); ?>">
					<span class="dashicons dashicons-plus-alt2" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Add New Profile', 'gemini-chat-assistant' ); ?>
				</button>
			<?php endif; ?>
		</div>
	</header>

	<!-- Notices -->
	<?php if ( $created ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'AI profile created successfully.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $updated ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'AI profile updated successfully.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $duplicated ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'AI profile duplicated successfully.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $deleted ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'AI profile deleted successfully.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $activated ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Active AI profile switched successfully. Future chatbot interactions will follow these instructions.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $at_limit ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Profile Limit Reached:', 'gemini-chat-assistant' ); ?></strong>
				<?php printf( esc_html__( 'You have reached the maximum limit of %d AI profiles. Please delete an unused profile to add a new one.', 'gemini-chat-assistant' ), $max_profiles ); ?>
			</p>
		</div>
	<?php endif; ?>

	<!-- Active Profile Highlight Card -->
	<section class="gca-admin-card" style="margin-bottom: 24px;">
		<div class="gca-admin-card__header" style="display: flex; align-items: center; justify-content: space-between;">
			<div>
				<span class="gca-admin-pill gca-admin-pill--success" style="font-size: 13px; font-weight: 600;">
					<span class="dashicons dashicons-yes" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-top;"></span>
					<?php esc_html_e( 'Currently Active Profile', 'gemini-chat-assistant' ); ?>
				</span>
				<h2 style="margin: 8px 0 4px; font-size: 20px;">
					<?php echo esc_html( $active_profile['name'] ?? __( 'General Assistant', 'gemini-chat-assistant' ) ); ?>
				</h2>
				<p style="margin: 0; color: #646970;">
					<?php echo esc_html( $active_profile['description'] ?? '' ); ?>
				</p>
			</div>
			<div style="text-align: right;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::AI_ASSISTANT_MENU_SLUG . '&action=edit&profile_id=' . ( $active_profile['id'] ?? '' ) ) ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-edit" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Edit Active Profile', 'gemini-chat-assistant' ); ?>
				</a>
			</div>
		</div>

		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f0f0f1;">
			<div>
				<strong style="display: block; font-size: 11px; text-transform: uppercase; color: #646970;"><?php esc_html_e( 'Tone', 'gemini-chat-assistant' ); ?></strong>
				<span style="font-size: 14px; font-weight: 500;">
					<?php echo esc_html( ProfileService::ALLOWED_TONES[ $active_profile['tone'] ?? 'professional' ] ?? 'Professional' ); ?>
				</span>
			</div>
			<div>
				<strong style="display: block; font-size: 11px; text-transform: uppercase; color: #646970;"><?php esc_html_e( 'Response Style', 'gemini-chat-assistant' ); ?></strong>
				<span style="font-size: 14px; font-weight: 500;">
					<?php echo esc_html( ProfileService::ALLOWED_STYLES[ $active_profile['response_style'] ?? 'balanced' ] ?? 'Balanced' ); ?>
				</span>
			</div>
			<div>
				<strong style="display: block; font-size: 11px; text-transform: uppercase; color: #646970;"><?php esc_html_e( 'Status', 'gemini-chat-assistant' ); ?></strong>
				<span class="gca-admin-pill gca-admin-pill--success" style="font-size: 11px;">
					<?php esc_html_e( 'Enabled & Active', 'gemini-chat-assistant' ); ?>
				</span>
			</div>
		</div>
	</section>

	<!-- Profiles Table Section -->
	<section class="gca-admin-section" aria-labelledby="gca-heading-profiles">
		<h2 id="gca-heading-profiles" class="gca-admin-section__title">
			<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
			<?php esc_html_e( 'Available Profiles', 'gemini-chat-assistant' ); ?>
		</h2>

		<table class="wp-list-table widefat fixed striped table-view-list" style="margin-top: 12px;">
			<thead>
				<tr>
					<th scope="col" style="width: 28%;"><?php esc_html_e( 'Profile Name', 'gemini-chat-assistant' ); ?></th>
					<th scope="col" style="width: 32%;"><?php esc_html_e( 'Description / Purpose', 'gemini-chat-assistant' ); ?></th>
					<th scope="col" style="width: 12%;"><?php esc_html_e( 'Tone', 'gemini-chat-assistant' ); ?></th>
					<th scope="col" style="width: 10%;"><?php esc_html_e( 'Status', 'gemini-chat-assistant' ); ?></th>
					<th scope="col" style="width: 8%;"><?php esc_html_e( 'Active', 'gemini-chat-assistant' ); ?></th>
					<th scope="col" style="width: 10%; text-align: right;"><?php esc_html_e( 'Actions', 'gemini-chat-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $profiles ) ) : ?>
					<tr>
						<td colspan="6"><?php esc_html_e( 'No profiles found. Click "Add New Profile" to create one.', 'gemini-chat-assistant' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $profiles as $id => $item ) : ?>
						<?php
						$is_this_active = ( $id === $active_id );
						$is_enabled     = ! empty( $item['enabled'] );
						$edit_url       = admin_url( 'admin.php?page=' . self::AI_ASSISTANT_MENU_SLUG . '&action=edit&profile_id=' . urlencode( $id ) );
						?>
						<tr>
							<td>
								<strong>
									<a href="<?php echo esc_url( $edit_url ); ?>" style="font-size: 14px;">
										<?php echo esc_html( $item['name'] ); ?>
									</a>
								</strong>
								<?php if ( $is_this_active ) : ?>
									<span class="gca-admin-pill gca-admin-pill--success" style="margin-left: 6px; font-size: 11px;">
										<?php esc_html_e( 'Active', 'gemini-chat-assistant' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								$desc = ! empty( $item['description'] ) ? $item['description'] : ( ! empty( $item['role'] ) ? $item['role'] : '—' );
								echo esc_html( wp_trim_words( $desc, 18, '...' ) );
								?>
							</td>
							<td>
								<?php echo esc_html( ProfileService::ALLOWED_TONES[ $item['tone'] ?? 'professional' ] ?? 'Professional' ); ?>
							</td>
							<td>
								<span class="gca-admin-pill <?php echo $is_enabled ? 'gca-admin-pill--success' : 'gca-admin-pill--muted'; ?>" style="font-size: 11px;">
									<?php echo $is_enabled ? esc_html__( 'Enabled', 'gemini-chat-assistant' ) : esc_html__( 'Disabled', 'gemini-chat-assistant' ); ?>
								</span>
							</td>
							<td>
								<?php if ( $is_this_active ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color: #00a32a; font-size: 20px;" title="<?php esc_attr_e( 'Currently active', 'gemini-chat-assistant' ); ?>"></span>
									<span class="screen-reader-text"><?php esc_html_e( 'Active', 'gemini-chat-assistant' ); ?></span>
								<?php else : ?>
									<span style="color: #a7aaad;">—</span>
								<?php endif; ?>
							</td>
							<td style="text-align: right;">
								<div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
									<!-- Edit -->
									<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small" aria-label="<?php echo esc_attr( sprintf( __( 'Edit profile %s', 'gemini-chat-assistant' ), $item['name'] ) ); ?>">
										<?php esc_html_e( 'Edit', 'gemini-chat-assistant' ); ?>
									</a>

									<!-- Activate (if enabled and not active) -->
									<?php if ( ! $is_this_active && $is_enabled ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
											<?php wp_nonce_field( 'gca_activate_profile_' . $id ); ?>
											<input type="hidden" name="action" value="gca_activate_profile" />
											<input type="hidden" name="profile_id" value="<?php echo esc_attr( $id ); ?>" />
											<button type="submit" class="button button-small button-primary" aria-label="<?php echo esc_attr( sprintf( __( 'Activate profile %s', 'gemini-chat-assistant' ), $item['name'] ) ); ?>">
												<?php esc_html_e( 'Activate', 'gemini-chat-assistant' ); ?>
											</button>
										</form>
									<?php endif; ?>

									<!-- Duplicate -->
									<?php if ( ! $at_limit ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
											<?php wp_nonce_field( 'gca_duplicate_profile_' . $id ); ?>
											<input type="hidden" name="action" value="gca_duplicate_profile" />
											<input type="hidden" name="profile_id" value="<?php echo esc_attr( $id ); ?>" />
											<button type="submit" class="button button-small" aria-label="<?php echo esc_attr( sprintf( __( 'Duplicate profile %s', 'gemini-chat-assistant' ), $item['name'] ) ); ?>" title="<?php esc_attr_e( 'Duplicate', 'gemini-chat-assistant' ); ?>">
												<span class="dashicons dashicons-admin-page" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
											</button>
										</form>
									<?php endif; ?>

									<!-- Delete (if not active or multiple profiles) -->
									<?php if ( count( $profiles ) > 1 && ! $is_this_active ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;" onsubmit="return confirm('<?php echo esc_js( sprintf( __( 'Are you sure you want to delete profile \'%s\'?', 'gemini-chat-assistant' ), $item['name'] ) ); ?>');">
											<?php wp_nonce_field( 'gca_delete_profile_' . $id ); ?>
											<input type="hidden" name="action" value="gca_delete_profile" />
											<input type="hidden" name="profile_id" value="<?php echo esc_attr( $id ); ?>" />
											<button type="submit" class="button button-small button-link-delete" aria-label="<?php echo esc_attr( sprintf( __( 'Delete profile %s', 'gemini-chat-assistant' ), $item['name'] ) ); ?>" title="<?php esc_attr_e( 'Delete', 'gemini-chat-assistant' ); ?>">
												<span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
											</button>
										</form>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</section>

	<!-- Effective Prompt Preview Section (Admin Only) -->
	<section class="gca-admin-card" style="margin-top: 24px;">
		<div class="gca-admin-card__header">
			<h3 class="gca-admin-card__title">
				<span class="dashicons dashicons-visibility" aria-hidden="true" style="vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'Effective System Instruction Preview (Active Profile)', 'gemini-chat-assistant' ); ?>
			</h3>
			<p class="gca-admin-card__desc">
				<?php esc_html_e( 'This is the assembled prompt sent to Google Gemini for chat turns under the currently active profile. It is strictly server-side and never exposed to website visitors.', 'gemini-chat-assistant' ); ?>
			</p>
		</div>
		<textarea readonly="readonly" rows="10" style="width: 100%; font-family: monospace; font-size: 12px; background: #f6f7f7; color: #2c3338; border: 1px solid #dcdcde; border-radius: 4px; padding: 10px; resize: vertical;"><?php echo esc_textarea( $preview_text ); ?></textarea>
	</section>
</div>
