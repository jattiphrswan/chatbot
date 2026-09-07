/**
 * Gemini Chat Assistant - Public Frontend Client.
 *
 * Vanilla JavaScript implementation managing UI state, navigation,
 * and REST communication with WordPress backend.
 */

(function () {
	'use strict';

	/**
	 * Resolves or initializes a secure client session token in sessionStorage.
	 *
	 * @return {string} Format: gca_sess_<32-hex-chars>
	 */
	function getOrCreateSessionToken() {
		const storageKey = 'gca_session_token';
		let token = '';

		try {
			token = sessionStorage.getItem(storageKey);
		} catch (e) {
			// Storage unavailable or disabled.
		}

		if (token && /^gca_sess_[a-fA-F0-9]{32,64}$/.test(token)) {
			return token;
		}

		// Generate cryptographically secure random token.
		token = generateSecureToken();
		try {
			sessionStorage.setItem(storageKey, token);
		} catch (e) {
			// Ignore storage write failure.
		}

		return token;
	}

	/**
	 * Generates an opaque random session token using browser crypto API.
	 *
	 * @return {string}
	 */
	function generateSecureToken() {
		if (typeof window.crypto !== 'undefined') {
			if (typeof window.crypto.randomUUID === 'function') {
				return 'gca_sess_' + window.crypto.randomUUID().replace(/-/g, '');
			}
			if (typeof window.crypto.getRandomValues === 'function') {
				const bytes = new Uint8Array(16);
				window.crypto.getRandomValues(bytes);
				const hex = Array.from(bytes, function (b) {
					return b.toString(16).padStart(2, '0');
				}).join('');
				return 'gca_sess_' + hex;
			}
		}
		// Fallback pseudo-random token if crypto is unavailable.
		const fallbackHex = 'xxxxxxxxxxxx4xxxyxxxxxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			const r = (Math.random() * 16) | 0;
			const v = c === 'x' ? r : (r & 0x3) | 0x8;
			return v.toString(16);
		});
		return 'gca_sess_' + fallbackHex;
	}

	/**
	 * Resets and regenerates the active session token.
	 *
	 * @return {string}
	 */
	function resetSessionToken() {
		const token = generateSecureToken();
		try {
			sessionStorage.setItem('gca_session_token', token);
		} catch (e) {
			// Storage write failure.
		}
		return token;
	}

	/**
	 * Initializes a specific chatbot widget instance.
	 *
	 * @param {HTMLElement} widgetElem Widget root element.
	 */
	function initWidgetInstance(widgetElem) {
		if (!widgetElem || widgetElem.dataset.gcaInitialized === 'true') {
			return;
		}
		widgetElem.dataset.gcaInitialized = 'true';

		const config = window.gcaConfig || {};
		const i18n = config.i18n || {};
		const isFloating = widgetElem.dataset.mode === 'floating';
		const widgetId = widgetElem.id;
		const launcher = isFloating ? document.querySelector(`.gca-launcher[aria-controls="${widgetId}"]`) : null;

		let sessionToken = getOrCreateSessionToken();

		// Screen elements
		const screenHome = widgetElem.querySelector('.gca-screen--home');
		const screenChat = widgetElem.querySelector('.gca-screen--chat');
		const navBtnHome = widgetElem.querySelector('.gca-nav__btn--home');
		const navBtnChat = widgetElem.querySelector('.gca-nav__btn--chat');
		const startCard = widgetElem.querySelector('.gca-start-card');

		// Chat elements
		const messagesContainer = widgetElem.querySelector('.gca-messages');
		const loadingElem = widgetElem.querySelector('.gca-loading');
		const errorNotice = widgetElem.querySelector('.gca-error-notice');
		const composerInput = widgetElem.querySelector('.gca-composer__input');
		const sendButton = widgetElem.querySelector('.gca-composer__send');
		const btnReset = widgetElem.querySelector('.gca-btn-reset');
		const btnClose = widgetElem.querySelector('.gca-btn-close');

		/**
		 * Switches active screen view.
		 *
		 * @param {'home'|'chat'} targetScreen
		 */
		function switchScreen(targetScreen) {
			if (targetScreen === 'chat') {
				screenHome.classList.remove('gca-screen--active');
				screenChat.classList.add('gca-screen--active');
				navBtnHome.classList.remove('gca-nav__btn--active');
				navBtnChat.classList.add('gca-nav__btn--active');
				if (composerInput) {
					setTimeout(function () {
						composerInput.focus();
					}, 100);
				}
			} else {
				screenChat.classList.remove('gca-screen--active');
				screenHome.classList.add('gca-screen--active');
				navBtnChat.classList.remove('gca-nav__btn--active');
				navBtnHome.classList.add('gca-nav__btn--active');
			}
		}

		/**
		 * Opens or closes the floating widget panel.
		 *
		 * @param {boolean} open
		 */
		function toggleFloatingWidget(open) {
			if (!isFloating) return;

			const shouldOpen = typeof open === 'boolean' ? open : !widgetElem.classList.contains('gca-widget--open');

			if (shouldOpen) {
				widgetElem.classList.add('gca-widget--open');
				widgetElem.setAttribute('aria-hidden', 'false');
				if (launcher) launcher.setAttribute('aria-expanded', 'true');
			} else {
				widgetElem.classList.remove('gca-widget--open');
				widgetElem.setAttribute('aria-hidden', 'true');
				if (launcher) launcher.setAttribute('aria-expanded', 'false');
			}
		}

		/**
		 * Appends a safe message bubble to the viewport.
		 *
		 * @param {string} text Message text content.
		 * @param {'user'|'assistant'} role Sender role.
		 */
		function appendMessage(text, role) {
			if (!messagesContainer) return;

			const msgElem = document.createElement('div');
			msgElem.className = 'gca-message gca-message--' + role;
			msgElem.setAttribute('data-role', role);

			if (role === 'assistant') {
				const avatar = document.createElement('div');
				avatar.className = 'gca-message__avatar';
				avatar.setAttribute('aria-hidden', 'true');
				avatar.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="12" rx="2"></rect><path d="M12 2v6"></path></svg>';
				msgElem.appendChild(avatar);
			}

			const bubble = document.createElement('div');
			bubble.className = 'gca-message__bubble';

			const p = document.createElement('p');
			p.className = 'gca-message__text';
			p.textContent = text; // Safe text rendering (Prevents XSS / injection)

			bubble.appendChild(p);
			msgElem.appendChild(bubble);

			// Insert before loading indicator
			if (loadingElem && loadingElem.parentNode === messagesContainer) {
				messagesContainer.insertBefore(msgElem, loadingElem);
			} else {
				messagesContainer.appendChild(msgElem);
			}

			scrollToBottom();
		}

		/**
		 * Scrolls messages viewport to bottom.
		 */
		function scrollToBottom() {
			if (messagesContainer) {
				messagesContainer.scrollTop = messagesContainer.scrollHeight;
			}
		}

		/**
		 * Shows or hides error banner.
		 *
		 * @param {string|null} errorMsg
		 */
		function showError(errorMsg) {
			if (!errorNotice) return;
			const textSpan = errorNotice.querySelector('.gca-error-notice__text');
			if (errorMsg) {
				if (textSpan) textSpan.textContent = errorMsg;
				errorNotice.style.display = 'flex';
			} else {
				errorNotice.style.display = 'none';
			}
		}

		/**
		 * Handles message dispatching to the REST API.
		 */
		function handleSendMessage() {
			if (!composerInput) return;

			const rawText = composerInput.value;
			const text = rawText.trim();
			if (!text) return;

			const maxLen = config.maxMessageLength || 1000;
			if (text.length > maxLen) {
				showError(i18n.errorTooLong || 'Your message exceeds the maximum allowed length.');
				return;
			}

			showError(null);
			appendMessage(text, 'user');

			composerInput.value = '';
			composerInput.style.height = 'auto';

			// Disable composer and show loading indicator
			composerInput.disabled = true;
			if (sendButton) sendButton.disabled = true;
			if (loadingElem) {
				loadingElem.style.display = 'flex';
				scrollToBottom();
			}

			const restUrl = (config.restUrl || '/wp-json/gca/v1').replace(/\/$/, '') + '/chat';

			fetch(restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({
					message: text,
					session_id: sessionToken,
				}),
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (data) {
					if (loadingElem) loadingElem.style.display = 'none';
					composerInput.disabled = false;
					if (sendButton) sendButton.disabled = false;
					composerInput.focus();

					if (data && data.success && data.data && typeof data.data.message === 'string') {
						appendMessage(data.data.message, 'assistant');
					} else {
						const errorMsg = (data && data.error && data.error.message) || i18n.errorGeneric || 'Something went wrong. Please try again.';
						showError(errorMsg);
					}
				})
				.catch(function () {
					if (loadingElem) loadingElem.style.display = 'none';
					composerInput.disabled = false;
					if (sendButton) sendButton.disabled = false;
					showError(i18n.networkError || 'Network error connecting to the chat server.');
				});
		}

		/**
		 * Resets the conversation session.
		 */
		function handleResetConversation() {
			const restUrl = (config.restUrl || '/wp-json/gca/v1').replace(/\/$/, '') + '/reset';

			fetch(restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({
					session_id: sessionToken,
				}),
			}).catch(function () {
				// Best effort reset
			});

			sessionToken = resetSessionToken();
			showError(null);

			// Reset messages to initial state
			if (messagesContainer) {
				const welcomeMsg = config.welcomeMessage || 'Hi! How can I help you today?';
				const initialAssistant = messagesContainer.querySelector('.gca-message--assistant');
				if (initialAssistant) {
					const p = initialAssistant.querySelector('.gca-message__text');
					if (p) p.textContent = welcomeMsg;
				}

				// Remove all user messages and subsequent assistant bubbles
				const allMessages = messagesContainer.querySelectorAll('.gca-message');
				allMessages.forEach(function (msg, idx) {
					if (idx > 0) {
						msg.remove();
					}
				});
			}

			switchScreen('chat');
		}

		// Event Listeners
		if (launcher) {
			launcher.addEventListener('click', function () {
				toggleFloatingWidget();
			});
		}

		if (btnClose) {
			btnClose.addEventListener('click', function () {
				toggleFloatingWidget(false);
			});
		}

		if (btnReset) {
			btnReset.addEventListener('click', handleResetConversation);
		}

		if (navBtnHome) {
			navBtnHome.addEventListener('click', function () {
				switchScreen('home');
			});
		}

		if (navBtnChat) {
			navBtnChat.addEventListener('click', function () {
				switchScreen('chat');
			});
		}

		if (startCard) {
			startCard.addEventListener('click', function () {
				switchScreen('chat');
			});
		}

		if (sendButton) {
			sendButton.addEventListener('click', handleSendMessage);
		}

		if (composerInput) {
			composerInput.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' && !e.shiftKey) {
					e.preventDefault();
					handleSendMessage();
				}
			});

			// Auto-grow textarea
			composerInput.addEventListener('input', function () {
				this.style.height = 'auto';
				this.style.height = Math.min(this.scrollHeight, 100) + 'px';
			});
		}

		// Close floating widget on Escape key press
		if (isFloating) {
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && widgetElem.classList.contains('gca-widget--open')) {
					toggleFloatingWidget(false);
				}
			});
		}
	}

	// Auto-initialize all widgets when DOM is ready
	document.addEventListener('DOMContentLoaded', function () {
		const widgets = document.querySelectorAll('.gca-widget');
		widgets.forEach(function (widget) {
			initWidgetInstance(widget);
		});
	});
})();
