<?php
/**
 * Admin Appearance Builder Template.
 *
 * @package SkyFish\GeminiChat\Templates
 */

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Admin\AppearanceService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array<string, mixed> $settings
 * @var string               $avatar_url
 */

$defaults = AppearanceService::get_defaults();
$s        = wp_parse_args( $settings, $defaults );

$avatar_id   = absint( $s['avatar_id'] ?? 0 );
$avatar_url  = AppearanceService::get_avatar_url( $avatar_id );
$position    = (string) ( $s['widget_position'] ?? 'bottom-right' );
$icon_choice = (string) ( $s['launcher_icon'] ?? 'chat' );
?>
<div class="wrap gca-admin-wrap">
	<!-- Admin Header -->
	<header class="gca-admin-header">
		<div class="gca-admin-header__info">
			<div class="gca-admin-header__icon" aria-hidden="true">
				<span class="dashicons dashicons-art"></span>
			</div>
			<div>
				<h1 class="gca-admin-header__title">
					<?php esc_html_e( 'Appearance Builder', 'gemini-chat-assistant' ); ?>
					<span class="gca-admin-badge"><?php esc_html_e( 'Visual Customizer', 'gemini-chat-assistant' ); ?></span>
				</h1>
				<p class="gca-admin-header__subtitle">
					<?php esc_html_e( 'Customize your AI chatbot branding, colors, dimensions, launcher icons, and device visibility.', 'gemini-chat-assistant' ); ?>
				</p>
			</div>
		</div>
	</header>

	<!-- Feedback Notices -->
	<?php if ( ! empty( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible gca-admin-notice">
			<p><?php esc_html_e( 'Appearance settings have been reset to defaults.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="gca-admin-appearance-layout">
		<!-- Left Column: Controls Form -->
		<div class="gca-admin-appearance-controls">
			<form method="post" action="options.php" id="gca-appearance-form">
				<?php
				settings_fields( 'gca_settings_group' );

				// Preserve non-appearance settings as hidden fields
				$all_saved = SettingsService::get_all();
				foreach ( $all_saved as $key => $val ) {
					if ( ! in_array( $key, AppearanceService::APPEARANCE_KEYS, true ) ) {
						if ( is_bool( $val ) ) {
							if ( $val ) {
								echo '<input type="hidden" name="' . esc_attr( SettingsService::OPTION_KEY . '[' . $key . ']' ) . '" value="1" />';
							}
						} elseif ( is_scalar( $val ) ) {
							echo '<input type="hidden" name="' . esc_attr( SettingsService::OPTION_KEY . '[' . $key . ']' ) . '" value="' . esc_attr( (string) $val ) . '" />';
						}
					}
				}
				?>

				<!-- Section 1: Chatbot Identity & Branding -->
				<section class="gca-section-card" aria-labelledby="gca-heading-identity">
					<h3 id="gca-heading-identity">
						<span class="dashicons dashicons-admin-users" style="color: #2271b1; vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Chatbot Identity & Branding', 'gemini-chat-assistant' ); ?>
					</h3>

					<div class="gca-form-grid">
						<!-- Assistant Name -->
						<div class="gca-field-row">
							<label for="gca-assistant-name"><?php esc_html_e( 'Assistant Name', 'gemini-chat-assistant' ); ?></label>
							<input
								type="text"
								id="gca-assistant-name"
								name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[assistant_name]"
								value="<?php echo esc_attr( (string) $s['assistant_name'] ); ?>"
								class="regular-text gca-preview-sync"
								data-target="assistant-name"
								required
							/>
							<p class="description"><?php esc_html_e( 'Displayed in the header and assistant response headers.', 'gemini-chat-assistant' ); ?></p>
						</div>

						<!-- Greeting -->
						<div class="gca-field-row">
							<label for="gca-greeting"><?php esc_html_e( 'Greeting Headline', 'gemini-chat-assistant' ); ?></label>
							<input
								type="text"
								id="gca-greeting"
								name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[greeting]"
								value="<?php echo esc_attr( (string) $s['greeting'] ); ?>"
								class="regular-text gca-preview-sync"
								data-target="greeting"
							/>
							<p class="description"><?php esc_html_e( 'Main welcoming headline on the Home screen (e.g. "Welcome! 👋").', 'gemini-chat-assistant' ); ?></p>
						</div>

						<!-- Welcome Message -->
						<div class="gca-field-row">
							<label for="gca-welcome-message"><?php esc_html_e( 'Welcome Description', 'gemini-chat-assistant' ); ?></label>
							<textarea
								id="gca-welcome-message"
								name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[welcome_message]"
								rows="2"
								class="large-text gca-preview-sync"
								data-target="welcome-message"
							><?php echo esc_textarea( (string) $s['welcome_message'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Subtext shown on Home screen and first message bubble.', 'gemini-chat-assistant' ); ?></p>
						</div>

						<!-- Avatar Upload -->
						<div class="gca-field-row">
							<label><?php esc_html_e( 'Assistant Avatar / Logo', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-avatar-uploader">
								<input
									type="hidden"
									id="gca-avatar-id"
									name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[avatar_id]"
									value="<?php echo esc_attr( (string) $avatar_id ); ?>"
								/>
								<div class="gca-avatar-preview-box" id="gca-avatar-preview-box">
									<?php if ( ! empty( $avatar_url ) ) : ?>
										<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" id="gca-avatar-preview-img" />
									<?php else : ?>
										<span class="dashicons dashicons-format-chat" id="gca-avatar-placeholder-icon"></span>
									<?php endif; ?>
								</div>
								<div class="gca-avatar-actions">
									<button type="button" class="button" id="gca-select-avatar-btn">
										<?php esc_html_e( 'Choose Image', 'gemini-chat-assistant' ); ?>
									</button>
									<button type="button" class="button button-link-delete" id="gca-remove-avatar-btn" style="<?php echo empty( $avatar_url ) ? 'display: none;' : ''; ?>">
										<?php esc_html_e( 'Remove Avatar', 'gemini-chat-assistant' ); ?>
									</button>
								</div>
							</div>
							<p class="description"><?php esc_html_e( 'Custom square PNG/JPEG/WEBP logo for your assistant header and message bubbles.', 'gemini-chat-assistant' ); ?></p>
						</div>
					</div>
				</section>

				<!-- Section 2: Color Palette Controls -->
				<section class="gca-section-card" aria-labelledby="gca-heading-colors">
					<h3 id="gca-heading-colors">
						<span class="dashicons dashicons-color-picker" style="color: #2271b1; vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Color Palette & Styling', 'gemini-chat-assistant' ); ?>
					</h3>

					<div class="gca-color-grid">
						<!-- Primary Brand Color -->
						<div class="gca-color-field">
							<label for="gca-color-primary"><?php esc_html_e( 'Primary Accent', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-color-input-wrap">
								<input type="color" class="gca-color-picker-native" value="<?php echo esc_attr( (string) $s['primary_color'] ); ?>" data-sync="gca-color-primary" />
								<input type="text" id="gca-color-primary" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[primary_color]" value="<?php echo esc_attr( (string) $s['primary_color'] ); ?>" class="gca-color-hex" pattern="^#([a-fA-F0-9]{3}){1,2}$" />
							</div>
						</div>

						<!-- Header Background -->
						<div class="gca-color-field">
							<label for="gca-color-header-bg"><?php esc_html_e( 'Header Background', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-color-input-wrap">
								<input type="color" class="gca-color-picker-native" value="<?php echo esc_attr( (string) $s['header_bg_color'] ); ?>" data-sync="gca-color-header-bg" />
								<input type="text" id="gca-color-header-bg" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[header_bg_color]" value="<?php echo esc_attr( (string) $s['header_bg_color'] ); ?>" class="gca-color-hex" />
							</div>
						</div>

						<!-- Header Text -->
						<div class="gca-color-field">
							<label for="gca-color-header-text"><?php esc_html_e( 'Header Text', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-color-input-wrap">
								<input type="color" class="gca-color-picker-native" value="<?php echo esc_attr( (string) $s['header_text_color'] ); ?>" data-sync="gca-color-header-text" />
								<input type="text" id="gca-color-header-text" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[header_text_color]" value="<?php echo esc_attr( (string) $s['header_text_color'] ); ?>" class="gca-color-hex" />
							</div>
						</div>

						<!-- Panel Background -->
						<div class="gca-color-field">
							<label for="gca-color-panel-bg"><?php esc_html_e( 'Panel Background', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-color-input-wrap">
								<input type="color" class="gca-color-picker-native" value="<?php echo esc_attr( (string) $s['panel_bg_color'] ); ?>" data-sync="gca-color-panel-bg" />
								<input type="text" id="gca-color-panel-bg" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[panel_bg_color]" value="<?php echo esc_attr( (string) $s['panel_bg_color'] ); ?>" class="gca-color-hex" />
							</div>
						</div>

						<!-- Main Text Color -->
						<div class="gca-color-field">
							<label for="gca-color-text"><?php esc_html_e( 'Main Body Text', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-color-input-wrap">
								<input type="color" class="gca-color-picker-native" value="<?php echo esc_attr( (string) $s['text_color'] ); ?>" data-sync="gca-color-text" />
								<input type="text" id="gca-color-text" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[text_color]" value="<?php echo esc_attr( (string) $s['text_color'] ); ?>" class="gca-color-hex" />
							</div>
						</div>

						<!-- Assistant Bubble -->
						<div class="gca-color-field">
							<label for="gca-color-ai-bubble"><?php esc_html_e( 'AI Bubble Background', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-color-input-wrap">
								<input type="color" class="gca-color-picker-native" value="<?php echo esc_attr( (string) $s['assistant_bubble_color'] ); ?>" data-sync="gca-color-ai-bubble" />
								<input type="text" id="gca-color-ai-bubble" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[assistant_bubble_color]" value="<?php echo esc_attr( (string) $s['assistant_bubble_color'] ); ?>" class="gca-color-hex" />
							</div>
						</div>

						<!-- Assistant Text -->
						<div class="gca-color-field">
							<label for="gca-color-ai-text"><?php esc_html_e( 'AI Bubble Text', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-color-input-wrap">
								<input type="color" class="gca-color-picker-native" value="<?php echo esc_attr( (string) $s['assistant_text_color'] ); ?>" data-sync="gca-color-ai-text" />
								<input type="text" id="gca-color-ai-text" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[assistant_text_color]" value="<?php echo esc_attr( (string) $s['assistant_text_color'] ); ?>" class="gca-color-hex" />
							</div>
						</div>

						<!-- User Bubble -->
						<div class="gca-color-field">
							<label for="gca-color-user-bubble"><?php esc_html_e( 'User Bubble Background', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-color-input-wrap">
								<input type="color" class="gca-color-picker-native" value="<?php echo esc_attr( (string) $s['user_bubble_color'] ); ?>" data-sync="gca-color-user-bubble" />
								<input type="text" id="gca-color-user-bubble" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[user_bubble_color]" value="<?php echo esc_attr( (string) $s['user_bubble_color'] ); ?>" class="gca-color-hex" />
							</div>
						</div>

						<!-- User Text -->
						<div class="gca-color-field">
							<label for="gca-color-user-text"><?php esc_html_e( 'User Bubble Text', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-color-input-wrap">
								<input type="color" class="gca-color-picker-native" value="<?php echo esc_attr( (string) $s['user_text_color'] ); ?>" data-sync="gca-color-user-text" />
								<input type="text" id="gca-color-user-text" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[user_text_color]" value="<?php echo esc_attr( (string) $s['user_text_color'] ); ?>" class="gca-color-hex" />
							</div>
						</div>

						<!-- Button Color -->
						<div class="gca-color-field">
							<label for="gca-color-button"><?php esc_html_e( 'Button Color', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-color-input-wrap">
								<input type="color" class="gca-color-picker-native" value="<?php echo esc_attr( (string) $s['button_color'] ); ?>" data-sync="gca-color-button" />
								<input type="text" id="gca-color-button" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[button_color]" value="<?php echo esc_attr( (string) $s['button_color'] ); ?>" class="gca-color-hex" />
							</div>
						</div>

						<!-- Launcher Background -->
						<div class="gca-color-field">
							<label for="gca-color-launcher-bg"><?php esc_html_e( 'Launcher Background', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-color-input-wrap">
								<input type="color" class="gca-color-picker-native" value="<?php echo esc_attr( (string) $s['launcher_bg_color'] ); ?>" data-sync="gca-color-launcher-bg" />
								<input type="text" id="gca-color-launcher-bg" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[launcher_bg_color]" value="<?php echo esc_attr( (string) $s['launcher_bg_color'] ); ?>" class="gca-color-hex" />
							</div>
						</div>

						<!-- Launcher Icon Color -->
						<div class="gca-color-field">
							<label for="gca-color-launcher-icon"><?php esc_html_e( 'Launcher Icon Color', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-color-input-wrap">
								<input type="color" class="gca-color-picker-native" value="<?php echo esc_attr( (string) $s['launcher_icon_color'] ); ?>" data-sync="gca-color-launcher-icon" />
								<input type="text" id="gca-color-launcher-icon" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[launcher_icon_color]" value="<?php echo esc_attr( (string) $s['launcher_icon_color'] ); ?>" class="gca-color-hex" />
							</div>
						</div>
					</div>
				</section>

				<!-- Section 3: Layout, Dimensions & Launcher -->
				<section class="gca-section-card" aria-labelledby="gca-heading-layout">
					<h3 id="gca-heading-layout">
						<span class="dashicons dashicons-layout" style="color: #2271b1; vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Layout, Dimensions & Launcher', 'gemini-chat-assistant' ); ?>
					</h3>

					<div class="gca-form-grid">
						<!-- Position -->
						<div class="gca-field-row">
							<label><?php esc_html_e( 'Widget Screen Position', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-radio-group">
								<label class="gca-radio-label">
									<input type="radio" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[widget_position]" value="bottom-right" <?php checked( $position, 'bottom-right' ); ?> />
									<span><?php esc_html_e( 'Bottom Right (Default)', 'gemini-chat-assistant' ); ?></span>
								</label>
								<label class="gca-radio-label">
									<input type="radio" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[widget_position]" value="bottom-left" <?php checked( $position, 'bottom-left' ); ?> />
									<span><?php esc_html_e( 'Bottom Left', 'gemini-chat-assistant' ); ?></span>
								</label>
							</div>
						</div>

						<!-- Launcher Icon Choice -->
						<div class="gca-field-row">
							<label><?php esc_html_e( 'Launcher Icon', 'gemini-chat-assistant' ); ?></label>
							<div class="gca-icon-choices">
								<label class="gca-icon-choice">
									<input type="radio" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[launcher_icon]" value="chat" <?php checked( $icon_choice, 'chat' ); ?> />
									<span class="gca-icon-choice__box" title="<?php esc_attr_e( 'Chat Bubble', 'gemini-chat-assistant' ); ?>">
										<span class="dashicons dashicons-format-chat"></span>
										<span><?php esc_html_e( 'Chat', 'gemini-chat-assistant' ); ?></span>
									</span>
								</label>
								<label class="gca-icon-choice">
									<input type="radio" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[launcher_icon]" value="message" <?php checked( $icon_choice, 'message' ); ?> />
									<span class="gca-icon-choice__box" title="<?php esc_attr_e( 'Message Envelope', 'gemini-chat-assistant' ); ?>">
										<span class="dashicons dashicons-email"></span>
										<span><?php esc_html_e( 'Message', 'gemini-chat-assistant' ); ?></span>
									</span>
								</label>
								<label class="gca-icon-choice">
									<input type="radio" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[launcher_icon]" value="headset" <?php checked( $icon_choice, 'headset' ); ?> />
									<span class="gca-icon-choice__box" title="<?php esc_attr_e( 'Support Headset', 'gemini-chat-assistant' ); ?>">
										<span class="dashicons dashicons-phone"></span>
										<span><?php esc_html_e( 'Headset', 'gemini-chat-assistant' ); ?></span>
									</span>
								</label>
								<label class="gca-icon-choice">
									<input type="radio" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[launcher_icon]" value="sparkle" <?php checked( $icon_choice, 'sparkle' ); ?> />
									<span class="gca-icon-choice__box" title="<?php esc_attr_e( 'AI Sparkle', 'gemini-chat-assistant' ); ?>">
										<span class="dashicons dashicons-star-filled"></span>
										<span><?php esc_html_e( 'Sparkle', 'gemini-chat-assistant' ); ?></span>
									</span>
								</label>
							</div>
						</div>

						<!-- Panel Dimensions Grid -->
						<div class="gca-dimensions-grid">
							<!-- Panel Width -->
							<div class="gca-field-row">
								<label for="gca-panel-width"><?php esc_html_e( 'Panel Width (px)', 'gemini-chat-assistant' ); ?></label>
								<input
									type="number"
									id="gca-panel-width"
									name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[panel_width]"
									value="<?php echo absint( $s['panel_width'] ); ?>"
									min="320"
									max="600"
									step="10"
								/>
								<span class="description"><?php esc_html_e( 'Desktop width (320 - 600px).', 'gemini-chat-assistant' ); ?></span>
							</div>

							<!-- Panel Height -->
							<div class="gca-field-row">
								<label for="gca-panel-height"><?php esc_html_e( 'Panel Height (px)', 'gemini-chat-assistant' ); ?></label>
								<input
									type="number"
									id="gca-panel-height"
									name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[panel_height]"
									value="<?php echo absint( $s['panel_height'] ); ?>"
									min="450"
									max="850"
									step="10"
								/>
								<span class="description"><?php esc_html_e( 'Desktop height (450 - 850px).', 'gemini-chat-assistant' ); ?></span>
							</div>

							<!-- Border Radius -->
							<div class="gca-field-row">
								<label for="gca-border-radius"><?php esc_html_e( 'Border Radius (px)', 'gemini-chat-assistant' ); ?></label>
								<input
									type="number"
									id="gca-border-radius"
									name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[border_radius]"
									value="<?php echo absint( $s['border_radius'] ); ?>"
									min="0"
									max="40"
									step="2"
								/>
								<span class="description"><?php esc_html_e( 'Corner curvature (0 - 40px).', 'gemini-chat-assistant' ); ?></span>
							</div>

							<!-- Launcher Size -->
							<div class="gca-field-row">
								<label for="gca-launcher-size"><?php esc_html_e( 'Launcher Button Size (px)', 'gemini-chat-assistant' ); ?></label>
								<input
									type="number"
									id="gca-launcher-size"
									name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[launcher_size]"
									value="<?php echo absint( $s['launcher_size'] ); ?>"
									min="44"
									max="80"
									step="2"
								/>
								<span class="description"><?php esc_html_e( 'Trigger button diameter (44 - 80px).', 'gemini-chat-assistant' ); ?></span>
							</div>
						</div>
					</div>
				</section>

				<!-- Section 4: Device & Responsive Visibility -->
				<section class="gca-section-card" aria-labelledby="gca-heading-devices">
					<h3 id="gca-heading-devices">
						<span class="dashicons dashicons-smartphone" style="color: #2271b1; vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Device & Viewport Visibility', 'gemini-chat-assistant' ); ?>
					</h3>

					<div class="gca-form-grid">
						<div class="gca-field-row">
							<label class="gca-toggle-label">
								<input type="checkbox" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[desktop_enabled]" value="1" <?php checked( ! empty( $s['desktop_enabled'] ) ); ?> />
								<span><?php esc_html_e( 'Show on Desktop screens (>1024px)', 'gemini-chat-assistant' ); ?></span>
							</label>
						</div>

						<div class="gca-field-row">
							<label class="gca-toggle-label">
								<input type="checkbox" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[tablet_enabled]" value="1" <?php checked( ! empty( $s['tablet_enabled'] ) ); ?> />
								<span><?php esc_html_e( 'Show on Tablet screens (601px - 1024px)', 'gemini-chat-assistant' ); ?></span>
							</label>
						</div>

						<div class="gca-field-row">
							<label class="gca-toggle-label">
								<input type="checkbox" name="<?php echo esc_attr( SettingsService::OPTION_KEY ); ?>[mobile_enabled]" value="1" <?php checked( ! empty( $s['mobile_enabled'] ) ); ?> />
								<span><?php esc_html_e( 'Show on Mobile screens (<=600px, fullscreen mode)', 'gemini-chat-assistant' ); ?></span>
							</label>
						</div>
					</div>
				</section>

				<!-- Save Button -->
				<div class="gca-form-actions" style="margin-top: 20px;">
					<?php submit_button( __( 'Save Appearance Settings', 'gemini-chat-assistant' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>

		<!-- Right Column: Sticky Live Preview & Reset Action -->
		<aside class="gca-admin-appearance-preview-sidebar">
			<div class="gca-admin-card gca-preview-card" style="position: sticky; top: 32px;">
				<h3 class="gca-admin-card__section-title">
					<span class="dashicons dashicons-visibility" style="color: #2271b1;"></span>
					<?php esc_html_e( 'Live Appearance Preview', 'gemini-chat-assistant' ); ?>
				</h3>

				<!-- Simulated Mock Chatbot Preview -->
				<div class="gca-mock-preview-wrap" id="gca-mock-preview-wrap">
					<!-- Mock Header -->
					<div class="gca-mock-header" style="background: <?php echo esc_attr( (string) $s['header_bg_color'] ); ?>; color: <?php echo esc_attr( (string) $s['header_text_color'] ); ?>;">
						<div class="gca-mock-header__avatar">
							<?php if ( ! empty( $avatar_url ) ) : ?>
								<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" id="gca-mock-avatar-img" />
							<?php else : ?>
								<span class="dashicons dashicons-format-chat" id="gca-mock-avatar-dashicon"></span>
							<?php endif; ?>
						</div>
						<div>
							<div class="gca-mock-header__title" id="gca-mock-name"><?php echo esc_html( (string) $s['assistant_name'] ); ?></div>
							<div class="gca-mock-header__status">● <?php esc_html_e( 'Online', 'gemini-chat-assistant' ); ?></div>
						</div>
					</div>

					<!-- Mock Body -->
					<div class="gca-mock-body" style="background: <?php echo esc_attr( (string) $s['panel_bg_color'] ); ?>; color: <?php echo esc_attr( (string) $s['text_color'] ); ?>;">
						<div class="gca-mock-greeting" id="gca-mock-greeting">
							<strong><?php echo esc_html( (string) $s['greeting'] ); ?> 👋</strong>
							<p id="gca-mock-desc"><?php echo esc_html( (string) $s['welcome_message'] ); ?></p>
						</div>

						<div class="gca-mock-bubbles">
							<!-- Assistant Bubble -->
							<div class="gca-mock-bubble gca-mock-bubble--ai" style="background: <?php echo esc_attr( (string) $s['assistant_bubble_color'] ); ?>; color: <?php echo esc_attr( (string) $s['assistant_text_color'] ); ?>;">
								<?php esc_html_e( 'Hello! How may I help you today?', 'gemini-chat-assistant' ); ?>
							</div>

							<!-- User Bubble -->
							<div class="gca-mock-bubble gca-mock-bubble--user" style="background: <?php echo esc_attr( (string) $s['user_bubble_color'] ); ?>; color: <?php echo esc_attr( (string) $s['user_text_color'] ); ?>;">
								<?php esc_html_e( 'I have a quick question about pricing.', 'gemini-chat-assistant' ); ?>
							</div>
						</div>

						<!-- Mock Action Button -->
						<div class="gca-mock-btn" style="background: <?php echo esc_attr( (string) $s['button_color'] ); ?>; color: <?php echo esc_attr( (string) $s['button_text_color'] ); ?>;">
							<?php esc_html_e( 'Start a Conversation', 'gemini-chat-assistant' ); ?>
						</div>
					</div>

					<!-- Mock Launcher Preview -->
					<div class="gca-mock-launcher-wrap">
						<div class="gca-mock-launcher" style="background: <?php echo esc_attr( (string) $s['launcher_bg_color'] ); ?>; color: <?php echo esc_attr( (string) $s['launcher_icon_color'] ); ?>;">
							<span class="dashicons dashicons-format-chat"></span>
						</div>
					</div>
				</div>

				<!-- Reset Defaults Action -->
				<div class="gca-reset-box" style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #f0f0f1; text-align: center;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Reset all chatbot appearance and branding settings to default values?', 'gemini-chat-assistant' ); ?>');">
						<input type="hidden" name="action" value="gca_reset_appearance" />
						<?php wp_nonce_field( 'gca_reset_appearance' ); ?>
						<button type="submit" class="button button-link-delete" style="color: #b32d2e;">
							<span class="dashicons dashicons-image-rotate" style="vertical-align: middle; margin-right: 4px;"></span>
							<?php esc_html_e( 'Reset Appearance to Defaults', 'gemini-chat-assistant' ); ?>
						</button>
					</form>
				</div>
			</div>
		</aside>
	</div>
</div>
