<?php
/**
 * Public Chatbot Widget Template.
 *
 * Supports both floating widget and embedded shortcode modes.
 *
 * @package SkyFish\GeminiChat\Templates
 *
 * @var string $mode        Widget mode: 'floating' or 'embedded'.
 * @var string $instance_id Unique DOM instance identifier.
 */

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Admin\AppearanceService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mode        = ! empty( $mode ) && in_array( $mode, [ 'floating', 'embedded' ], true ) ? $mode : 'floating';
$instance_id = ! empty( $instance_id ) ? $instance_id : ( function_exists( 'wp_unique_id' ) ? wp_unique_id( 'gca-widget-' ) : 'gca-widget-' . uniqid() );

$settings        = SettingsService::get_all();
$assistant_name  = (string) ( $settings['assistant_name'] ?? __( 'AI Assistant', 'gemini-chat-assistant' ) );
$greeting        = (string) ( $settings['greeting'] ?? __( 'Welcome!', 'gemini-chat-assistant' ) );
$welcome_message = (string) ( $settings['welcome_message'] ?? __( 'Hi! How can I help you today?', 'gemini-chat-assistant' ) );
$placeholder     = (string) ( $settings['placeholder'] ?? __( 'Type your message...', 'gemini-chat-assistant' ) );
$max_length          = absint( $settings['max_message_length'] ?? 1000 );
$avatar_id           = absint( $settings['avatar_id'] ?? 0 );
$avatar_url          = AppearanceService::get_avatar_url( $avatar_id );
$launcher_icon       = (string) ( $settings['launcher_icon'] ?? 'chat' );
$prechat_enabled     = ! empty( $settings['prechat_enabled'] );
$collect_name        = ! empty( $settings['collect_name'] );
$require_name        = ! empty( $settings['require_name'] );
$collect_email       = ! empty( $settings['collect_email'] );
$require_email       = ! empty( $settings['require_email'] );
$collect_phone       = ! empty( $settings['collect_phone'] );
$require_phone       = ! empty( $settings['require_phone'] );
$collect_requirement = ! empty( $settings['collect_requirement'] );
$require_requirement = ! empty( $settings['require_requirement'] );
$faq_enabled         = ! empty( $settings['faq_enabled'] );
$faq_home_limit      = absint( $settings['faq_home_limit'] ?? 6 );
$home_faqs           = [];
if ( $faq_enabled ) {
	$faq_repo  = new \SkyFish\GeminiChat\Database\FaqRepository();
	$home_faqs = $faq_repo->get_home_faqs( $faq_home_limit );
}

// Direct Contact Channels (N17.5).
$contact_channels_enabled = ! empty( $settings['contact_channels_enabled'] );
$contact_phone_enabled    = ! empty( $settings['contact_phone_enabled'] ) && ! empty( $settings['contact_phone_number'] );
$contact_phone_number     = (string) ( $settings['contact_phone_number'] ?? '' );
$contact_phone_label      = (string) ( $settings['contact_phone_label'] ?? __( 'Call Us', 'gemini-chat-assistant' ) );
$contact_phone_url        = ! empty( $contact_phone_number ) ? 'tel:' . preg_replace( '/[^0-9+]/', '', $contact_phone_number ) : '';

$contact_email_enabled    = ! empty( $settings['contact_email_enabled'] ) && ! empty( $settings['contact_email_address'] );
$contact_email_address    = sanitize_email( (string) ( $settings['contact_email_address'] ?? '' ) );
$contact_email_label      = (string) ( $settings['contact_email_label'] ?? __( 'Email Us', 'gemini-chat-assistant' ) );
$contact_email_url        = ! empty( $contact_email_address ) ? 'mailto:' . rawurlencode( $contact_email_address ) : '';

