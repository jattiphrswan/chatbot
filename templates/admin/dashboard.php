<?php
/**
 * Admin Dashboard Page Template.
 *
 * @package SkyFish\GeminiChat\Templates
 */

use SkyFish\GeminiChat\Admin\SettingsService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array<string, mixed> $settings
 * @var bool                 $is_configured
 * @var string               $model
 * @var string               $db_version
 * @var string               $settings_url
 */

$is_enabled     = ! empty( $settings['enabled'] );
$widget_enabled = ! empty( $settings['widget_enabled'] );
$store_messages = ! empty( $settings['store_messages'] );
$guest_access   = ! empty( $settings['guest_access'] );
$site_url       = function_exists( 'home_url' ) ? home_url( '/' ) : '/';

$profile_service     = new \SkyFish\GeminiChat\Admin\ProfileService();
$active_profile      = $profile_service->get_active_profile();
$active_profile_name = $active_profile['name'] ?? __( 'General Assistant', 'gemini-chat-assistant' );
?>
<div class="wrap gca-admin-wrap">
	<!-- Admin Header -->
	<header class="gca-admin-header">
		<div class="gca-admin-header__info">
			<div class="gca-admin-header__icon" aria-hidden="true">
				<span class="dashicons dashicons-format-chat"></span>
			</div>
			<div>
				<h1 class="gca-admin-header__title">
					<?php esc_html_e( 'Gemini Chat Assistant', 'gemini-chat-assistant' ); ?>
					<span class="gca-admin-badge gca-admin-badge--version"><?php echo esc_html( GCA_VERSION ); ?></span>
				</h1>
				<p class="gca-admin-header__subtitle">
					<?php esc_html_e( 'Native AI chatbot dashboard, system status, and administration overview.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>
		</div>
		<div class="gca-admin-header__actions">
			<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
				<span class="dashicons dashicons-admin-generic" style="vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'Configure Settings', 'gemini-chat-assistant' ); ?>
			</a>
		</div>
	</header>

	<!-- Setup Notice / Alerts -->
	<?php if ( ! $is_configured ) : ?>
		<div class="notice notice-warning gca-admin-notice inline">
			<p>
				<strong><?php esc_html_e( 'Action Required:', 'gemini-chat-assistant' ); ?></strong>
				<?php esc_html_e( 'Google Gemini API key is not configured. The chatbot cannot generate responses until a key is defined.', 'gemini-chat-assistant' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Add your key to wp-config.php:', 'gemini-chat-assistant' ); ?>
				<code>define( 'GCA_GEMINI_API_KEY', 'your-api-key' );</code>
				<?php esc_html_e( 'or set the environment variable:', 'gemini-chat-assistant' ); ?>
				<code>GEMINI_API_KEY="your-api-key"</code>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! $is_enabled ) : ?>
		<div class="notice notice-info gca-admin-notice inline">
			<p>
				<strong><?php esc_html_e( 'Chatbot Paused:', 'gemini-chat-assistant' ); ?></strong>
				<?php esc_html_e( 'The chat assistant is globally disabled. You can re-enable it in the plugin settings.', 'gemini-chat-assistant' ); ?>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Enable Chatbot &rarr;', 'gemini-chat-assistant' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<!-- System Status Cards Grid -->
	<section class="gca-admin-section" aria-labelledby="gca-heading-status">
		<h2 id="gca-heading-status" class="gca-admin-section__title">
			<span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
			<?php esc_html_e( 'System & Configuration Overview', 'gemini-chat-assistant' ); ?>
		</h2>

		<div class="gca-admin-grid gca-admin-grid--3">
			<!-- Card 1: Chatbot Status -->
			<div class="gca-admin-card gca-admin-card--status">
				<div class="gca-admin-card__header">
					<span class="gca-admin-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-format-chat"></span>
					</span>
					<span class="gca-admin-pill <?php echo $is_enabled ? 'gca-admin-pill--success' : 'gca-admin-pill--muted'; ?>">
						<?php echo $is_enabled ? esc_html__( 'Enabled', 'gemini-chat-assistant' ) : esc_html__( 'Disabled', 'gemini-chat-assistant' ); ?>
					</span>
				</div>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Chatbot Status', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php echo $is_enabled ? esc_html__( 'Assistant is active and responding to public inquiries.', 'gemini-chat-assistant' ) : esc_html__( 'Assistant is paused globally.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>

			<!-- Card 2: Gemini API Status -->
			<div class="gca-admin-card gca-admin-card--status">
				<div class="gca-admin-card__header">
					<span class="gca-admin-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-key"></span>
					</span>
					<span class="gca-admin-pill <?php echo $is_configured ? 'gca-admin-pill--success' : 'gca-admin-pill--warning'; ?>">
						<?php echo $is_configured ? esc_html__( 'Configured', 'gemini-chat-assistant' ) : esc_html__( 'Missing Key', 'gemini-chat-assistant' ); ?>
					</span>
				</div>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Google Gemini API', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php echo $is_configured ? esc_html__( 'API key loaded securely from server-side environment.', 'gemini-chat-assistant' ) : esc_html__( 'Requires server environment or wp-config key.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>

			<!-- Card 3: Model Configuration -->
			<div class="gca-admin-card gca-admin-card--status">
				<div class="gca-admin-card__header">
					<span class="gca-admin-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-superhero"></span>
					</span>
					<span class="gca-admin-pill gca-admin-pill--info">
						<?php echo esc_html( $model ); ?>
					</span>
				</div>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Active AI Model', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php esc_html_e( 'Interactions API v1 powered by Google Gemini.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>

			<!-- Card: AI Profile -->
			<div class="gca-admin-card gca-admin-card--status">
				<div class="gca-admin-card__header">
					<span class="gca-admin-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-admin-users"></span>
					</span>
					<span class="gca-admin-pill gca-admin-pill--success">
						<?php echo esc_html( $active_profile_name ); ?>
					</span>
				</div>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Active AI Profile', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php esc_html_e( 'Persona, instructions, and tone powering assistant interactions.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>

			<!-- Card 4: Database Schema -->
			<div class="gca-admin-card gca-admin-card--status">
				<div class="gca-admin-card__header">
					<span class="gca-admin-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-database"></span>
					</span>
					<span class="gca-admin-pill gca-admin-pill--success">
						<?php printf( esc_html__( 'v%s Ready', 'gemini-chat-assistant' ), esc_html( $db_version ) ); ?>
					</span>
				</div>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Database Schema', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php esc_html_e( 'Tables wp_gca_conversations and wp_gca_messages active.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>

			<!-- Card 5: Message Storage -->
			<div class="gca-admin-card gca-admin-card--status">
				<div class="gca-admin-card__header">
					<span class="gca-admin-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-archive"></span>
					</span>
					<span class="gca-admin-pill <?php echo $store_messages ? 'gca-admin-pill--success' : 'gca-admin-pill--muted'; ?>">
						<?php echo $store_messages ? esc_html__( 'Active', 'gemini-chat-assistant' ) : esc_html__( 'Disabled', 'gemini-chat-assistant' ); ?>
					</span>
				</div>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Conversation Storage', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php echo $store_messages ? esc_html__( 'Chat transcripts are securely stored in database.', 'gemini-chat-assistant' ) : esc_html__( 'Message persistence is turned off.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>

			<!-- Card 6: Guest Access -->
			<div class="gca-admin-card gca-admin-card--status">
				<div class="gca-admin-card__header">
					<span class="gca-admin-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-groups"></span>
					</span>
					<span class="gca-admin-pill <?php echo $guest_access ? 'gca-admin-pill--success' : 'gca-admin-pill--info'; ?>">
						<?php echo $guest_access ? esc_html__( 'Allowed', 'gemini-chat-assistant' ) : esc_html__( 'Members Only', 'gemini-chat-assistant' ); ?>
					</span>
				</div>
				<h3 class="gca-admin-card__title"><?php esc_html_e( 'Guest Access Policy', 'gemini-chat-assistant' ); ?></h3>
				<p class="gca-admin-card__desc">
					<?php echo $guest_access ? esc_html__( 'Logged-out visitors can interact with the chatbot.', 'gemini-chat-assistant' ) : esc_html__( 'Only authenticated WordPress users can chat.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>
		</div>
	</section>

	<!-- Setup Checklist & Quick Actions Two-Column Section -->
	<div class="gca-admin-layout-2col">
		<!-- Setup Checklist -->
		<section class="gca-admin-card gca-admin-checklist" aria-labelledby="gca-heading-checklist">
			<h2 id="gca-heading-checklist" class="gca-admin-card__section-title">
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Setup & Verification Checklist', 'gemini-chat-assistant' ); ?>
			</h2>
			<ul class="gca-admin-checklist__list">
				<li class="gca-admin-checklist__item is-done">
					<span class="dashicons dashicons-yes"></span>
					<div class="gca-admin-checklist__text">
						<strong><?php esc_html_e( 'Plugin Foundation Active', 'gemini-chat-assistant' ); ?></strong>
						<span><?php printf( esc_html__( 'Gemini Chat Assistant v%s loaded.', 'gemini-chat-assistant' ), esc_html( GCA_VERSION ) ); ?></span>
					</div>
				</li>
				<li class="gca-admin-checklist__item is-done">
					<span class="dashicons dashicons-yes"></span>
					<div class="gca-admin-checklist__text">
						<strong><?php esc_html_e( 'Database Schema Initialized', 'gemini-chat-assistant' ); ?></strong>
						<span><?php printf( esc_html__( 'Custom tables ready (Schema v%s).', 'gemini-chat-assistant' ), esc_html( $db_version ) ); ?></span>
					</div>
				</li>
				<li class="gca-admin-checklist__item <?php echo $is_configured ? 'is-done' : 'is-pending'; ?>">
					<span class="dashicons <?php echo $is_configured ? 'dashicons-yes' : 'dashicons-warning'; ?>"></span>
					<div class="gca-admin-checklist__text">
						<strong><?php esc_html_e( 'Google Gemini API Key', 'gemini-chat-assistant' ); ?></strong>
						<span><?php echo $is_configured ? esc_html__( 'Server-side key loaded securely.', 'gemini-chat-assistant' ) : esc_html__( 'Configuration required.', 'gemini-chat-assistant' ); ?></span>
					</div>
				</li>
				<li class="gca-admin-checklist__item <?php echo $is_enabled ? 'is-done' : 'is-pending'; ?>">
					<span class="dashicons <?php echo $is_enabled ? 'dashicons-yes' : 'dashicons-warning'; ?>"></span>
					<div class="gca-admin-checklist__text">
						<strong><?php esc_html_e( 'Chat Assistant Enabled', 'gemini-chat-assistant' ); ?></strong>
						<span><?php echo $is_enabled ? esc_html__( 'Active and ready for visitor queries.', 'gemini-chat-assistant' ) : esc_html__( 'Currently paused.', 'gemini-chat-assistant' ); ?></span>
					</div>
				</li>
				<li class="gca-admin-checklist__item is-done">
					<span class="dashicons dashicons-yes"></span>
					<div class="gca-admin-checklist__text">
						<strong><?php esc_html_e( 'Public UI & Shortcode Ready', 'gemini-chat-assistant' ); ?></strong>
						<span><code>[gemini_chat]</code> <?php esc_html_e( 'shortcode and floating widget available.', 'gemini-chat-assistant' ); ?></span>
					</div>
				</li>
			</ul>
		</section>

		<!-- Quick Actions & Helpful Links -->
		<section class="gca-admin-card gca-admin-quick-actions" aria-labelledby="gca-heading-actions">
			<h2 id="gca-heading-actions" class="gca-admin-card__section-title">
				<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
				<?php esc_html_e( 'Quick Actions', 'gemini-chat-assistant' ); ?>
			</h2>
			<div class="gca-admin-action-list">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gca-ai-assistant' ) ); ?>" class="gca-admin-action-item">
					<div class="gca-admin-action-item__icon" aria-hidden="true">
						<span class="dashicons dashicons-superhero"></span>
					</div>
					<div class="gca-admin-action-item__text">
						<strong><?php esc_html_e( 'Manage AI Assistant Profiles', 'gemini-chat-assistant' ); ?></strong>
						<span><?php esc_html_e( 'Configure personas, instructions, tone, and behavioral boundaries.', 'gemini-chat-assistant' ); ?></span>
					</div>
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gca-conversations' ) ); ?>" class="gca-admin-action-item">
					<div class="gca-admin-action-item__icon" aria-hidden="true">
						<span class="dashicons dashicons-format-chat"></span>
					</div>
					<div class="gca-admin-action-item__text">
						<strong><?php esc_html_e( 'Manage Conversations', 'gemini-chat-assistant' ); ?></strong>
						<span><?php esc_html_e( 'Search, inspect transcripts, and manage visitor sessions.', 'gemini-chat-assistant' ); ?></span>
					</div>
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gca-leads' ) ); ?>" class="gca-admin-action-item">
					<div class="gca-admin-action-item__icon" aria-hidden="true">
						<span class="dashicons dashicons-id-alt"></span>
					</div>
					<div class="gca-admin-action-item__text">
						<strong><?php esc_html_e( 'Manage Lead Inquiries', 'gemini-chat-assistant' ); ?></strong>
						<span><?php esc_html_e( 'View, filter, and respond to pre-chat contact submissions.', 'gemini-chat-assistant' ); ?></span>
					</div>
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gca-analytics' ) ); ?>" class="gca-admin-action-item">
					<div class="gca-admin-action-item__icon" aria-hidden="true">
						<span class="dashicons dashicons-chart-bar"></span>
					</div>
					<div class="gca-admin-action-item__text">
						<strong><?php esc_html_e( 'View Analytics & Insights', 'gemini-chat-assistant' ); ?></strong>
						<span><?php esc_html_e( 'Inspect volume trends, response speeds, and token usage.', 'gemini-chat-assistant' ); ?></span>
					</div>
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gca-appearance' ) ); ?>" class="gca-admin-action-item">
					<div class="gca-admin-action-item__icon" aria-hidden="true">
						<span class="dashicons dashicons-art"></span>
					</div>
					<div class="gca-admin-action-item__text">
						<strong><?php esc_html_e( 'Customize Appearance', 'gemini-chat-assistant' ); ?></strong>
						<span><?php esc_html_e( 'Brand colors, dimensions, launcher icons, and live preview.', 'gemini-chat-assistant' ); ?></span>
					</div>
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url( $settings_url ); ?>" class="gca-admin-action-item">
					<div class="gca-admin-action-item__icon" aria-hidden="true">
						<span class="dashicons dashicons-admin-generic"></span>
					</div>
					<div class="gca-admin-action-item__text">
						<strong><?php esc_html_e( 'Configure Assistant Settings', 'gemini-chat-assistant' ); ?></strong>
						<span><?php esc_html_e( 'Adjust model, prompts, limits, and privacy settings.', 'gemini-chat-assistant' ); ?></span>
					</div>
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url( $site_url ); ?>" target="_blank" rel="noopener" class="gca-admin-action-item">
					<div class="gca-admin-action-item__icon" aria-hidden="true">
						<span class="dashicons dashicons-admin-site-alt3"></span>
					</div>
					<div class="gca-admin-action-item__text">
						<strong><?php esc_html_e( 'View Frontend Website', 'gemini-chat-assistant' ); ?></strong>
						<span><?php esc_html_e( 'Test the public chatbot widget on your live site.', 'gemini-chat-assistant' ); ?></span>
					</div>
					<span class="dashicons dashicons-external" aria-hidden="true"></span>
				</a>
			</div>
		</section>
	</div>

	<!-- Roadmap & Future Modules Preview -->
	<section class="gca-admin-section" aria-labelledby="gca-heading-roadmap">
		<h2 id="gca-heading-roadmap" class="gca-admin-section__title">
			<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
			<?php esc_html_e( 'Roadmap Capabilities', 'gemini-chat-assistant' ); ?>
		</h2>
		<p class="gca-admin-section__desc">
			<?php esc_html_e( 'Upcoming features scheduled in the plugin architecture roadmap.', 'gemini-chat-assistant' ); ?>
		</p>

		<div class="gca-admin-grid gca-admin-grid--3">
			<div class="gca-admin-card">
				<div class="gca-admin-card__header">
					<span class="dashicons dashicons-format-chat" style="color: #2271b1;"></span>
					<span class="gca-admin-pill gca-admin-pill--success"><?php esc_html_e( 'Node N11 • Active', 'gemini-chat-assistant' ); ?></span>
				</div>
				<h4>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gca-conversations' ) ); ?>" style="text-decoration: none; color: inherit;">
						<?php esc_html_e( 'Conversations Management', 'gemini-chat-assistant' ); ?> &rarr;
					</a>
				</h4>
				<p class="gca-admin-card__desc"><?php esc_html_e( 'Search, browse, view, and manage multi-turn visitor chat transcripts.', 'gemini-chat-assistant' ); ?></p>
			</div>

			<div class="gca-admin-card">
				<div class="gca-admin-card__header">
					<span class="dashicons dashicons-chart-bar" style="color: #2271b1;"></span>
					<span class="gca-admin-pill gca-admin-pill--success"><?php esc_html_e( 'Node N12 • Active', 'gemini-chat-assistant' ); ?></span>
				</div>
				<h4>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gca-analytics' ) ); ?>" style="text-decoration: none; color: inherit;">
						<?php esc_html_e( 'Analytics & Insights', 'gemini-chat-assistant' ); ?> &rarr;
					</a>
				</h4>
				<p class="gca-admin-card__desc"><?php esc_html_e( 'Conversation volume, token usage metrics, latency, and engagement trends.', 'gemini-chat-assistant' ); ?></p>
			</div>

			<div class="gca-admin-card">
				<div class="gca-admin-card__header">
					<span class="dashicons dashicons-art" style="color: #2271b1;"></span>
					<span class="gca-admin-pill gca-admin-pill--success"><?php esc_html_e( 'Node N13 • Active', 'gemini-chat-assistant' ); ?></span>
				</div>
				<h4>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gca-appearance' ) ); ?>" style="text-decoration: none; color: inherit;">
						<?php esc_html_e( 'Appearance Builder', 'gemini-chat-assistant' ); ?> &rarr;
					</a>
				</h4>
				<p class="gca-admin-card__desc"><?php esc_html_e( 'Live visual customizer for brand colors, widget placement, and avatars.', 'gemini-chat-assistant' ); ?></p>
			</div>

			<div class="gca-admin-card">
				<div class="gca-admin-card__header">
					<span class="dashicons dashicons-id-alt" style="color: #2271b1;"></span>
					<span class="gca-admin-pill gca-admin-pill--success"><?php esc_html_e( 'Node N14 • Active', 'gemini-chat-assistant' ); ?></span>
				</div>
				<h4>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gca-leads' ) ); ?>" style="text-decoration: none; color: inherit;">
						<?php esc_html_e( 'Leads & Pre-Chat', 'gemini-chat-assistant' ); ?> &rarr;
					</a>
				</h4>
				<p class="gca-admin-card__desc"><?php esc_html_e( 'Configurable pre-chat lead capture form, lead repository, and admin inquiries management.', 'gemini-chat-assistant' ); ?></p>
			</div>

			<div class="gca-admin-card">
				<div class="gca-admin-card__header">
					<span class="dashicons dashicons-superhero" style="color: #2271b1;"></span>
					<span class="gca-admin-pill gca-admin-pill--success"><?php esc_html_e( 'Node N15 • Active', 'gemini-chat-assistant' ); ?></span>
				</div>
				<h4>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gca-ai-assistant' ) ); ?>" style="text-decoration: none; color: inherit;">
						<?php esc_html_e( 'AI Profiles & Custom Prompts', 'gemini-chat-assistant' ); ?> &rarr;
					</a>
				</h4>
				<p class="gca-admin-card__desc"><?php esc_html_e( 'Persona management, active profile selection, role guidance, tone, rules, and prompt builder.', 'gemini-chat-assistant' ); ?></p>
			</div>

			<div class="gca-admin-card gca-admin-card--future">
				<div class="gca-admin-card__header">
					<span class="dashicons dashicons-book"></span>
					<span class="gca-admin-pill gca-admin-pill--planned"><?php esc_html_e( 'Node N16', 'gemini-chat-assistant' ); ?></span>
				</div>
				<h4><?php esc_html_e( 'FAQ & Knowledge Hub', 'gemini-chat-assistant' ); ?></h4>
				<p><?php esc_html_e( 'Manage quick suggested questions and custom business knowledge grounding.', 'gemini-chat-assistant' ); ?></p>
			</div>

			<div class="gca-admin-card gca-admin-card--future">
				<div class="gca-admin-card__header">
					<span class="dashicons dashicons-media-text"></span>
					<span class="gca-admin-pill gca-admin-pill--planned"><?php esc_html_e( 'Node N18', 'gemini-chat-assistant' ); ?></span>
				</div>
				<h4><?php esc_html_e( 'Logs & Diagnostics', 'gemini-chat-assistant' ); ?></h4>
				<p><?php esc_html_e( 'Detailed REST endpoint logs, error tracking, and system self-tests.', 'gemini-chat-assistant' ); ?></p>
			</div>
		</div>
	</section>
</div>
