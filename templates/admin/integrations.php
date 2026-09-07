<?php
/**
 * Admin Integrations Page Template.
 *
 * @package SkyFish\GeminiChat\Templates
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'gemini-chat-assistant' ) );
}

/**
 * @var \SkyFish\GeminiChat\Integrations\IntegrationRegistry $integration_registry
 */
$registered_integrations = isset( $integration_registry ) ? $integration_registry->get_all() : [];
$registered_count        = count( $registered_integrations );
?>
<div class="wrap gca-admin-wrap">
	<!-- Page Header -->
	<header class="gca-admin-header">
		<div class="gca-admin-header__info">
			<div class="gca-admin-header__icon" aria-hidden="true">
				<span class="dashicons dashicons-networking"></span>
			</div>
			<div>
				<h1 class="gca-admin-header__title">
					<?php esc_html_e( 'Business Integrations', 'gemini-chat-assistant' ); ?>
					<span class="gca-admin-badge gca-admin-badge--version"><?php echo esc_html( GCA_VERSION ); ?></span>
				</h1>
				<p class="gca-admin-header__subtitle">
					<?php esc_html_e( 'Connect Gemini Chat Assistant with WordPress services, store catalogs, and external contact channels.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>
		</div>
	</header>

	<!-- Architecture Framework Banner -->
	<div class="gca-admin-card" style="margin-bottom: 24px; border-left: 4px solid #2271b1;">
		<div style="display: flex; align-items: flex-start; gap: 16px;">
			<div style="color: #2271b1; font-size: 28px; line-height: 1;" aria-hidden="true">
				<span class="dashicons dashicons-shield"></span>
			</div>
			<div>
				<h2 style="margin: 0 0 6px; font-size: 16px; font-weight: 600;">
					<?php esc_html_e( 'Integration Framework Active (Subnode N17.1)', 'gemini-chat-assistant' ); ?>
				</h2>
				<p style="margin: 0; color: #50575e; font-size: 13px; line-height: 1.5;">
					<?php esc_html_e( 'The secure business integration framework is active. It provides strictly isolated action registries, argument validation, and risk policies (Read / Write / External). Business modules plug into this layer in upcoming roadmap subnodes.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>
		</div>
	</div>

	<!-- Registered Integrations Overview (if any registered) -->
	<?php if ( $registered_count > 0 ) : ?>
		<section class="gca-admin-section" aria-labelledby="gca-heading-active-integrations">
			<h2 id="gca-heading-active-integrations" class="gca-admin-section__title">
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Registered Business Integrations', 'gemini-chat-assistant' ); ?>
				<span class="gca-admin-badge"><?php echo esc_html( (string) $registered_count ); ?></span>
			</h2>
			<div class="gca-admin-grid gca-admin-grid--3">
				<?php foreach ( $registered_integrations as $integration ) : ?>
					<?php
					$is_available = $integration->is_available();
					$is_enabled   = $integration->is_enabled();
					$actions      = $integration->get_actions();
					?>
					<div class="gca-admin-card">
						<div class="gca-admin-card__header">
							<span class="dashicons dashicons-admin-plugins" style="color: #2271b1;"></span>
							<span class="gca-admin-pill <?php echo ( $is_available && $is_enabled ) ? 'gca-admin-pill--success' : 'gca-admin-pill--warning'; ?>">
								<?php
								if ( ! $is_available ) {
									esc_html_e( 'Unavailable', 'gemini-chat-assistant' );
								} elseif ( ! $is_enabled ) {
									esc_html_e( 'Disabled', 'gemini-chat-assistant' );
								} else {
									esc_html_e( 'Active', 'gemini-chat-assistant' );
								}
								?>
							</span>
						</div>
						<h3 class="gca-admin-card__title"><?php echo esc_html( $integration->get_name() ); ?></h3>
						<p class="gca-admin-card__desc"><?php echo esc_html( $integration->get_description() ); ?></p>
						<div style="margin-top: 12px; font-size: 12px; color: #646970;">
							<strong><?php esc_html_e( 'Registered Actions:', 'gemini-chat-assistant' ); ?></strong>
							<?php echo esc_html( (string) count( $actions ) ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- Planned Modules (Subnodes N17.2 - N17.5) -->
	<section class="gca-admin-section" aria-labelledby="gca-heading-planned-integrations">
		<h2 id="gca-heading-planned-integrations" class="gca-admin-section__title">
			<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
			<?php esc_html_e( 'Planned Business Integrations', 'gemini-chat-assistant' ); ?>
		</h2>
		<p class="gca-admin-section__desc">
			<?php esc_html_e( 'These capabilities are architected to plug directly into the integration framework in upcoming roadmap releases.', 'gemini-chat-assistant' ); ?>
		</p>

		<div class="gca-admin-grid gca-admin-grid--2">
			<!-- WooCommerce -->
			<?php
			$wc_installed   = class_exists( 'WooCommerce' );
			$wc_integration = $integration_registry->get( 'woocommerce' );
			$wc_available   = $wc_integration ? $wc_integration->is_available() : false;
			?>
			<div class="gca-admin-card">
				<div class="gca-admin-card__header">
					<span class="dashicons dashicons-cart" style="color: #7f54b3;"></span>
					<span class="gca-admin-pill <?php echo $wc_available ? 'gca-admin-pill--success' : 'gca-admin-pill--info'; ?>">
						<?php echo $wc_available ? esc_html__( 'Active • Node N17.2', 'gemini-chat-assistant' ) : esc_html__( 'Subnode N17.2 • Ready', 'gemini-chat-assistant' ); ?>
					</span>
				</div>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'WooCommerce Store & Catalog', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php esc_html_e( 'Read-only live product queries, real-time pricing lookups, stock availability checks, and category searches.', 'gemini-chat-assistant' ); ?>
				</p>
				<div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #f0f0f1; font-size: 12px; color: #646970;">
					<div style="display: flex; gap: 16px; margin-bottom: 6px;">
						<span><strong><?php esc_html_e( 'WooCommerce Installed:', 'gemini-chat-assistant' ); ?></strong> <?php echo $wc_installed ? esc_html__( 'Yes', 'gemini-chat-assistant' ) : esc_html__( 'No', 'gemini-chat-assistant' ); ?></span>
						<span><strong><?php esc_html_e( 'Integration:', 'gemini-chat-assistant' ); ?></strong> <?php echo $wc_available ? esc_html__( 'Available', 'gemini-chat-assistant' ) : esc_html__( 'Unavailable', 'gemini-chat-assistant' ); ?></span>
					</div>
					<span class="dashicons dashicons-info" style="font-size: 16px; line-height: 1; vertical-align: text-top;"></span>
					<?php esc_html_e( 'Scope: Read-only catalog queries. Cart, checkout, payment, and order actions are strictly excluded.', 'gemini-chat-assistant' ); ?>
				</div>
			</div>

			<!-- Human Agent Handoff -->
			<?php
			$handoff_integration = $integration_registry ? $integration_registry->get( 'handoff' ) : null;
			$handoff_available   = $handoff_integration ? $handoff_integration->is_available() : false;
			?>
			<div class="gca-admin-card">
				<div class="gca-admin-card__header">
					<span class="dashicons dashicons-businesswoman" style="color: #0073aa;"></span>
					<span class="gca-admin-pill <?php echo $handoff_available ? 'gca-admin-pill--success' : 'gca-admin-pill--info'; ?>">
						<?php echo $handoff_available ? esc_html__( 'Active • Node N17.3', 'gemini-chat-assistant' ) : esc_html__( 'Subnode N17.3 • Ready', 'gemini-chat-assistant' ); ?>
					</span>
				</div>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Live Agent & Human Handoff', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php esc_html_e( 'Transfer complex visitor queries to human support personnel, ticket desks, or live operator queues with session transcripts.', 'gemini-chat-assistant' ); ?>
				</p>
				<div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #f0f0f1; font-size: 12px; color: #646970;">
					<div style="display: flex; gap: 16px; margin-bottom: 6px;">
						<span><strong><?php esc_html_e( 'Framework Action:', 'gemini-chat-assistant' ); ?></strong> <code>handoff.create</code></span>
						<span><strong><?php esc_html_e( 'Status:', 'gemini-chat-assistant' ); ?></strong> <?php echo $handoff_available ? esc_html__( 'Active', 'gemini-chat-assistant' ) : esc_html__( 'Ready', 'gemini-chat-assistant' ); ?></span>
					</div>
					<span class="dashicons dashicons-info" style="font-size: 16px; line-height: 1; vertical-align: text-top;"></span>
					<?php esc_html_e( 'Scope: Controlled escalation requests and admin management. External email/SMS dispatches belong to N17.4+.', 'gemini-chat-assistant' ); ?>
				</div>
			</div>

			<!-- Email Notifications -->
			<div class="gca-admin-card">
				<div class="gca-admin-card__header">
					<span class="dashicons dashicons-email-alt" style="color: #46b450;"></span>
					<span class="gca-admin-pill gca-admin-pill--info">
						<?php esc_html_e( 'Subnode N17.4 • Framework Ready', 'gemini-chat-assistant' ); ?>
					</span>
				</div>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Email Notifications & Alerts', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php esc_html_e( 'Automated email dispatches for lead captures, urgent inquiry alerts, and visitor transcript digests.', 'gemini-chat-assistant' ); ?>
				</p>
				<div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #f0f0f1; font-size: 12px; color: #646970;">
					<span class="dashicons dashicons-info" style="font-size: 16px; line-height: 1; vertical-align: text-top;"></span>
					<?php esc_html_e( 'Status: Framework contracts defined. Implementation scheduled in Subnode N17.4.', 'gemini-chat-assistant' ); ?>
				</div>
			</div>

			<!-- External Contact Actions -->
			<div class="gca-admin-card">
				<div class="gca-admin-card__header">
					<span class="dashicons dashicons-phone" style="color: #25d366;"></span>
					<span class="gca-admin-pill gca-admin-pill--info">
						<?php esc_html_e( 'Subnode N17.5 • Framework Ready', 'gemini-chat-assistant' ); ?>
					</span>
				</div>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Direct Contact Channels (WhatsApp & Calls)', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php esc_html_e( 'Deep-linked direct contact actions allowing visitors to initiate pre-populated WhatsApp chats or direct phone calls.', 'gemini-chat-assistant' ); ?>
				</p>
				<div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #f0f0f1; font-size: 12px; color: #646970;">
					<span class="dashicons dashicons-info" style="font-size: 16px; line-height: 1; vertical-align: text-top;"></span>
					<?php esc_html_e( 'Status: Framework contracts defined. Implementation scheduled in Subnode N17.5.', 'gemini-chat-assistant' ); ?>
				</div>
			</div>
		</div>
	</section>
</div>
