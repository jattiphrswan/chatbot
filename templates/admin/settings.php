<?php
/**
 * Admin Settings Page Template.
 *
 * @package SkyFish\GeminiChat\Templates
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array<string, mixed> $settings
 * @var bool                 $is_configured
 */
?>
<div class="wrap gca-admin-wrap">
	<div class="gca-header">
		<h1 class="gca-header-title">
			<?php esc_html_e( 'Gemini Chat Assistant', 'gemini-chat-assistant' ); ?>
			<span class="gca-header-badge"><?php echo esc_html( GCA_VERSION ); ?></span>
		</h1>
	</div>

	<!-- API Status Banner -->
	<div class="gca-card gca-status-card <?php echo $is_configured ? 'is-configured' : ''; ?>">
		<div class="gca-status-info">
			<h3><?php esc_html_e( 'Google Gemini API Status', 'gemini-chat-assistant' ); ?></h3>
			<?php if ( $is_configured ) : ?>
				<p><?php esc_html_e( 'API key detected and loaded securely from server-side environment / configuration.', 'gemini-chat-assistant' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'No API key detected. Please define GEMINI_API_KEY environment variable or GCA_GEMINI_API_KEY constant in wp-config.php.', 'gemini-chat-assistant' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="gca-status-badge <?php echo $is_configured ? 'is-configured' : ''; ?>">
			<span class="dashicons <?php echo $is_configured ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
			<?php echo $is_configured ? esc_html__( 'Configured', 'gemini-chat-assistant' ) : esc_html__( 'Not Configured', 'gemini-chat-assistant' ); ?>
		</div>
	</div>

	<?php if ( ! $is_configured ) : ?>
		<div class="gca-instructions-box">
			<strong><?php esc_html_e( 'How to configure your Gemini API Key securely:', 'gemini-chat-assistant' ); ?></strong>
			<p><?php esc_html_e( 'Add the following definition to your wp-config.php file (above the "stop editing" line):', 'gemini-chat-assistant' ); ?></p>
			<code>define( 'GCA_GEMINI_API_KEY', 'your-gemini-api-key-here' );</code>
			<p><?php esc_html_e( 'Or export the environment variable on your web server:', 'gemini-chat-assistant' ); ?> <code>GEMINI_API_KEY="your-gemini-api-key-here"</code></p>
		</div>
	<?php if ( ! empty( $_GET['test_sent'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible gca-admin-notice">
			<p><?php esc_html_e( 'Test email accepted for sending by WordPress mail transport.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php elseif ( ! empty( $_GET['test_failed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-error is-dismissible gca-admin-notice">
			<p><?php esc_html_e( 'Test email could not be sent. WordPress wp_mail() returned false.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'gca_settings_group' );
		?>

		<!-- Navigation Tabs -->
		<div class="gca-nav-tab-wrapper">
			<button type="button" class="gca-tab-btn active" data-tab="general"><?php esc_html_e( 'General', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="ai"><?php esc_html_e( 'AI & Model', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="widget"><?php esc_html_e( 'Widget Display', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="prechat"><?php esc_html_e( 'Pre-Chat Form', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="faq"><?php esc_html_e( 'FAQ & Content', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="access"><?php esc_html_e( 'Access', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="limits"><?php esc_html_e( 'Limits', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="notifications"><?php esc_html_e( 'Notifications', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="privacy"><?php esc_html_e( 'Privacy', 'gemini-chat-assistant' ); ?></button>
		</div>

		<!-- Panel: General -->
		<div class="gca-tab-panel active" id="gca-panel-general">
			<div class="gca-section-card">
				<h3><?php esc_html_e( 'General Assistant Settings', 'gemini-chat-assistant' ); ?></h3>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
							<?php esc_html_e( 'Enable Chat Assistant Globally', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
					<div class="gca-field-row">
						<label for="gca_assistant_name"><?php esc_html_e( 'Assistant Name', 'gemini-chat-assistant' ); ?></label>
						<input type="text" id="gca_assistant_name" name="gca_settings[assistant_name]" value="<?php echo esc_attr( $settings['assistant_name'] ); ?>" />
						<span class="description"><?php esc_html_e( 'Display name shown in the chat window header.', 'gemini-chat-assistant' ); ?></span>
					</div>
					<div class="gca-field-row">
						<label for="gca_greeting"><?php esc_html_e( 'Greeting Title', 'gemini-chat-assistant' ); ?></label>
						<input type="text" id="gca_greeting" name="gca_settings[greeting]" value="<?php echo esc_attr( $settings['greeting'] ); ?>" />
					</div>
					<div class="gca-field-row">
						<label for="gca_welcome_message"><?php esc_html_e( 'Welcome Message', 'gemini-chat-assistant' ); ?></label>
						<textarea id="gca_welcome_message" name="gca_settings[welcome_message]" rows="3"><?php echo esc_textarea( $settings['welcome_message'] ); ?></textarea>
						<span class="description"><?php esc_html_e( 'Initial message displayed when the chat window opens.', 'gemini-chat-assistant' ); ?></span>
					</div>
					<div class="gca-field-row">
						<label for="gca_placeholder"><?php esc_html_e( 'Input Placeholder', 'gemini-chat-assistant' ); ?></label>
						<input type="text" id="gca_placeholder" name="gca_settings[placeholder]" value="<?php echo esc_attr( $settings['placeholder'] ); ?>" />
					</div>
				</div>
			</div>
		</div>

		<!-- Panel: AI & Model -->
		<div class="gca-tab-panel" id="gca-panel-ai">
			<div class="gca-section-card">
				<h3><?php esc_html_e( 'Google Gemini AI Configuration', 'gemini-chat-assistant' ); ?></h3>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label for="gca_model"><?php esc_html_e( 'Model Slug', 'gemini-chat-assistant' ); ?></label>
						<input type="text" id="gca_model" name="gca_settings[model]" value="<?php echo esc_attr( $settings['model'] ); ?>" />
						<span class="description"><?php esc_html_e( 'Configured Gemini model (default: gemini-3.8-flash).', 'gemini-chat-assistant' ); ?></span>
					</div>

					<?php
					$profile_service = new \SkyFish\GeminiChat\Admin\ProfileService();
					$active_profile  = $profile_service->get_active_profile();
					$active_name     = $active_profile['name'] ?? __( 'General Assistant', 'gemini-chat-assistant' );
					$ai_url          = admin_url( 'admin.php?page=gca-ai-assistant' );
					?>
					<div class="gca-field-row" style="background: #f6f7f7; padding: 16px; border-radius: 6px; border: 1px solid #dcdcde;">
						<label style="font-weight: 600;"><?php esc_html_e( 'Active AI Persona & Instructions', 'gemini-chat-assistant' ); ?></label>
						<p style="margin: 4px 0 10px;">
							<strong><?php esc_html_e( 'Active Profile:', 'gemini-chat-assistant' ); ?></strong>
							<span class="gca-admin-pill gca-admin-pill--success" style="font-size: 13px; margin-left: 6px;">
								<?php echo esc_html( $active_name ); ?>
							</span>
						</p>
						<p class="description" style="margin-bottom: 12px;">
							<?php esc_html_e( 'AI personas, instructions, tone, and behavioral rules are now centrally managed under AI Assistant.', 'gemini-chat-assistant' ); ?>
						</p>
						<a href="<?php echo esc_url( $ai_url ); ?>" class="button button-secondary">
							<span class="dashicons dashicons-superhero" style="vertical-align: middle; margin-right: 4px;"></span>
							<?php esc_html_e( 'Manage AI Profiles & Prompts &rarr;', 'gemini-chat-assistant' ); ?>
						</a>
						<!-- Hidden passthrough fields to protect existing configuration during general settings save -->
						<input type="hidden" name="gca_settings[system_instruction]" value="<?php echo esc_attr( $settings['system_instruction'] ); ?>" />
						<?php if ( ! empty( $settings['active_profile_id'] ) ) : ?>
							<input type="hidden" name="gca_settings[active_profile_id]" value="<?php echo esc_attr( $settings['active_profile_id'] ); ?>" />
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<!-- Panel: Widget Display -->
		<div class="gca-tab-panel" id="gca-panel-widget">
			<div class="gca-section-card">
				<h3><?php esc_html_e( 'Widget & Placement Options', 'gemini-chat-assistant' ); ?></h3>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[widget_enabled]" value="1" <?php checked( ! empty( $settings['widget_enabled'] ) ); ?> />
							<?php esc_html_e( 'Enable Floating Chat Bubble Widget', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[embedded_chat_enabled]" value="1" <?php checked( ! empty( $settings['embedded_chat_enabled'] ) ); ?> />
							<?php esc_html_e( 'Enable Embedded Chat [gemini_chat] Shortcode', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[desktop_enabled]" value="1" <?php checked( ! empty( $settings['desktop_enabled'] ) ); ?> />
							<?php esc_html_e( 'Display on Desktop Devices', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[mobile_enabled]" value="1" <?php checked( ! empty( $settings['mobile_enabled'] ) ); ?> />
							<?php esc_html_e( 'Display on Mobile Devices', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
				</div>
			</div>
		</div>

		<!-- Panel: Pre-Chat Form -->
		<div class="gca-tab-panel" id="gca-panel-prechat">
			<div class="gca-section-card">
				<h3><?php esc_html_e( 'Lead Capture & Pre-Chat Form Settings', 'gemini-chat-assistant' ); ?></h3>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[prechat_enabled]" value="1" <?php checked( ! empty( $settings['prechat_enabled'] ) ); ?> />
							<?php esc_html_e( 'Enable Pre-Chat Form Before Starting Conversation', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[collect_name]" value="1" <?php checked( ! empty( $settings['collect_name'] ) ); ?> />
							<?php esc_html_e( 'Collect Name', 'gemini-chat-assistant' ); ?>
						</label>
						<label class="gca-toggle-label" style="margin-left: 24px;">
							<input type="checkbox" name="gca_settings[require_name]" value="1" <?php checked( ! empty( $settings['require_name'] ) ); ?> />
							<?php esc_html_e( 'Require Name (Mandatory)', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[collect_email]" value="1" <?php checked( ! empty( $settings['collect_email'] ) ); ?> />
							<?php esc_html_e( 'Collect Email Address', 'gemini-chat-assistant' ); ?>
						</label>
						<label class="gca-toggle-label" style="margin-left: 24px;">
							<input type="checkbox" name="gca_settings[require_email]" value="1" <?php checked( ! empty( $settings['require_email'] ) ); ?> />
							<?php esc_html_e( 'Require Email Address (Mandatory)', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[collect_phone]" value="1" <?php checked( ! empty( $settings['collect_phone'] ) ); ?> />
							<?php esc_html_e( 'Collect Phone Number', 'gemini-chat-assistant' ); ?>
						</label>
						<label class="gca-toggle-label" style="margin-left: 24px;">
							<input type="checkbox" name="gca_settings[require_phone]" value="1" <?php checked( ! empty( $settings['require_phone'] ) ); ?> />
							<?php esc_html_e( 'Require Phone Number (Mandatory)', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[collect_requirement]" value="1" <?php checked( ! empty( $settings['collect_requirement'] ) ); ?> />
							<?php esc_html_e( 'Collect Inquiry / Requirement Description', 'gemini-chat-assistant' ); ?>
						</label>
						<label class="gca-toggle-label" style="margin-left: 24px;">
							<input type="checkbox" name="gca_settings[require_requirement]" value="1" <?php checked( ! empty( $settings['require_requirement'] ) ); ?> />
							<?php esc_html_e( 'Require Inquiry (Mandatory)', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
				</div>
			</div>
		</div>

		<!-- Panel: FAQ & Content -->
		<div class="gca-tab-panel" id="gca-panel-faq">
			<div class="gca-section-card">
				<h3><?php esc_html_e( 'FAQ Suggestions & Content Guidance', 'gemini-chat-assistant' ); ?></h3>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[faq_enabled]" value="1" <?php checked( ! empty( $settings['faq_enabled'] ) ); ?> />
							<?php esc_html_e( 'Enable Quick FAQ Buttons in Chat Window', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[faq_show_home]" value="1" <?php checked( ! empty( $settings['faq_show_home'] ) ); ?> />
							<?php esc_html_e( 'Show FAQ Suggestions on Homepage', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
				</div>
			</div>
		</div>

		<!-- Panel: Access & Permissions -->
		<div class="gca-tab-panel" id="gca-panel-access">
			<div class="gca-section-card">
				<h3><?php esc_html_e( 'Access & User Authorization', 'gemini-chat-assistant' ); ?></h3>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[guest_access]" value="1" <?php checked( ! empty( $settings['guest_access'] ) ); ?> />
							<?php esc_html_e( 'Allow Guest (Logged-out) Visitors to Chat', 'gemini-chat-assistant' ); ?>
						</label>
						<span class="description"><?php esc_html_e( 'When disabled, only authenticated logged-in WordPress users can chat.', 'gemini-chat-assistant' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Panel: Limits & Throttling -->
		<div class="gca-tab-panel" id="gca-panel-limits">
			<div class="gca-section-card">
				<h3><?php esc_html_e( 'Rate Limits & Token Controls', 'gemini-chat-assistant' ); ?></h3>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label for="gca_max_message_length"><?php esc_html_e( 'Maximum User Message Length (Characters)', 'gemini-chat-assistant' ); ?></label>
						<input type="number" id="gca_max_message_length" name="gca_settings[max_message_length]" value="<?php echo esc_attr( $settings['max_message_length'] ); ?>" min="100" max="10000" />
						<span class="description"><?php esc_html_e( 'Protects server memory and model token budgets (100 to 10000 characters).', 'gemini-chat-assistant' ); ?></span>
					</div>
					<div class="gca-field-row">
						<label for="gca_rate_limit_5m"><?php esc_html_e( '5-Minute Rate Limit per IP', 'gemini-chat-assistant' ); ?></label>
						<input type="number" id="gca_rate_limit_5m" name="gca_settings[rate_limit_5m]" value="<?php echo esc_attr( $settings['rate_limit_5m'] ); ?>" min="1" max="500" />
						<span class="description"><?php esc_html_e( 'Maximum messages allowed from a single IP within any 5-minute rolling window.', 'gemini-chat-assistant' ); ?></span>
					</div>
					<div class="gca-field-row">
						<label for="gca_rate_limit_1h"><?php esc_html_e( 'Hourly Rate Limit per IP', 'gemini-chat-assistant' ); ?></label>
						<input type="number" id="gca_rate_limit_1h" name="gca_settings[rate_limit_1h]" value="<?php echo esc_attr( $settings['rate_limit_1h'] ); ?>" min="5" max="5000" />
						<span class="description"><?php esc_html_e( 'Maximum messages allowed from a single IP within any 1-hour window.', 'gemini-chat-assistant' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Panel: Notifications (N17.4) -->
		<div class="gca-tab-panel" id="gca-panel-notifications">
			<div class="gca-section-card">
				<h3><?php esc_html_e( 'Human Handoff Email Notifications', 'gemini-chat-assistant' ); ?></h3>
				<p class="description" style="margin-bottom: 16px;">
					<?php esc_html_e( 'Automatically notify administrators and support personnel via WordPress native wp_mail() when a visitor requests human assistance.', 'gemini-chat-assistant' ); ?>
				</p>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[handoff_email_enabled]" value="1" <?php checked( ! empty( $settings['handoff_email_enabled'] ) ); ?> />
							<strong><?php esc_html_e( 'Enable Email Notifications for Handoff Requests', 'gemini-chat-assistant' ); ?></strong>
						</label>
						<span class="description"><?php esc_html_e( 'When enabled, new handoffs will trigger operational emails to configured team recipients.', 'gemini-chat-assistant' ); ?></span>
					</div>

					<div class="gca-field-row">
						<label for="gca_handoff_email_recipients"><?php esc_html_e( 'Recipient Email Addresses', 'gemini-chat-assistant' ); ?></label>
						<textarea id="gca_handoff_email_recipients" name="gca_settings[handoff_email_recipients]" rows="3" class="large-text code" placeholder="support@example.com&#10;sales@example.com"><?php echo esc_textarea( $settings['handoff_email_recipients'] ?? '' ); ?></textarea>
						<span class="description">
							<?php esc_html_e( 'Enter up to 10 recipient email addresses (one per line or comma-separated). If left empty, defaults to WordPress admin email.', 'gemini-chat-assistant' ); ?>
						</span>
					</div>

					<div class="gca-field-row">
						<label for="gca_handoff_email_subject"><?php esc_html_e( 'Email Subject Line', 'gemini-chat-assistant' ); ?></label>
						<input type="text" id="gca_handoff_email_subject" name="gca_settings[handoff_email_subject]" value="<?php echo esc_attr( $settings['handoff_email_subject'] ?? 'New Chatbot Handoff Request' ); ?>" class="regular-text" maxlength="150" />
						<span class="description"><?php esc_html_e( 'Subject line for internal notification emails (maximum 150 characters).', 'gemini-chat-assistant' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Test Email Dispatch Card -->
			<div class="gca-section-card" style="margin-top: 20px; border-top: 1px solid #f0f0f1; padding-top: 16px;">
				<h4><?php esc_html_e( 'Test WordPress Mail Transport', 'gemini-chat-assistant' ); ?></h4>
				<p class="description" style="margin-bottom: 12px;">
					<?php esc_html_e( 'Send a verification email to your configured recipient(s) to confirm WordPress wp_mail() delivery.', 'gemini-chat-assistant' ); ?>
				</p>
				<button type="submit" name="action" value="gca_send_test_email" class="button" formmethod="post" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onclick="this.form.querySelector('[name=action]').value='gca_send_test_email';">
					<span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Send Test Email', 'gemini-chat-assistant' ); ?>
				</button>
				<?php wp_nonce_field( 'gca_send_test_email', '_wpnonce_test_email' ); ?>
			</div>
		</div>

		<!-- Panel: Privacy & Retention -->
		<div class="gca-tab-panel" id="gca-panel-privacy">
			<div class="gca-section-card">
				<h3><?php esc_html_e( 'Privacy, Logs & Data Retention', 'gemini-chat-assistant' ); ?></h3>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[store_messages]" value="1" <?php checked( ! empty( $settings['store_messages'] ) ); ?> />
							<?php esc_html_e( 'Store Chat Message History in Database', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[store_leads]" value="1" <?php checked( ! empty( $settings['store_leads'] ) ); ?> />
							<?php esc_html_e( 'Store Pre-Chat Leads in Database', 'gemini-chat-assistant' ); ?>
						</label>
					</div>
					<div class="gca-field-row">
						<label for="gca_retention_days"><?php esc_html_e( 'Data Retention Window (Days)', 'gemini-chat-assistant' ); ?></label>
						<input type="number" id="gca_retention_days" name="gca_settings[retention_days]" value="<?php echo esc_attr( $settings['retention_days'] ); ?>" min="1" max="365" />
						<span class="description"><?php esc_html_e( 'Number of days to keep conversation history before automatic pruning.', 'gemini-chat-assistant' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<?php submit_button( __( 'Save All Settings', 'gemini-chat-assistant' ) ); ?>
	</form>
</div>