$contact_wa_enabled       = ! empty( $settings['contact_whatsapp_enabled'] ) && ! empty( $settings['contact_whatsapp_number'] );
$contact_wa_number        = preg_replace( '/[^0-9]/', '', (string) ( $settings['contact_whatsapp_number'] ?? '' ) );
$contact_wa_label         = (string) ( $settings['contact_whatsapp_label'] ?? __( 'WhatsApp', 'gemini-chat-assistant' ) );
$contact_wa_msg           = (string) ( $settings['contact_whatsapp_message'] ?? '' );
$contact_wa_url           = ! empty( $contact_wa_number )
	? 'https://wa.me/' . $contact_wa_number . ( ! empty( $contact_wa_msg ) ? '?text=' . rawurlencode( $contact_wa_msg ) : '' )
	: '';

$has_contact_channels = $contact_channels_enabled && ( $contact_phone_enabled || $contact_email_enabled || $contact_wa_enabled );
?>

<?php if ( 'floating' === $mode ) : ?>
<!-- Floating Chat Launcher Button -->
<button
	type="button"
	class="gca-launcher"
	aria-controls="<?php echo esc_attr( $instance_id ); ?>"
	aria-expanded="false"
	aria-label="<?php esc_attr_e( 'Open chat assistant', 'gemini-chat-assistant' ); ?>"
>
	<span class="gca-launcher__badge" aria-hidden="true" style="display: none;">0</span>
	<span class="gca-launcher__icon gca-launcher__icon--open" aria-hidden="true">
		<?php if ( 'message' === $launcher_icon ) : ?>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
				<polyline points="22,6 12,13 2,6"></polyline>
			</svg>
		<?php elseif ( 'headset' === $launcher_icon ) : ?>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
				<path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
			</svg>
		<?php elseif ( 'sparkle' === $launcher_icon ) : ?>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
			</svg>
		<?php else : // default 'chat' ?>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
			</svg>
		<?php endif; ?>
	</span>
	<span class="gca-launcher__icon gca-launcher__icon--close" aria-hidden="true">
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<line x1="18" y1="6" x2="6" y2="18"></line>
			<line x1="6" y1="6" x2="18" y2="18"></line>
		</svg>
	</span>
</button>
<?php endif; ?>

<!-- Chatbot Panel Container -->
<div
	id="<?php echo esc_attr( $instance_id ); ?>"
	class="gca-widget gca-widget--<?php echo esc_attr( $mode ); ?>"
	data-mode="<?php echo esc_attr( $mode ); ?>"
	aria-hidden="<?php echo 'floating' === $mode ? 'true' : 'false'; ?>"
