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
				<p><?php esc_html_e( 'API key detected and loaded securely from server-side configuration / encrypted store.', 'gemini-chat-assistant' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'No API key detected. You can easily enter your Gemini API key under the "AI Providers" tab below, or define GCA_GEMINI_API_KEY in wp-config.php.', 'gemini-chat-assistant' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="gca-status-badge <?php echo $is_configured ? 'is-configured' : ''; ?>">
			<span class="dashicons <?php echo $is_configured ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
			<?php echo $is_configured ? esc_html__( 'Configured', 'gemini-chat-assistant' ) : esc_html__( 'Not Configured', 'gemini-chat-assistant' ); ?>
		</div>
	</div>

	<?php if ( ! $is_configured ) : ?>
		<div class="gca-instructions-box">
			<strong><?php esc_html_e( 'How to configure your Gemini API Key:', 'gemini-chat-assistant' ); ?></strong>
			<p><?php esc_html_e( '1. Recommended: Go to the "AI Providers" tab below, enter your Gemini API key in the Google Gemini card, and click "Save All Settings".', 'gemini-chat-assistant' ); ?></p>
			<p><?php esc_html_e( '2. Optional: Define GCA_GEMINI_API_KEY in wp-config.php, or export the GEMINI_API_KEY environment variable on your web server.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['key_removed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible gca-admin-notice">
			<p><?php printf( esc_html__( 'Stored API key for %s has been removed.', 'gemini-chat-assistant' ), esc_html( ucfirst( sanitize_key( (string) $_GET['key_removed'] ) ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['test_sent'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible gca-admin-notice">
			<p><?php esc_html_e( 'Test email accepted for sending by WordPress mail transport.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php elseif ( ! empty( $_GET['test_failed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-error is-dismissible gca-admin-notice">
			<p><?php esc_html_e( 'Test email could not be sent. WordPress wp_mail() returned false.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['gemini_test_status'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php if ( 'connected' === $_GET['gemini_test_status'] ) : ?>
			<div class="notice notice-success is-dismissible gca-admin-notice">
				<p><strong><?php esc_html_e( 'Google Gemini Connection Test:', 'gemini-chat-assistant' ); ?></strong> <?php esc_html_e( 'Connection verified successfully. API key and model verified.', 'gemini-chat-assistant' ); ?></p>
			</div>
		<?php else : ?>
			<?php
			$err_type = ! empty( $_GET['gemini_test_error'] ) ? sanitize_key( (string) $_GET['gemini_test_error'] ) : '';
			$err_msg  = __( 'Unable to connect to Google Gemini API. Please check your API key and configuration.', 'gemini-chat-assistant' );
			if ( 'auth_failed' === $err_type || 'authentication_error' === $err_type ) {
				$err_msg = __( 'The Gemini API key was rejected. Please check the key and API access.', 'gemini-chat-assistant' );
			} elseif ( 'model_unavailable' === $err_type ) {
				$err_msg = __( 'The selected Gemini model is unavailable. Please choose another model.', 'gemini-chat-assistant' );
			} elseif ( 'rate_limited' === $err_type || 'rate_limit' === $err_type || 'quota_error' === $err_type ) {
				$err_msg = __( 'Gemini rate limit or quota has been reached.', 'gemini-chat-assistant' );
			} elseif ( 'timeout' === $err_type ) {
				$err_msg = __( 'Your server connected too slowly to Google Gemini and the request timed out.', 'gemini-chat-assistant' );
			} elseif ( 'network' === $err_type || 'network_error' === $err_type ) {
				$err_msg = __( 'Your WordPress server could not reach Google Gemini.', 'gemini-chat-assistant' );
			} elseif ( 'server_error' === $err_type || 'unavailable' === $err_type ) {
				$err_msg = __( 'Google Gemini is temporarily unavailable.', 'gemini-chat-assistant' );
			} elseif ( 'not_configured' === $err_type ) {
				$err_msg = __( 'Google Gemini provider is disabled or no API key is configured.', 'gemini-chat-assistant' );
			}
			?>
			<div class="notice notice-error is-dismissible gca-admin-notice">
				<p><strong><?php esc_html_e( 'Google Gemini Connection Test Failed:', 'gemini-chat-assistant' ); ?></strong> <?php echo esc_html( $err_msg ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['gemini_models_status'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php if ( 'refreshed' === $_GET['gemini_models_status'] ) : ?>
			<div class="notice notice-success is-dismissible gca-admin-notice">
				<p><?php esc_html_e( 'Gemini models list successfully refreshed from Google Generative Language API.', 'gemini-chat-assistant' ); ?></p>
			</div>
		<?php else : ?>
			<div class="notice notice-error is-dismissible gca-admin-notice">
				<p><?php printf( esc_html__( 'Failed to refresh Gemini models from Google API: %s', 'gemini-chat-assistant' ), esc_html( (string) ( $_GET['gemini_models_error'] ?? __( 'Unknown error', 'gemini-chat-assistant' ) ) ) ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['openai_test_status'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php if ( 'connected' === $_GET['openai_test_status'] ) : ?>
			<div class="notice notice-success is-dismissible gca-admin-notice">
				<p><strong><?php esc_html_e( 'OpenAI Connection Test:', 'gemini-chat-assistant' ); ?></strong> <?php esc_html_e( 'Connected successfully to OpenAI Responses API. Model and credentials verified.', 'gemini-chat-assistant' ); ?></p>
			</div>
		<?php else : ?>
			<?php
			$err_type = ! empty( $_GET['openai_test_error'] ) ? sanitize_key( (string) $_GET['openai_test_error'] ) : '';
			$err_msg  = __( 'Unable to connect to OpenAI API. Please check your credentials and configuration.', 'gemini-chat-assistant' );
			if ( 'auth_failed' === $err_type || 'authentication_error' === $err_type ) {
				$err_msg = __( 'Authentication failed. Please verify your OpenAI API key.', 'gemini-chat-assistant' );
			} elseif ( 'model_unavailable' === $err_type ) {
				$err_msg = __( 'Configured model is unavailable for your account or does not exist.', 'gemini-chat-assistant' );
			} elseif ( 'rate_limited' === $err_type || 'rate_limit' === $err_type ) {
				$err_msg = __( 'OpenAI rate limit or quota reached. Please check your OpenAI account billing.', 'gemini-chat-assistant' );
			} elseif ( 'timeout' === $err_type ) {
				$err_msg = __( 'Connection to OpenAI timed out.', 'gemini-chat-assistant' );
			} elseif ( 'not_configured' === $err_type ) {
				$err_msg = __( 'OpenAI provider is disabled or no API key is configured.', 'gemini-chat-assistant' );
			}
			?>
			<div class="notice notice-error is-dismissible gca-admin-notice">
				<p><strong><?php esc_html_e( 'OpenAI Connection Test Failed:', 'gemini-chat-assistant' ); ?></strong> <?php echo esc_html( $err_msg ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['claude_test_status'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php if ( 'connected' === $_GET['claude_test_status'] ) : ?>
			<div class="notice notice-success is-dismissible gca-admin-notice">
				<p><strong><?php esc_html_e( 'Claude Connection Test:', 'gemini-chat-assistant' ); ?></strong> <?php esc_html_e( 'Connected successfully to Anthropic Messages API. Model and credentials verified.', 'gemini-chat-assistant' ); ?></p>
			</div>
		<?php else : ?>
			<?php
			$err_type = ! empty( $_GET['claude_test_error'] ) ? sanitize_key( (string) $_GET['claude_test_error'] ) : '';
			$err_msg  = __( 'Unable to connect to Anthropic Claude API. Please check your credentials and configuration.', 'gemini-chat-assistant' );
			if ( 'auth_failed' === $err_type || 'authentication_error' === $err_type ) {
				$err_msg = __( 'Authentication failed. Please verify your Anthropic Claude API key.', 'gemini-chat-assistant' );
			} elseif ( 'model_unavailable' === $err_type ) {
				$err_msg = __( 'Configured model is unavailable for your account or does not exist.', 'gemini-chat-assistant' );
			} elseif ( 'rate_limited' === $err_type || 'rate_limit' === $err_type ) {
				$err_msg = __( 'Anthropic rate limit reached. Please check your Anthropic account billing and tier limits.', 'gemini-chat-assistant' );
			} elseif ( 'timeout' === $err_type ) {
				$err_msg = __( 'Connection to Anthropic Claude timed out.', 'gemini-chat-assistant' );
			} elseif ( 'not_configured' === $err_type ) {
				$err_msg = __( 'Claude provider is disabled or no API key is configured.', 'gemini-chat-assistant' );
			}
			?>
			<div class="notice notice-error is-dismissible gca-admin-notice">
				<p><strong><?php esc_html_e( 'Claude Connection Test Failed:', 'gemini-chat-assistant' ); ?></strong> <?php echo esc_html( $err_msg ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'gca_settings_group' );
		?>

		<!-- Navigation Tabs -->
		<div class="gca-nav-tab-wrapper">
			<button type="button" class="gca-tab-btn active" data-tab="general"><?php esc_html_e( 'General', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="ai"><?php esc_html_e( 'AI Providers', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="widget"><?php esc_html_e( 'Widget Display', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="prechat"><?php esc_html_e( 'Pre-Chat Form', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="faq"><?php esc_html_e( 'FAQ & Content', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="access"><?php esc_html_e( 'Access', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="limits"><?php esc_html_e( 'Limits', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="notifications"><?php esc_html_e( 'Notifications', 'gemini-chat-assistant' ); ?></button>
			<button type="button" class="gca-tab-btn" data-tab="contact"><?php esc_html_e( 'Contact Channels', 'gemini-chat-assistant' ); ?></button>
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

		<!-- Panel: AI & Providers (N18) -->
		<div class="gca-tab-panel" id="gca-panel-ai">
			<?php
			$default_provider  = \SkyFish\GeminiChat\Admin\SettingsService::get_default_provider();

			$gemini_enabled    = \SkyFish\GeminiChat\Admin\SettingsService::is_provider_enabled( 'gemini' );
			$gemini_configured = \SkyFish\GeminiChat\Admin\SettingsService::is_provider_configured( 'gemini' );
			$gemini_source     = \SkyFish\GeminiChat\Admin\SettingsService::get_credential_source( 'gemini' );
			$gemini_model      = \SkyFish\GeminiChat\Admin\SettingsService::get_provider_model( 'gemini' );
			$gemini_info       = \SkyFish\GeminiChat\Admin\SettingsService::get_connection_status_info( 'gemini' );
			$gemini_masked_key = \SkyFish\GeminiChat\Admin\SettingsService::get_masked_provider_api_key( 'gemini' );

			$openai_enabled    = \SkyFish\GeminiChat\Admin\SettingsService::is_provider_enabled( 'openai' );
			$openai_configured = \SkyFish\GeminiChat\Admin\SettingsService::is_provider_configured( 'openai' );
			$openai_source     = \SkyFish\GeminiChat\Admin\SettingsService::get_credential_source( 'openai' );
			$openai_model      = \SkyFish\GeminiChat\Admin\SettingsService::get_provider_model( 'openai' );

			$claude_enabled    = \SkyFish\GeminiChat\Admin\SettingsService::is_provider_enabled( 'claude' );
			$claude_configured = \SkyFish\GeminiChat\Admin\SettingsService::is_provider_configured( 'claude' );
			$claude_source     = \SkyFish\GeminiChat\Admin\SettingsService::get_credential_source( 'claude' );
			$claude_model      = \SkyFish\GeminiChat\Admin\SettingsService::get_provider_model( 'claude' );
			?>

			<!-- Default Provider Selector -->
			<div class="gca-section-card">
				<h3><?php esc_html_e( 'Default AI Provider', 'gemini-chat-assistant' ); ?></h3>
				<p class="description" style="margin-bottom: 12px;">
					<?php esc_html_e( 'Select the primary AI provider used by the public chatbot.', 'gemini-chat-assistant' ); ?>
				</p>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label for="gca_default_provider"><?php esc_html_e( 'Default Provider', 'gemini-chat-assistant' ); ?></label>
						<select id="gca_default_provider" name="gca_settings[default_provider]">
							<option value="gemini" <?php selected( $default_provider, 'gemini' ); ?>><?php esc_html_e( 'Google Gemini', 'gemini-chat-assistant' ); ?></option>
							<option value="openai" <?php selected( $default_provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI', 'gemini-chat-assistant' ); ?></option>
							<option value="claude" <?php selected( $default_provider, 'claude' ); ?>><?php esc_html_e( 'Anthropic Claude', 'gemini-chat-assistant' ); ?></option>
						</select>
						<span class="description"><?php esc_html_e( 'Currently active provider for visitor chat responses.', 'gemini-chat-assistant' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Public Chat Controls (N21) -->
			<div class="gca-section-card" style="margin-top: 16px;">
				<h3><?php esc_html_e( 'Public Chat Controls', 'gemini-chat-assistant' ); ?></h3>
				<p class="description" style="margin-bottom: 12px;">
					<?php esc_html_e( 'Configure whether website visitors can select AI providers and models directly within the chat widget.', 'gemini-chat-assistant' ); ?>
				</p>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[allow_public_provider_selection]" value="1" <?php checked( ! empty( $settings['allow_public_provider_selection'] ) ); ?> />
							<strong><?php esc_html_e( 'Allow visitors to choose AI provider', 'gemini-chat-assistant' ); ?></strong>
						</label>
						<span class="description"><?php esc_html_e( 'When enabled, a provider dropdown appears in the chat widget containing active and configured providers.', 'gemini-chat-assistant' ); ?></span>
					</div>
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[allow_public_model_selection]" value="1" <?php checked( ! empty( $settings['allow_public_model_selection'] ) ); ?> />
							<strong><?php esc_html_e( 'Allow visitors to choose AI model', 'gemini-chat-assistant' ); ?></strong>
						</label>
						<span class="description"><?php esc_html_e( 'When enabled, a model dropdown appears in the chat widget displaying supported models for the active provider.', 'gemini-chat-assistant' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Google Gemini Card -->
			<div class="gca-section-card" style="margin-top: 16px;">
				<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f1; padding-bottom: 10px; margin-bottom: 14px;">
					<h3 style="margin: 0;"><?php esc_html_e( 'Google Gemini', 'gemini-chat-assistant' ); ?></h3>
					<?php
					$gemini_diag = get_option( 'gca_gemini_diagnostics', [] );
					$pill_class  = 'gca-admin-pill--warning';
					$pill_label  = __( 'Not Configured', 'gemini-chat-assistant' );
					if ( 'verified' === $gemini_info['status'] ) {
						$pill_class = 'gca-admin-pill--success';
						$pill_label = __( 'Connection Verified', 'gemini-chat-assistant' );
					} elseif ( 'failed' === $gemini_info['status'] ) {
						$pill_class = 'gca-admin-pill--danger';
						$pill_label = __( 'Connection Failed', 'gemini-chat-assistant' );
					} elseif ( $gemini_configured ) {
						$pill_class = 'gca-admin-pill--info';
						$pill_label = __( 'Configured', 'gemini-chat-assistant' );
					}
					?>
					<span class="gca-admin-pill <?php echo esc_attr( $pill_class ); ?>">
						<?php echo esc_html( $pill_label ); ?>
					</span>
				</div>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[provider_gemini_enabled]" value="1" <?php checked( $gemini_enabled ); ?> />
							<strong><?php esc_html_e( 'Enable Google Gemini', 'gemini-chat-assistant' ); ?></strong>
						</label>
					</div>

					<!-- Credential Source Selection (Dashboard Primary) -->
					<div class="gca-field-row">
						<label><strong><?php esc_html_e( 'Credential Source', 'gemini-chat-assistant' ); ?></strong></label>
						<?php
						$gemini_source_setting = \SkyFish\GeminiChat\Admin\SettingsService::get( 'provider_gemini_credential_source', 'dashboard' );
						?>
						<div style="margin-top: 6px; display: flex; flex-direction: column; gap: 8px;">
							<label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
								<input type="radio" name="gca_settings[provider_gemini_credential_source]" value="dashboard" <?php checked( 'dashboard', $gemini_source_setting ); ?> />
								<span><strong><?php esc_html_e( 'WordPress Dashboard', 'gemini-chat-assistant' ); ?></strong> (<?php esc_html_e( 'Primary / Recommended', 'gemini-chat-assistant' ); ?>)</span>
							</label>
							<label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
								<input type="radio" name="gca_settings[provider_gemini_credential_source]" value="server" <?php checked( 'server', $gemini_source_setting ); ?> />
								<span><?php esc_html_e( 'Server Constant / Advanced (GCA_GEMINI_API_KEY in wp-config.php)', 'gemini-chat-assistant' ); ?></span>
							</label>
						</div>
						<span class="description" style="display: block; margin-top: 6px;">
							<?php if ( 'server' === $gemini_source_setting ) : ?>
								<strong><?php esc_html_e( 'API Key Source:', 'gemini-chat-assistant' ); ?></strong> <?php esc_html_e( 'Server Configuration', 'gemini-chat-assistant' ); ?>
								<?php if ( ! defined( 'GCA_GEMINI_API_KEY' ) && empty( getenv( 'GEMINI_API_KEY' ) ) ) : ?>
									<br><span style="color: #dc2626;"><?php esc_html_e( 'Server API key is not configured.', 'gemini-chat-assistant' ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<strong><?php esc_html_e( 'API Key Source:', 'gemini-chat-assistant' ); ?></strong> <?php esc_html_e( 'WordPress Dashboard', 'gemini-chat-assistant' ); ?>
							<?php endif; ?>
						</span>
					</div>

					<!-- API Key Input -->
					<div class="gca-field-row" id="gca-row-gemini-api-key">
						<label for="gca_api_key_gemini"><?php esc_html_e( 'API Key', 'gemini-chat-assistant' ); ?></label>
						<div style="display: flex; gap: 8px; align-items: center; max-width: 550px;">
							<input type="password" id="gca_api_key_gemini" name="gca_settings[api_key_gemini]" value="" autocomplete="new-password" class="regular-text gca-password-input" style="flex: 1;" placeholder="<?php echo ! empty( $gemini_masked_key ) ? esc_attr( $gemini_masked_key ) : esc_attr__( 'Enter Gemini API key (AIza...)', 'gemini-chat-assistant' ); ?>" />
							<button type="button" class="button button-secondary gca-toggle-visibility-btn" data-target="gca_api_key_gemini" title="<?php esc_attr_e( 'Show/Hide typed key', 'gemini-chat-assistant' ); ?>">
								<span class="dashicons dashicons-visibility"></span>
							</button>
						</div>
						<span class="description" style="display: block; margin-top: 6px;">
							<?php
							if ( 'server' === $gemini_source_setting ) {
								if ( defined( 'GCA_GEMINI_API_KEY' ) || ! empty( getenv( 'GEMINI_API_KEY' ) ) ) {
									esc_html_e( 'API key is loaded from server configuration (constant/environment).', 'gemini-chat-assistant' );
								} else {
									esc_html_e( 'Server API key is not configured. Define GCA_GEMINI_API_KEY in wp-config.php or switch to WordPress Dashboard.', 'gemini-chat-assistant' );
								}
							} elseif ( \SkyFish\GeminiChat\Admin\SettingsService::has_stored_credential( 'gemini' ) ) {
								esc_html_e( 'API key is saved in WordPress Dashboard (encrypted AES-256). To replace it, enter a new key above and click Save All Settings.', 'gemini-chat-assistant' );
							} elseif ( $gemini_configured ) {
								esc_html_e( 'API key is available through server configuration fallback. Enter and save a key above to use a dashboard credential.', 'gemini-chat-assistant' );
							} else {
								esc_html_e( 'No API key configured. Enter your Google Gemini API key above and click Save All Settings.', 'gemini-chat-assistant' );
							}
							?>
						</span>
						<p class="description" style="margin-top: 4px; color: #646970;">
							<em><?php esc_html_e( 'Your API key is stored securely on your WordPress site and is never sent to website visitors.', 'gemini-chat-assistant' ); ?></em>
						</p>
					</div>

					<!-- Model Selection & Dynamic Refresh -->
					<div class="gca-field-row">
						<label for="gca_provider_gemini_model"><?php esc_html_e( 'Model', 'gemini-chat-assistant' ); ?></label>
						<div style="display: flex; gap: 8px; align-items: center; max-width: 550px; flex-wrap: wrap;">
							<select id="gca_provider_gemini_model" name="gca_settings[provider_gemini_model]" style="flex: 1; min-width: 250px;">
								<?php foreach ( \SkyFish\GeminiChat\Providers\ModelRegistry::get_models_for_provider( 'gemini' ) as $m ) : ?>
									<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $gemini_model, $m['id'] ); ?>>
										<?php echo esc_html( $m['name'] . ( ! empty( $m['recommended'] ) ? ' (' . __( 'Recommended', 'gemini-chat-assistant' ) . ')' : '' ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php if ( $gemini_configured ) : ?>
								<button type="submit" form="gca-refresh-models-gemini-form" class="button button-secondary">
									<span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 2px;"></span>
									<?php esc_html_e( 'Refresh Available Models', 'gemini-chat-assistant' ); ?>
								</button>
							<?php endif; ?>
						</div>
						<span class="description" style="display: block; margin-top: 4px;">
							<?php esc_html_e( 'Select the Gemini model for chat responses. Click "Refresh Available Models" to query models dynamically from Google API.', 'gemini-chat-assistant' ); ?>
						</span>
					</div>

					<!-- Connection Actions & Diagnostic Panel -->
					<div class="gca-field-row" style="border-top: 1px solid #f0f0f1; padding-top: 14px;">
						<div style="margin-bottom: 12px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
							<?php if ( $gemini_configured ) : ?>
								<button type="submit" form="gca-test-gemini-form" class="button button-secondary">
									<span class="dashicons dashicons-admin-plugins" style="vertical-align: middle; margin-right: 2px;"></span>
									<?php esc_html_e( 'Test Connection', 'gemini-chat-assistant' ); ?>
								</button>
							<?php endif; ?>
							<?php if ( \SkyFish\GeminiChat\Admin\SettingsService::has_stored_credential( 'gemini' ) ) : ?>
								<button type="submit" form="gca-remove-key-form-gemini" class="button button-secondary button-small" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove the stored Gemini API key from database?', 'gemini-chat-assistant' ) ); ?>');">
									<span class="dashicons dashicons-trash" style="vertical-align: middle; margin-right: 2px;"></span>
									<?php esc_html_e( 'Remove API Key', 'gemini-chat-assistant' ); ?>
								</button>
							<?php endif; ?>
						</div>

						<!-- Diagnostics Result Table -->
						<div class="gca-diagnostic-box" style="padding: 14px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; max-width: 550px;">
							<h4 style="margin: 0 0 10px 0; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569;">
								<?php esc_html_e( 'Connection Diagnostics', 'gemini-chat-assistant' ); ?>
							</h4>
							<table style="width: 100%; font-size: 13px; line-height: 1.6; border-collapse: collapse;">
								<tr>
									<td style="color: #64748b; width: 45%;"><strong><?php esc_html_e( 'Gemini API Key:', 'gemini-chat-assistant' ); ?></strong></td>
									<td><?php echo $gemini_configured ? '<span style="color: #16a34a; font-weight: 600;">' . esc_html__( 'Configured', 'gemini-chat-assistant' ) . '</span>' : '<span style="color: #dc2626; font-weight: 600;">' . esc_html__( 'Not Configured', 'gemini-chat-assistant' ) . '</span>'; ?></td>
								</tr>
								<tr>
									<td style="color: #64748b;"><strong><?php esc_html_e( 'Credential Source:', 'gemini-chat-assistant' ); ?></strong></td>
									<td><?php echo esc_html( 'server' === $gemini_source_setting ? __( 'Server Configuration', 'gemini-chat-assistant' ) : __( 'WordPress Dashboard', 'gemini-chat-assistant' ) ); ?></td>
								</tr>
								<tr>
									<td style="color: #64748b;"><strong><?php esc_html_e( 'Google API Reachable:', 'gemini-chat-assistant' ); ?></strong></td>
									<td><?php echo esc_html( $gemini_diag['api_reachable'] ?? 'Not Tested' ); ?></td>
								</tr>
								<tr>
									<td style="color: #64748b;"><strong><?php esc_html_e( 'Authentication:', 'gemini-chat-assistant' ); ?></strong></td>
									<td><?php echo esc_html( $gemini_diag['authentication'] ?? 'Not Tested' ); ?></td>
								</tr>
								<tr>
									<td style="color: #64748b;"><strong><?php esc_html_e( 'Selected Model:', 'gemini-chat-assistant' ); ?></strong></td>
									<td><code><?php echo esc_html( $gemini_model ); ?></code></td>
								</tr>
								<tr>
									<td style="color: #64748b;"><strong><?php esc_html_e( 'Model Available:', 'gemini-chat-assistant' ); ?></strong></td>
									<td><?php echo esc_html( $gemini_diag['model_available'] ?? 'Not Tested' ); ?></td>
								</tr>
								<tr>
									<td style="color: #64748b;"><strong><?php esc_html_e( 'Generation Test:', 'gemini-chat-assistant' ); ?></strong></td>
									<td><?php echo esc_html( $gemini_diag['generation_test'] ?? 'Not Tested' ); ?></td>
								</tr>
								<tr>
									<td style="color: #64748b;"><strong><?php esc_html_e( 'Last Error:', 'gemini-chat-assistant' ); ?></strong></td>
									<td style="color: <?php echo ( ! empty( $gemini_diag['last_error'] ) && false === stripos( $gemini_diag['last_error'], 'verified' ) ) ? '#dc2626' : '#16a34a'; ?>;">
										<?php echo esc_html( $gemini_diag['last_error'] ?? ( $gemini_configured ? __( 'None', 'gemini-chat-assistant' ) : __( 'API key not configured.', 'gemini-chat-assistant' ) ) ); ?>
									</td>
								</tr>
								<?php foreach ( [ 'http_status' => __( 'HTTP Status', 'gemini-chat-assistant' ), 'google_error_code' => __( 'Google Error Code', 'gemini-chat-assistant' ), 'google_error_status' => __( 'Google Error Status', 'gemini-chat-assistant' ), 'google_error_message' => __( 'Google Error Message', 'gemini-chat-assistant' ), 'endpoint' => __( 'Endpoint', 'gemini-chat-assistant' ) ] as $diag_key => $diag_label ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $diag_label ); ?>:</strong></td>
									<td style="overflow-wrap: anywhere;"><?php echo esc_html( $gemini_diag[ $diag_key ] ?? 'Not recorded' ); ?></td>
								</tr>
								<?php endforeach; ?>
							</table>
							<?php $chat_server_error = get_option( 'gca_chat_server_error', [] ); ?>
							<?php if ( is_array( $chat_server_error ) && ! empty( $chat_server_error ) ) : ?>
							<h4><?php esc_html_e( 'Last Chat Server Error', 'gemini-chat-assistant' ); ?></h4>
							<p class="description"><?php esc_html_e( 'Recorded PHP exception location. This is separate from Google API errors; use the server PHP log for the full cause.', 'gemini-chat-assistant' ); ?></p>
							<table style="width: 100%; table-layout: fixed;">
							<?php foreach ( [ 'checked_at' => 'Recorded At', 'request_id' => 'Request ID', 'error_class' => 'PHP Error Type', 'file' => 'File', 'line' => 'Line' ] as $field => $label ) : ?>
							<tr><td><?php echo esc_html( $label ); ?></td><td style="overflow-wrap: anywhere;"><?php echo esc_html( $chat_server_error[ $field ] ?? '' ); ?></td></tr>
							<?php endforeach; ?>
							</table>
							<?php endif; ?>
							<h4><?php esc_html_e( 'Latest Chat Generation', 'gemini-chat-assistant' ); ?></h4>
							<p class="description"><?php esc_html_e( 'This is the chat request result, separate from Test Connection. Send a chat message, then reload this settings page.', 'gemini-chat-assistant' ); ?></p>
							<?php $gemini_chat_diag = get_option( 'gca_gemini_last_chat', [] ); ?>
							<?php if ( empty( $gemini_chat_diag ) ) : ?>
								<p><?php esc_html_e( 'No separate chat result recorded yet.', 'gemini-chat-assistant' ); ?></p>
								<?php $gemini_chat_diag = get_option( 'gca_gemini_last_generation', [] ); ?>
								<?php if ( ! empty( $gemini_chat_diag ) ) : ?><p><?php esc_html_e( 'Previous generation record (may be an admin test):', 'gemini-chat-assistant' ); ?></p><?php endif; ?>
							<?php endif; ?>
							<?php if ( is_array( $gemini_chat_diag ) && ! empty( $gemini_chat_diag ) ) : ?>
							<table style="width: 100%; font-size: 13px; text-align: left; table-layout: fixed;">
								<?php foreach ( [ 'checked_at' => __( 'Recorded At', 'gemini-chat-assistant' ), 'generation_result' => __( 'Generation Result', 'gemini-chat-assistant' ), 'request_id' => __( 'Request ID', 'gemini-chat-assistant' ), 'selected_model' => __( 'Selected Model', 'gemini-chat-assistant' ), 'http_status' => __( 'Google HTTP Status', 'gemini-chat-assistant' ), 'google_error_code' => __( 'Google Error Code', 'gemini-chat-assistant' ), 'google_error_status' => __( 'Google Error Status', 'gemini-chat-assistant' ), 'google_error_message' => __( 'Google Error Message', 'gemini-chat-assistant' ), 'connection_error' => __( 'Connection Error', 'gemini-chat-assistant' ), 'last_error' => __( 'Last Error', 'gemini-chat-assistant' ), 'endpoint' => __( 'Endpoint', 'gemini-chat-assistant' ) ] as $diag_key => $diag_label ) : ?>
								<tr><td><strong><?php echo esc_html( $diag_label ); ?>:</strong></td><td style="overflow-wrap: anywhere;"><?php echo esc_html( $gemini_chat_diag[ $diag_key ] ?? 'Not recorded' ); ?></td></tr>
								<?php endforeach; ?>
							</table>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<!-- OpenAI Card -->
			<div class="gca-section-card" style="margin-top: 16px;">
				<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f1; padding-bottom: 10px; margin-bottom: 14px;">
					<h3 style="margin: 0;"><?php esc_html_e( 'OpenAI', 'gemini-chat-assistant' ); ?></h3>
					<span class="gca-admin-pill <?php echo $openai_configured ? 'gca-admin-pill--success' : 'gca-admin-pill--warning'; ?>">
						<?php echo $openai_configured ? esc_html__( 'Configured', 'gemini-chat-assistant' ) : esc_html__( 'Not Configured', 'gemini-chat-assistant' ); ?>
					</span>
				</div>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[provider_openai_enabled]" value="1" <?php checked( $openai_enabled ); ?> />
							<strong><?php esc_html_e( 'Enable OpenAI', 'gemini-chat-assistant' ); ?></strong>
						</label>
					</div>
					<div class="gca-field-row">
						<label for="gca_provider_openai_model"><?php esc_html_e( 'Model', 'gemini-chat-assistant' ); ?></label>
						<select id="gca_provider_openai_model" name="gca_settings[provider_openai_model]">
							<?php foreach ( \SkyFish\GeminiChat\Providers\ModelRegistry::get_models_for_provider( 'openai' ) as $m ) : ?>
								<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $openai_model, $m['id'] ); ?>>
									<?php echo esc_html( $m['name'] . ( ! empty( $m['recommended'] ) ? ' (' . __( 'Recommended', 'gemini-chat-assistant' ) . ')' : '' ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="gca-field-row">
						<label for="gca_api_key_openai"><?php esc_html_e( 'API Key', 'gemini-chat-assistant' ); ?></label>
						<input type="password" id="gca_api_key_openai" name="gca_settings[api_key_openai]" value="" autocomplete="new-password" class="regular-text" placeholder="<?php echo $openai_configured ? esc_attr__( 'API key configured. Enter new key to update...', 'gemini-chat-assistant' ) : esc_attr__( 'Enter OpenAI API key (sk-...)...', 'gemini-chat-assistant' ); ?>" />
						<span class="description">
							<?php
							if ( 'environment' === $openai_source ) {
								esc_html_e( 'Credentials loaded securely from OPENAI_API_KEY environment variable.', 'gemini-chat-assistant' );
							} elseif ( 'constant' === $openai_source ) {
								esc_html_e( 'Credentials loaded securely from GCA_OPENAI_API_KEY constant in wp-config.php.', 'gemini-chat-assistant' );
							} elseif ( 'database' === $openai_source ) {
								esc_html_e( 'Credentials securely stored in database (encrypted AES-256).', 'gemini-chat-assistant' );
							} else {
								esc_html_e( 'No API key configured. API keys are never echoed back in HTML.', 'gemini-chat-assistant' );
							}
							?>
						</span>
						<p class="description" style="margin-top: 4px; color: #646970;">
							<em><?php esc_html_e( 'OpenAI Responses API integration active (Node N19). Uses POST https://api.openai.com/v1/responses with server-side credentials.', 'gemini-chat-assistant' ); ?></em>
						</p>
						<div style="margin-top: 8px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
							<?php if ( $openai_configured ) : ?>
								<button type="submit" form="gca-test-openai-form" class="button button-secondary">
									<span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 2px;"></span>
									<?php esc_html_e( 'Test OpenAI Connection', 'gemini-chat-assistant' ); ?>
								</button>
							<?php endif; ?>
							<?php if ( 'database' === $openai_source ) : ?>
								<button type="submit" form="gca-remove-key-form-openai" class="button button-secondary button-small" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove the stored OpenAI API key from database?', 'gemini-chat-assistant' ) ); ?>');">
									<span class="dashicons dashicons-trash" style="vertical-align: middle; margin-right: 2px;"></span>
									<?php esc_html_e( 'Remove Stored API Key', 'gemini-chat-assistant' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<!-- Anthropic Claude Card -->
			<div class="gca-section-card" style="margin-top: 16px;">
				<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f1; padding-bottom: 10px; margin-bottom: 14px;">
					<h3 style="margin: 0;"><?php esc_html_e( 'Anthropic Claude', 'gemini-chat-assistant' ); ?></h3>
					<span class="gca-admin-pill <?php echo $claude_configured ? 'gca-admin-pill--success' : 'gca-admin-pill--warning'; ?>">
						<?php echo $claude_configured ? esc_html__( 'Configured', 'gemini-chat-assistant' ) : esc_html__( 'Not Configured', 'gemini-chat-assistant' ); ?>
					</span>
				</div>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[provider_claude_enabled]" value="1" <?php checked( $claude_enabled ); ?> />
							<strong><?php esc_html_e( 'Enable Anthropic Claude', 'gemini-chat-assistant' ); ?></strong>
						</label>
					</div>
					<div class="gca-field-row">
						<label for="gca_provider_claude_model"><?php esc_html_e( 'Model', 'gemini-chat-assistant' ); ?></label>
						<select id="gca_provider_claude_model" name="gca_settings[provider_claude_model]">
							<?php foreach ( \SkyFish\GeminiChat\Providers\ModelRegistry::get_models_for_provider( 'claude' ) as $m ) : ?>
								<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $claude_model, $m['id'] ); ?>>
									<?php echo esc_html( $m['name'] . ( ! empty( $m['recommended'] ) ? ' (' . __( 'Recommended', 'gemini-chat-assistant' ) . ')' : '' ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="gca-field-row">
						<label for="gca_api_key_claude"><?php esc_html_e( 'API Key', 'gemini-chat-assistant' ); ?></label>
						<input type="password" id="gca_api_key_claude" name="gca_settings[api_key_claude]" value="" autocomplete="new-password" class="regular-text" placeholder="<?php echo $claude_configured ? esc_attr__( 'API key configured. Enter new key to update...', 'gemini-chat-assistant' ) : esc_attr__( 'Enter Claude API key (sk-ant-...)...', 'gemini-chat-assistant' ); ?>" />
						<span class="description">
							<?php
							if ( 'environment' === $claude_source ) {
								esc_html_e( 'Credentials loaded securely from ANTHROPIC_API_KEY environment variable.', 'gemini-chat-assistant' );
							} elseif ( 'constant' === $claude_source ) {
								esc_html_e( 'Credentials loaded securely from GCA_CLAUDE_API_KEY / GCA_ANTHROPIC_API_KEY constant in wp-config.php.', 'gemini-chat-assistant' );
							} elseif ( 'database' === $claude_source ) {
								esc_html_e( 'Credentials securely stored in database (encrypted AES-256).', 'gemini-chat-assistant' );
							} else {
								esc_html_e( 'No API key configured. API keys are never echoed back in HTML.', 'gemini-chat-assistant' );
							}
							?>
						</span>
						<p class="description" style="margin-top: 4px; color: #646970;">
							<em><?php esc_html_e( 'Anthropic Messages API integration active (Node N20). Uses POST https://api.anthropic.com/v1/messages with server-side credentials.', 'gemini-chat-assistant' ); ?></em>
						</p>
						<div style="margin-top: 8px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
							<?php if ( $claude_configured ) : ?>
								<button type="submit" form="gca-test-claude-form" class="button button-secondary">
									<span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 2px;"></span>
									<?php esc_html_e( 'Test Claude Connection', 'gemini-chat-assistant' ); ?>
								</button>
							<?php endif; ?>
							<?php if ( 'database' === $claude_source ) : ?>
								<button type="submit" form="gca-remove-key-form-claude" class="button button-secondary button-small" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove the stored Claude API key from database?', 'gemini-chat-assistant' ) ); ?>');">
									<span class="dashicons dashicons-trash" style="vertical-align: middle; margin-right: 2px;"></span>
									<?php esc_html_e( 'Remove Stored API Key', 'gemini-chat-assistant' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<!-- Active AI Persona Card -->
			<div class="gca-section-card" style="margin-top: 16px;">
				<div class="gca-form-grid">
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
							<?php esc_html_e( 'AI personas, instructions, tone, and behavioral rules are centrally managed under AI Assistant.', 'gemini-chat-assistant' ); ?>
						</p>
						<a href="<?php echo esc_url( $ai_url ); ?>" class="button button-secondary">
							<span class="dashicons dashicons-superhero" style="vertical-align: middle; margin-right: 4px;"></span>
							<?php esc_html_e( 'Manage AI Profiles & Prompts &rarr;', 'gemini-chat-assistant' ); ?>
						</a>
						<!-- Passthrough hidden fields to preserve profile state -->
						<input type="hidden" name="gca_settings[system_instruction]" value="<?php echo esc_attr( $settings['system_instruction'] ?? '' ); ?>" />
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

		<!-- Panel: Direct Contact Channels (N17.5) -->
		<div class="gca-tab-panel" id="gca-panel-contact">
			<div class="gca-section-card">
				<h3><?php esc_html_e( 'Direct Contact Channels', 'gemini-chat-assistant' ); ?></h3>
				<p class="description" style="margin-bottom: 16px;">
					<?php esc_html_e( 'Configure direct contact links (Phone, Email, WhatsApp) displayed on the chatbot home screen so visitors can easily reach your team.', 'gemini-chat-assistant' ); ?>
				</p>
				<div class="gca-form-grid">
					<div class="gca-field-row">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[contact_channels_enabled]" value="1" <?php checked( ! empty( $settings['contact_channels_enabled'] ) ); ?> />
							<strong><?php esc_html_e( 'Enable Direct Contact Channels', 'gemini-chat-assistant' ); ?></strong>
						</label>
						<span class="description"><?php esc_html_e( 'Show contact options (Need More Help?) on the chatbot home screen.', 'gemini-chat-assistant' ); ?></span>
					</div>

					<!-- Phone / Call Channel -->
					<div class="gca-field-row" style="border-top: 1px solid #f0f0f1; padding-top: 14px;">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[contact_phone_enabled]" value="1" <?php checked( ! empty( $settings['contact_phone_enabled'] ) ); ?> />
							<strong><?php esc_html_e( 'Enable Phone / Call Channel', 'gemini-chat-assistant' ); ?></strong>
						</label>
					</div>
					<div class="gca-field-row">
						<label for="gca_contact_phone_number"><?php esc_html_e( 'Phone Number', 'gemini-chat-assistant' ); ?></label>
						<input type="tel" id="gca_contact_phone_number" name="gca_settings[contact_phone_number]" value="<?php echo esc_attr( $settings['contact_phone_number'] ?? '' ); ?>" class="regular-text" placeholder="+1 (555) 123-4567" />
						<span class="description"><?php esc_html_e( 'Telephone number for tel: link.', 'gemini-chat-assistant' ); ?></span>
					</div>
					<div class="gca-field-row">
						<label for="gca_contact_phone_label"><?php esc_html_e( 'Phone Button Label', 'gemini-chat-assistant' ); ?></label>
						<input type="text" id="gca_contact_phone_label" name="gca_settings[contact_phone_label]" value="<?php echo esc_attr( $settings['contact_phone_label'] ?? 'Call Us' ); ?>" class="regular-text" maxlength="50" />
					</div>

					<!-- Email Channel -->
					<div class="gca-field-row" style="border-top: 1px solid #f0f0f1; padding-top: 14px;">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[contact_email_enabled]" value="1" <?php checked( ! empty( $settings['contact_email_enabled'] ) ); ?> />
							<strong><?php esc_html_e( 'Enable Email Channel', 'gemini-chat-assistant' ); ?></strong>
						</label>
					</div>
					<div class="gca-field-row">
						<label for="gca_contact_email_address"><?php esc_html_e( 'Contact Email Address', 'gemini-chat-assistant' ); ?></label>
						<input type="email" id="gca_contact_email_address" name="gca_settings[contact_email_address]" value="<?php echo esc_attr( $settings['contact_email_address'] ?? '' ); ?>" class="regular-text" placeholder="contact@example.com" />
						<span class="description"><?php esc_html_e( 'Target address for mailto: link.', 'gemini-chat-assistant' ); ?></span>
					</div>
					<div class="gca-field-row">
						<label for="gca_contact_email_label"><?php esc_html_e( 'Email Button Label', 'gemini-chat-assistant' ); ?></label>
						<input type="text" id="gca_contact_email_label" name="gca_settings[contact_email_label]" value="<?php echo esc_attr( $settings['contact_email_label'] ?? 'Email Us' ); ?>" class="regular-text" maxlength="50" />
					</div>

					<!-- WhatsApp Channel -->
					<div class="gca-field-row" style="border-top: 1px solid #f0f0f1; padding-top: 14px;">
						<label class="gca-toggle-label">
							<input type="checkbox" name="gca_settings[contact_whatsapp_enabled]" value="1" <?php checked( ! empty( $settings['contact_whatsapp_enabled'] ) ); ?> />
							<strong><?php esc_html_e( 'Enable WhatsApp Channel', 'gemini-chat-assistant' ); ?></strong>
						</label>
					</div>
					<div class="gca-field-row">
						<label for="gca_contact_whatsapp_number"><?php esc_html_e( 'WhatsApp Phone Number (with country code)', 'gemini-chat-assistant' ); ?></label>
						<input type="tel" id="gca_contact_whatsapp_number" name="gca_settings[contact_whatsapp_number]" value="<?php echo esc_attr( $settings['contact_whatsapp_number'] ?? '' ); ?>" class="regular-text" placeholder="+15551234567" />
						<span class="description"><?php esc_html_e( 'Include international country code without spaces or dashes (e.g. 15551234567 or 919876543210).', 'gemini-chat-assistant' ); ?></span>
					</div>
					<div class="gca-field-row">
						<label for="gca_contact_whatsapp_label"><?php esc_html_e( 'WhatsApp Button Label', 'gemini-chat-assistant' ); ?></label>
						<input type="text" id="gca_contact_whatsapp_label" name="gca_settings[contact_whatsapp_label]" value="<?php echo esc_attr( $settings['contact_whatsapp_label'] ?? 'WhatsApp' ); ?>" class="regular-text" maxlength="50" />
					</div>
					<div class="gca-field-row">
						<label for="gca_contact_whatsapp_message"><?php esc_html_e( 'WhatsApp Pre-filled Message (Optional)', 'gemini-chat-assistant' ); ?></label>
						<textarea id="gca_contact_whatsapp_message" name="gca_settings[contact_whatsapp_message]" rows="2" class="large-text" maxlength="300"><?php echo esc_textarea( $settings['contact_whatsapp_message'] ?? '' ); ?></textarea>
						<span class="description"><?php esc_html_e( 'Pre-filled text that appears in WhatsApp when the visitor clicks the link.', 'gemini-chat-assistant' ); ?></span>
					</div>
				</div>
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

	<?php foreach ( \SkyFish\GeminiChat\Admin\SettingsService::ALLOWED_PROVIDERS as $provider_slug ) : ?>
		<?php if ( \SkyFish\GeminiChat\Admin\SettingsService::has_stored_credential( $provider_slug ) ) : ?>
			<form id="gca-remove-key-form-<?php echo esc_attr( $provider_slug ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: none;">
				<input type="hidden" name="action" value="gca_remove_provider_key" />
				<input type="hidden" name="provider" value="<?php echo esc_attr( $provider_slug ); ?>" />
				<?php wp_nonce_field( 'gca_remove_provider_key_' . $provider_slug ); ?>
			</form>
		<?php endif; ?>
	<?php endforeach; ?>

	<form id="gca-test-gemini-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: none;">
		<input type="hidden" name="action" value="gca_test_gemini_connection" />
		<?php wp_nonce_field( 'gca_test_gemini_connection' ); ?>
	</form>

	<form id="gca-refresh-models-gemini-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: none;">
		<input type="hidden" name="action" value="gca_refresh_gemini_models" />
		<?php wp_nonce_field( 'gca_refresh_gemini_models' ); ?>
	</form>

	<form id="gca-test-openai-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: none;">
		<input type="hidden" name="action" value="gca_test_openai_connection" />
		<?php wp_nonce_field( 'gca_test_openai_connection' ); ?>
	</form>

	<form id="gca-test-claude-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: none;">
		<input type="hidden" name="action" value="gca_test_claude_connection" />
		<?php wp_nonce_field( 'gca_test_claude_connection' ); ?>
	</form>
</div>
