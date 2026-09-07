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
$max_length      = absint( $settings['max_message_length'] ?? 1000 );
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
	<span class="gca-launcher__icon gca-launcher__icon--open" aria-hidden="true">
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
		</svg>
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
		<div class="gca-header__info">
			<div class="gca-header__avatar" aria-hidden="true">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2 2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"></path>
					<rect x="3" y="8" width="18" height="12" rx="2"></rect>
					<circle cx="9" cy="14" r="1"></circle>
					<circle cx="15" cy="14" r="1"></circle>
				</svg>
			</div>
			<div>
				<h3 class="gca-header__title"><?php echo esc_html( $assistant_name ); ?></h3>
				<span class="gca-header__status">
					<span class="gca-header__status-dot" aria-hidden="true"></span>
					<?php esc_html_e( 'Online', 'gemini-chat-assistant' ); ?>
				</span>
			</div>
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
					<div class="gca-start-card__content">
						<div class="gca-start-card__icon" aria-hidden="true">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
							</svg>
						</div>
						<div class="gca-start-card__text">
							<span class="gca-start-card__title"><?php esc_html_e( 'Start a Conversation', 'gemini-chat-assistant' ); ?></span>
							<span class="gca-start-card__desc"><?php esc_html_e( 'Ask us about products, services, or anything else you need help with.', 'gemini-chat-assistant' ); ?></span>
						</div>
					</div>
					<span class="gca-start-card__arrow" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<polyline points="9 18 15 12 9 6"></polyline>
						</svg>
					</span>
				</button>

				<!-- FAQ Slot Placeholder (Reserved for future N16) -->
				<div class="gca-faq-slot" aria-hidden="true"></div>
			</div>
		</section>

		<!-- Screen 2: Chat Screen -->
		<section class="gca-screen gca-screen--chat" aria-label="<?php esc_attr_e( 'Chat Screen', 'gemini-chat-assistant' ); ?>">
			<div class="gca-chat">
				<!-- Messages Viewport -->
				<div class="gca-messages" role="log" aria-live="polite" aria-label="<?php esc_attr_e( 'Conversation messages', 'gemini-chat-assistant' ); ?>">
					<!-- Assistant Initial Welcome Bubble -->
					<div class="gca-message gca-message--assistant" data-role="assistant">
						<div class="gca-message__avatar" aria-hidden="true">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<rect x="3" y="8" width="18" height="12" rx="2"></rect>
								<path d="M12 2v6"></path>
							</svg>
						</div>
						<div class="gca-message__bubble">
							<p class="gca-message__text"><?php echo esc_html( $welcome_message ); ?></p>
						</div>
					</div>

					<!-- Loading Indicator (Hidden by default) -->
					<div class="gca-loading" aria-live="polite" aria-hidden="true" style="display: none;">
						<div class="gca-message__avatar" aria-hidden="true">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<rect x="3" y="8" width="18" height="12" rx="2"></rect>
								<path d="M12 2v6"></path>
							</svg>
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
				</div>

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
	</div>

	<!-- Bottom Navigation Bar -->
	<nav class="gca-nav" aria-label="<?php esc_attr_e( 'Chat navigation', 'gemini-chat-assistant' ); ?>">
		<button
			type="button"
			class="gca-nav__btn gca-nav__btn--home gca-nav__btn--active"
			data-target="home"
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