>
	<!-- Header -->
	<header class="gca-header">
		<div class="gca-header__avatar" aria-hidden="true">
			<?php if ( ! empty( $avatar_url ) ) : ?>
				<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" class="gca-avatar-img" />
			<?php else : ?>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2 2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"></path>
					<rect x="3" y="8" width="18" height="12" rx="2"></rect>
					<circle cx="9" cy="14" r="1"></circle>
					<circle cx="15" cy="14" r="1"></circle>
				</svg>
			<?php endif; ?>
		</div>
		<div class="gca-header__titles">
			<h3 class="gca-header__title"><?php echo esc_html( $assistant_name ); ?></h3>
			<span class="gca-header__status">
				<span class="gca-header__status-dot" aria-hidden="true"></span>
				<?php esc_html_e( 'Online', 'gemini-chat-assistant' ); ?>
			</span>
		</div>
		<div class="gca-header__actions">
			<button
				type="button"
				class="gca-btn-action gca-btn-reset"
				title="<?php esc_attr_e( 'Reset Conversation', 'gemini-chat-assistant' ); ?>"
				aria-label="<?php esc_attr_e( 'Reset Conversation', 'gemini-chat-assistant' ); ?>"
			>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
					<path d="M21 3v5h-5"></path>
					<path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
					<path d="M8 16H3v5"></path>
				</svg>
			</button>
			<?php if ( 'floating' === $mode ) : ?>
			<button
				type="button"
				class="gca-btn-action gca-btn-close"
				title="<?php esc_attr_e( 'Close', 'gemini-chat-assistant' ); ?>"
				aria-label="<?php esc_attr_e( 'Close', 'gemini-chat-assistant' ); ?>"
			>
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
			<?php endif; ?>
		</div>
	</header>

	<!-- Main Body Screens -->
	<div class="gca-body">
		<!-- Screen 1: Home Screen -->
		<section class="gca-screen gca-screen--home gca-screen--active" aria-label="<?php esc_attr_e( 'Home Screen', 'gemini-chat-assistant' ); ?>">
			<div class="gca-home">
				<div class="gca-home__greeting">
					<h2 class="gca-home__headline"><?php echo esc_html( $greeting ); ?> 👋</h2>
					<p class="gca-home__subtext"><?php echo esc_html( $welcome_message ); ?></p>
				</div>

				<!-- Start Conversation Card -->
				<button type="button" class="gca-start-card" aria-label="<?php esc_attr_e( 'Start a Conversation', 'gemini-chat-assistant' ); ?>">
					<span class="gca-start-card__icon" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
						</svg>
					</span>
					<span class="gca-start-card__content">
						<span class="gca-start-card__title"><?php esc_html_e( 'Start a Conversation', 'gemini-chat-assistant' ); ?></span>
						<span class="gca-start-card__description"><?php esc_html_e( 'Ask us about products, services, or support.', 'gemini-chat-assistant' ); ?></span>
					</span>
					<span class="gca-start-card__arrow" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<polyline points="9 18 15 12 9 6"></polyline>
						</svg>
					</span>
				</button>

				<!-- Quick Help FAQ Area (N16) -->
				<?php if ( $faq_enabled && ! empty( $home_faqs ) ) : ?>
					<div class="gca-quick-help" role="region" aria-label="<?php esc_attr_e( 'Quick Help', 'gemini-chat-assistant' ); ?>">
						<h3 class="gca-quick-help__title"><?php esc_html_e( 'Quick Help', 'gemini-chat-assistant' ); ?></h3>
						<div class="gca-quick-help__list">
							<?php foreach ( $home_faqs as $faq ) : ?>
								<div class="gca-quick-help__item">
									<button
										type="button"
										class="gca-faq-trigger"
										data-faq-id="<?php echo esc_attr( (string) $faq['public_id'] ); ?>"
										data-question="<?php echo esc_attr( (string) $faq['question'] ); ?>"
										aria-label="<?php echo esc_attr( sprintf( __( 'Read FAQ: %s', 'gemini-chat-assistant' ), $faq['question'] ) ); ?>"
									>
										<span class="gca-faq-trigger__text"><?php echo esc_html( (string) $faq['question'] ); ?></span>
										<svg class="gca-faq-trigger__arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
											<polyline points="9 18 15 12 9 6"></polyline>
										</svg>
									</button>
									<template class="gca-faq-template-answer"><?php echo esc_html( (string) $faq['answer'] ); ?></template>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Direct Contact Channels (N17.5) -->
				<?php if ( $has_contact_channels ) : ?>
					<div class="gca-contact-channels" role="region" aria-label="<?php esc_attr_e( 'Direct Contact Options', 'gemini-chat-assistant' ); ?>">
						<h3 class="gca-contact-channels__title"><?php esc_html_e( 'Need More Help?', 'gemini-chat-assistant' ); ?></h3>
						<div class="gca-contact-channels__list">
							<?php if ( $contact_phone_enabled ) : ?>
								<a
									href="<?php echo esc_url( $contact_phone_url ); ?>"
									class="gca-contact-btn gca-contact-btn--phone"
									aria-label="<?php echo esc_attr( sprintf( __( 'Call us at %s', 'gemini-chat-assistant' ), $contact_phone_number ) ); ?>"
								>
									<span class="gca-contact-btn__icon" aria-hidden="true">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
											<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
										</svg>
									</span>
									<span class="gca-contact-btn__label"><?php echo esc_html( $contact_phone_label ); ?></span>
								</a>
							<?php endif; ?>

							<?php if ( $contact_email_enabled ) : ?>
								<a
									href="<?php echo esc_url( $contact_email_url ); ?>"
									class="gca-contact-btn gca-contact-btn--email"
									aria-label="<?php echo esc_attr( sprintf( __( 'Email us at %s', 'gemini-chat-assistant' ), $contact_email_address ) ); ?>"
								>
									<span class="gca-contact-btn__icon" aria-hidden="true">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
											<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
											<polyline points="22,6 12,13 2,6"></polyline>
										</svg>
									</span>
									<span class="gca-contact-btn__label"><?php echo esc_html( $contact_email_label ); ?></span>
								</a>
							<?php endif; ?>

							<?php if ( $contact_wa_enabled ) : ?>
								<a
									href="<?php echo esc_url( $contact_wa_url ); ?>"
									target="_blank"
									rel="noopener noreferrer"
									class="gca-contact-btn gca-contact-btn--whatsapp"
									aria-label="<?php esc_attr_e( 'Message us on WhatsApp', 'gemini-chat-assistant' ); ?>"
								>
									<span class="gca-contact-btn__icon" aria-hidden="true">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
											<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
										</svg>
									</span>
									<span class="gca-contact-btn__label"><?php echo esc_html( $contact_wa_label ); ?></span>
								</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<!-- Screen: FAQ Detail View (N16) -->
		<section class="gca-screen gca-screen--faq" aria-label="<?php esc_attr_e( 'FAQ Detail Screen', 'gemini-chat-assistant' ); ?>">
			<div class="gca-faq-detail">
				<div class="gca-faq-detail__header">
					<button type="button" class="gca-faq-detail__back" aria-label="<?php esc_attr_e( 'Back to Home screen', 'gemini-chat-assistant' ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<line x1="19" y1="12" x2="5" y2="12"></line>
							<polyline points="12 19 5 12 12 5"></polyline>
						</svg>
						<span><?php esc_html_e( 'Home', 'gemini-chat-assistant' ); ?></span>
					</button>
				</div>
				<div class="gca-faq-detail__body">
					<h2 class="gca-faq-detail__question" tabindex="-1"></h2>
					<div class="gca-faq-detail__answer"></div>
				</div>
				<div class="gca-faq-detail__footer">
					<p class="gca-faq-detail__cta-text"><?php esc_html_e( 'Still have questions?', 'gemini-chat-assistant' ); ?></p>
					<button type="button" class="gca-btn gca-btn--primary gca-faq-detail__start-btn">
						<?php esc_html_e( 'Start a Conversation', 'gemini-chat-assistant' ); ?>
					</button>
				</div>
			</div>
		</section>

		<!-- Screen: Pre-Chat Lead Capture Form -->
		<section class="gca-screen gca-screen--prechat" aria-label="<?php esc_attr_e( 'Pre-Chat Form', 'gemini-chat-assistant' ); ?>">
			<div class="gca-prechat">
				<div class="gca-prechat__header">
					<button type="button" class="gca-prechat__back" aria-label="<?php esc_attr_e( 'Back to Home screen', 'gemini-chat-assistant' ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<line x1="19" y1="12" x2="5" y2="12"></line>
							<polyline points="12 19 5 12 12 5"></polyline>
						</svg>
						<span><?php esc_html_e( 'Start a Conversation', 'gemini-chat-assistant' ); ?></span>
					</button>
					<p class="gca-prechat__intro"><?php esc_html_e( 'Please share a few details so we can best assist you.', 'gemini-chat-assistant' ); ?></p>
				</div>

				<form class="gca-prechat-form" novalidate onsubmit="return false;">
					<!-- Invisible Honeypot field (anti-spam) -->
					<div class="gca-sr-only" aria-hidden="true">
						<label for="<?php echo esc_attr( $instance_id ); ?>-website-url"><?php esc_html_e( 'Leave this field blank', 'gemini-chat-assistant' ); ?></label>
						<input type="text" id="<?php echo esc_attr( $instance_id ); ?>-website-url" name="website_url" tabindex="-1" autocomplete="off" />
					</div>

					<!-- Form Top General Error Notice -->
					<div class="gca-prechat__error-banner" role="alert" style="display: none;"></div>

					<!-- Name Field -->
					<?php if ( $collect_name ) : ?>
						<div class="gca-form-group" data-field="name">
							<label for="<?php echo esc_attr( $instance_id ); ?>-prechat-name" class="gca-form-label">
								<?php esc_html_e( 'Name', 'gemini-chat-assistant' ); ?>
								<?php if ( $require_name ) : ?>
									<span class="gca-req" aria-hidden="true">*</span>
								<?php endif; ?>
							</label>
							<input
								type="text"
								id="<?php echo esc_attr( $instance_id ); ?>-prechat-name"
								name="name"
								class="gca-input"
								placeholder="<?php esc_attr_e( 'Your name', 'gemini-chat-assistant' ); ?>"
								maxlength="100"
								<?php if ( $require_name ) echo 'aria-required="true"'; ?>
								aria-describedby="<?php echo esc_attr( $instance_id ); ?>-name-error"
							/>
							<span class="gca-field-error" id="<?php echo esc_attr( $instance_id ); ?>-name-error" role="alert"></span>
						</div>
					<?php endif; ?>

					<!-- Email Field -->
					<?php if ( $collect_email ) : ?>
						<div class="gca-form-group" data-field="email">
							<label for="<?php echo esc_attr( $instance_id ); ?>-prechat-email" class="gca-form-label">
								<?php esc_html_e( 'Email', 'gemini-chat-assistant' ); ?>
								<?php if ( $require_email ) : ?>
									<span class="gca-req" aria-hidden="true">*</span>
								<?php endif; ?>
							</label>
							<input
								type="email"
								id="<?php echo esc_attr( $instance_id ); ?>-prechat-email"
								name="email"
								class="gca-input"
								placeholder="<?php esc_attr_e( 'your.email@example.com', 'gemini-chat-assistant' ); ?>"
								maxlength="254"
								<?php if ( $require_email ) echo 'aria-required="true"'; ?>
								aria-describedby="<?php echo esc_attr( $instance_id ); ?>-email-error"
							/>
							<span class="gca-field-error" id="<?php echo esc_attr( $instance_id ); ?>-email-error" role="alert"></span>
						</div>
					<?php endif; ?>

					<!-- Phone Field -->
					<?php if ( $collect_phone ) : ?>
						<div class="gca-form-group" data-field="phone">
							<label for="<?php echo esc_attr( $instance_id ); ?>-prechat-phone" class="gca-form-label">
								<?php esc_html_e( 'Phone', 'gemini-chat-assistant' ); ?>
								<?php if ( $require_phone ) : ?>
									<span class="gca-req" aria-hidden="true">*</span>
								<?php endif; ?>
							</label>
							<input
								type="tel"
								id="<?php echo esc_attr( $instance_id ); ?>-prechat-phone"
								name="phone"
								class="gca-input"
								placeholder="<?php esc_attr_e( '+1 (555) 000-0000', 'gemini-chat-assistant' ); ?>"
								maxlength="50"
								<?php if ( $require_phone ) echo 'aria-required="true"'; ?>
								aria-describedby="<?php echo esc_attr( $instance_id ); ?>-phone-error"
							/>
							<span class="gca-field-error" id="<?php echo esc_attr( $instance_id ); ?>-phone-error" role="alert"></span>
						</div>
					<?php endif; ?>

					<!-- Requirement / Message Field -->
					<?php if ( $collect_requirement ) : ?>
						<div class="gca-form-group" data-field="requirement">
							<label for="<?php echo esc_attr( $instance_id ); ?>-prechat-req" class="gca-form-label">
								<?php esc_html_e( 'What can we help you with?', 'gemini-chat-assistant' ); ?>
								<?php if ( $require_requirement ) : ?>
									<span class="gca-req" aria-hidden="true">*</span>
								<?php endif; ?>
							</label>
							<textarea
								id="<?php echo esc_attr( $instance_id ); ?>-prechat-req"
								name="requirement"
								rows="3"
								class="gca-textarea"
								placeholder="<?php esc_attr_e( 'Describe your question or requirement...', 'gemini-chat-assistant' ); ?>"
								maxlength="2000"
								<?php if ( $require_requirement ) echo 'aria-required="true"'; ?>
								aria-describedby="<?php echo esc_attr( $instance_id ); ?>-req-error"
							></textarea>
							<span class="gca-field-error" id="<?php echo esc_attr( $instance_id ); ?>-req-error" role="alert"></span>
						</div>
					<?php endif; ?>

					<p class="gca-prechat__consent">
						<?php esc_html_e( 'By starting a conversation, you agree that the information you provide may be used to respond to your inquiry.', 'gemini-chat-assistant' ); ?>
					</p>

					<div class="gca-prechat__actions">
						<button type="submit" class="gca-btn gca-btn--primary gca-prechat-submit">
							<span class="gca-prechat-submit__text"><?php esc_html_e( 'Start Chat', 'gemini-chat-assistant' ); ?></span>
							<span class="gca-prechat-submit__spinner" aria-hidden="true" style="display: none;">⏳</span>
						</button>
					</div>
				</form>
			</div>
		</section>

		<!-- Screen 2: Chat Screen -->
		<section class="gca-screen gca-screen--chat" aria-label="<?php esc_attr_e( 'Chat Screen', 'gemini-chat-assistant' ); ?>">
			<div class="gca-chat">
				<!-- AI Provider & Model Selector Bar (Node N21) -->
				<div class="gca-ai-selector-bar" role="toolbar" aria-label="<?php esc_attr_e( 'AI Provider and Model selection', 'gemini-chat-assistant' ); ?>" style="display: none;">
					<div class="gca-selector-group gca-selector-group--provider" style="display: none;">
						<label for="<?php echo esc_attr( $instance_id ); ?>-provider-select" class="gca-selector-label">
							<?php esc_html_e( 'AI:', 'gemini-chat-assistant' ); ?>
						</label>
						<select id="<?php echo esc_attr( $instance_id ); ?>-provider-select" class="gca-selector gca-selector--provider" aria-label="<?php esc_attr_e( 'Select AI Provider', 'gemini-chat-assistant' ); ?>">
						</select>
					</div>
					<div class="gca-selector-group gca-selector-group--model" style="display: none;">
						<label for="<?php echo esc_attr( $instance_id ); ?>-model-select" class="gca-selector-label">
							<?php esc_html_e( 'Model:', 'gemini-chat-assistant' ); ?>
						</label>
						<select id="<?php echo esc_attr( $instance_id ); ?>-model-select" class="gca-selector gca-selector--model" aria-label="<?php esc_attr_e( 'Select AI Model', 'gemini-chat-assistant' ); ?>">
						</select>
					</div>
				</div>

				<!-- Messages Viewport -->
				<div class="gca-messages" role="log" aria-live="polite" aria-label="<?php esc_attr_e( 'Conversation messages', 'gemini-chat-assistant' ); ?>">
					<!-- Assistant Initial Welcome Bubble -->
					<div class="gca-message gca-message--assistant" data-role="assistant">
						<div class="gca-message__avatar" aria-hidden="true">
							<?php if ( ! empty( $avatar_url ) ) : ?>
								<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" class="gca-avatar-img" />
							<?php else : ?>
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<rect x="3" y="8" width="18" height="12" rx="2"></rect>
									<path d="M12 2v6"></path>
								</svg>
							<?php endif; ?>
						</div>
						<div class="gca-message__bubble">
							<p class="gca-message__text"><?php echo esc_html( $welcome_message ); ?></p>
						</div>
					</div>

					<!-- Loading Indicator (Hidden by default) -->
					<div class="gca-loading" aria-live="polite" aria-hidden="true" style="display: none;">
						<div class="gca-message__avatar" aria-hidden="true">
							<?php if ( ! empty( $avatar_url ) ) : ?>
								<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" class="gca-avatar-img" />
							<?php else : ?>
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<rect x="3" y="8" width="18" height="12" rx="2"></rect>
									<path d="M12 2v6"></path>
								</svg>
							<?php endif; ?>
						</div>
						<div class="gca-loading__bubble">
							<span class="gca-loading__dot"></span>
							<span class="gca-loading__dot"></span>
							<span class="gca-loading__dot"></span>
							<span class="gca-sr-only"><?php esc_html_e( 'Assistant is responding...', 'gemini-chat-assistant' ); ?></span>
						</div>
					</div>
				</div>

				<!-- Error Notice Banner (Hidden by default) -->
				<div class="gca-error-notice" role="alert" style="display: none;">
					<span class="gca-error-notice__icon" aria-hidden="true">⚠️</span>
					<span class="gca-error-notice__text"></span>
					<button type="button" class="gca-error-notice__retry" style="display: none;">
						<?php esc_html_e( 'Try Again', 'gemini-chat-assistant' ); ?>
					</button>
				</div>

				<!-- Floating Scroll-to-Bottom Button -->
				<button type="button" class="gca-scroll-bottom" aria-label="<?php esc_attr_e( 'Scroll to latest messages', 'gemini-chat-assistant' ); ?>" style="display: none;">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<line x1="12" y1="5" x2="12" y2="19"></line>
						<polyline points="19 12 12 19 5 12"></polyline>
					</svg>
					<span><?php esc_html_e( 'New messages', 'gemini-chat-assistant' ); ?></span>
				</button>

				<!-- Composer -->
				<form class="gca-composer" onsubmit="return false;">
					<textarea
						class="gca-composer__input"
						rows="1"
						maxlength="<?php echo esc_attr( $max_length ); ?>"
						placeholder="<?php echo esc_attr( $placeholder ); ?>"
						aria-label="<?php esc_attr_e( 'Type your message', 'gemini-chat-assistant' ); ?>"
					></textarea>
					<button
						type="button"
						class="gca-composer__send"
						aria-label="<?php esc_attr_e( 'Send message', 'gemini-chat-assistant' ); ?>"
					>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<line x1="22" y1="2" x2="11" y2="13"></line>
							<polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
						</svg>
					</button>
				</form>
			</div>
		</section>

		<!-- Reset Confirmation Dialog Overlay -->
		<div class="gca-confirm-dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Reset Conversation Confirmation', 'gemini-chat-assistant' ); ?>" style="display: none;">
			<div class="gca-confirm-dialog__backdrop"></div>
			<div class="gca-confirm-dialog__card">
				<h4 class="gca-confirm-dialog__title"><?php esc_html_e( 'Start a new conversation?', 'gemini-chat-assistant' ); ?></h4>
				<p class="gca-confirm-dialog__text"><?php esc_html_e( 'This will clear the current chat from this screen.', 'gemini-chat-assistant' ); ?></p>
				<div class="gca-confirm-dialog__actions">
					<button type="button" class="gca-confirm-dialog__btn gca-confirm-dialog__btn--cancel"><?php esc_html_e( 'Cancel', 'gemini-chat-assistant' ); ?></button>
					<button type="button" class="gca-confirm-dialog__btn gca-confirm-dialog__btn--confirm"><?php esc_html_e( 'New Chat', 'gemini-chat-assistant' ); ?></button>
				</div>
			</div>
		</div>
	</div>

	<!-- Bottom Navigation Bar -->
	<nav class="gca-nav" aria-label="<?php esc_attr_e( 'Chat navigation', 'gemini-chat-assistant' ); ?>">
		<button
			type="button"
			class="gca-nav__btn gca-nav__btn--home gca-nav__btn--active"
			data-target="home"
			aria-current="page"
			aria-label="<?php esc_attr_e( 'Navigate to Home screen', 'gemini-chat-assistant' ); ?>"
		>
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
				<polyline points="9 22 9 12 15 12 15 22"></polyline>
			</svg>
			<span><?php esc_html_e( 'Home', 'gemini-chat-assistant' ); ?></span>
		</button>
		<button
			type="button"
			class="gca-nav__btn gca-nav__btn--chat"
			data-target="chat"
			aria-label="<?php esc_attr_e( 'Navigate to Chat screen', 'gemini-chat-assistant' ); ?>"
		>
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
			</svg>
			<span><?php esc_html_e( 'Chat', 'gemini-chat-assistant' ); ?></span>
		</button>
	</nav>
</div>
