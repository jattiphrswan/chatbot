/**
 * Gemini Chat Assistant - Public Frontend Client.
 *
 * Vanilla JavaScript implementation managing UX state, accessible navigation,
 * robust autoscroll, rate limit countdowns, retry flows, and REST communication.
 *
 * @package SkyFish\GeminiChat
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
		const launcher = isFloating ? document.querySelector('.gca-launcher[aria-controls="' + widgetId + '"]') : null;
		const launcherBadge = launcher ? launcher.querySelector('.gca-launcher__badge') : null;

		let sessionToken = getOrCreateSessionToken();

		// UX Component State Model
		const state = {
			isOpen: !isFloating,
			activeScreen: 'home',
			isSending: false,
			unreadCount: 0,
			lastFailedMessage: null,
			rateLimitRemaining: 0,
			rateLimitTimer: null,
			userScrolledUp: false,
			prechatCompleted: false,
		};

		// Check sessionStorage for pre-chat completion in current session
		try {
			if (sessionStorage.getItem('gca_prechat_' + sessionToken) === '1') {
				state.prechatCompleted = true;
			}
		} catch (e) {
			// Storage unavailable
		}

		// Screen Elements
		const screenHome = widgetElem.querySelector('.gca-screen--home');
		const screenPrechat = widgetElem.querySelector('.gca-screen--prechat');
		const screenChat = widgetElem.querySelector('.gca-screen--chat');
		const navBtnHome = widgetElem.querySelector('.gca-nav__btn--home');
		const navBtnChat = widgetElem.querySelector('.gca-nav__btn--chat');
		const startCard = widgetElem.querySelector('.gca-start-card');

		// Pre-chat Elements
		const prechatForm = widgetElem.querySelector('.gca-prechat-form');
		const prechatBackBtn = widgetElem.querySelector('.gca-prechat__back');
		const prechatSubmitBtn = widgetElem.querySelector('.gca-prechat-submit');
		const prechatErrorBanner = widgetElem.querySelector('.gca-prechat__error-banner');

		// Chat Elements
		const messagesContainer = widgetElem.querySelector('.gca-messages');
		const loadingElem = widgetElem.querySelector('.gca-loading');
		const errorNotice = widgetElem.querySelector('.gca-error-notice');
		const errorText = errorNotice ? errorNotice.querySelector('.gca-error-notice__text') : null;
		const errorRetryBtn = errorNotice ? errorNotice.querySelector('.gca-error-notice__retry') : null;
		const scrollBottomBtn = widgetElem.querySelector('.gca-scroll-bottom');
		const composerInput = widgetElem.querySelector('.gca-composer__input');
		const sendButton = widgetElem.querySelector('.gca-composer__send');
		const btnReset = widgetElem.querySelector('.gca-btn-reset');
		const btnClose = widgetElem.querySelector('.gca-btn-close');

		// Reset Confirmation Dialog Elements
		const confirmDialog = widgetElem.querySelector('.gca-confirm-dialog');
		const confirmBackdrop = confirmDialog ? confirmDialog.querySelector('.gca-confirm-dialog__backdrop') : null;
		const confirmCancelBtn = confirmDialog ? confirmDialog.querySelector('.gca-confirm-dialog__btn--cancel') : null;
		const confirmAcceptBtn = confirmDialog ? confirmDialog.querySelector('.gca-confirm-dialog__btn--confirm') : null;

		/**
		 * Switches active screen view.
		 *
		 * @param {'home'|'prechat'|'chat'} targetScreen
		 */
		function switchScreen(targetScreen) {
			state.activeScreen = targetScreen;

			if (screenHome) screenHome.classList.remove('gca-screen--active');
			if (screenPrechat) screenPrechat.classList.remove('gca-screen--active');
			if (screenChat) screenChat.classList.remove('gca-screen--active');

			if (targetScreen === 'chat') {
				if (screenChat) screenChat.classList.add('gca-screen--active');
				if (navBtnHome) {
					navBtnHome.classList.remove('gca-nav__btn--active');
					navBtnHome.removeAttribute('aria-current');
				}
				if (navBtnChat) {
					navBtnChat.classList.add('gca-nav__btn--active');
					navBtnChat.setAttribute('aria-current', 'page');
				}
				if (composerInput) {
					setTimeout(function () {
						composerInput.focus();
					}, 100);
				}
			} else if (targetScreen === 'prechat') {
				if (screenPrechat) screenPrechat.classList.add('gca-screen--active');
				if (navBtnHome) {
					navBtnHome.classList.add('gca-nav__btn--active');
					navBtnHome.removeAttribute('aria-current');
				}
				if (navBtnChat) {
					navBtnChat.classList.remove('gca-nav__btn--active');
					navBtnChat.removeAttribute('aria-current');
				}
				if (screenPrechat) {
					const firstInput = screenPrechat.querySelector('.gca-input, .gca-textarea');
					if (firstInput) {
						setTimeout(function () {
							firstInput.focus();
						}, 100);
					}
				}
			} else {
				if (screenHome) screenHome.classList.add('gca-screen--active');
				if (navBtnChat) {
					navBtnChat.classList.remove('gca-nav__btn--active');
					navBtnChat.removeAttribute('aria-current');
				}
				if (navBtnHome) {
					navBtnHome.classList.add('gca-nav__btn--active');
					navBtnHome.setAttribute('aria-current', 'page');
				}
			}
		}

		/**
		 * Opens or closes the floating widget panel.
		 *
		 * @param {boolean} [open]
		 */
		function toggleFloatingWidget(open) {
			if (!isFloating) return;

			const shouldOpen = typeof open === 'boolean' ? open : !state.isOpen;
			state.isOpen = shouldOpen;

			if (shouldOpen) {
				widgetElem.classList.add('gca-widget--open');
				widgetElem.setAttribute('aria-hidden', 'false');
				if (launcher) launcher.setAttribute('aria-expanded', 'true');

				// Clear unread badge on open
				state.unreadCount = 0;
				updateUnreadBadge();

				// Focus management: focus composer if on chat screen, or start card if on home
				setTimeout(function () {
					if (state.activeScreen === 'chat' && composerInput) {
						composerInput.focus();
					} else if (startCard) {
						startCard.focus();
					}
				}, 150);
			} else {
				widgetElem.classList.remove('gca-widget--open');
				widgetElem.setAttribute('aria-hidden', 'true');
				if (launcher) {
					launcher.setAttribute('aria-expanded', 'false');
					launcher.focus();
				}
				hideResetConfirmation();
			}
		}

		/**
		 * Updates the unread message counter badge on the floating launcher.
		 */
		function updateUnreadBadge() {
			if (!launcherBadge) return;

			if (state.unreadCount > 0 && !state.isOpen) {
				launcherBadge.textContent = state.unreadCount > 9 ? '9+' : String(state.unreadCount);
				launcherBadge.style.display = 'flex';
				launcherBadge.setAttribute('aria-label', state.unreadCount + ' ' + (i18n.unreadCount || 'unread messages'));
			} else {
				launcherBadge.textContent = '0';
				launcherBadge.style.display = 'none';
				launcherBadge.removeAttribute('aria-label');
			}
		}

		/**
		 * Checks if the messages container is currently scrolled near the bottom.
		 *
		 * @return {boolean}
		 */
		function isNearBottom() {
			if (!messagesContainer) return true;
			const threshold = 80;
			return messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight <= threshold;
		}

		/**
		 * Smoothly scrolls message viewport to the bottom.
		 *
		 * @param {boolean} [force=false]
		 */
		function scrollToBottom(force) {
			if (!messagesContainer) return;

			if (force || !state.userScrolledUp || isNearBottom()) {
				messagesContainer.scrollTop = messagesContainer.scrollHeight;
				state.userScrolledUp = false;
				if (scrollBottomBtn) {
					scrollBottomBtn.style.display = 'none';
				}
			}
		}

		/**
		 * Handles scroll events inside messages container to track user scroll intent.
		 */
		if (messagesContainer) {
			messagesContainer.addEventListener('scroll', function () {
				if (isNearBottom()) {
					state.userScrolledUp = false;
					if (scrollBottomBtn) {
						scrollBottomBtn.style.display = 'none';
					}
				} else {
					state.userScrolledUp = true;
				}
			});
		}

		/**
		 * Appends a safe, sanitized message bubble to the viewport.
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
			p.textContent = text; // Safe text rendering (Strict XSS prevention)

			bubble.appendChild(p);
			msgElem.appendChild(bubble);

			// Insert before loading indicator if present
			if (loadingElem && loadingElem.parentNode === messagesContainer) {
				messagesContainer.insertBefore(msgElem, loadingElem);
			} else {
				messagesContainer.appendChild(msgElem);
			}

			// Scroll behavior
			if (role === 'user') {
				scrollToBottom(true);
			} else if (role === 'assistant') {
				if (!state.isOpen) {
					state.unreadCount++;
					updateUnreadBadge();
				}

				if (state.userScrolledUp && !isNearBottom()) {
					if (scrollBottomBtn) {
						scrollBottomBtn.style.display = 'flex';
					}
				} else {
					scrollToBottom(true);
				}
			}
		}

		/**
		 * Shows or hides the error banner with optional retry and countdown support.
		 *
		 * @param {string|null} errorMsg
		 * @param {boolean} [canRetry=false]
		 */
		function showError(errorMsg, canRetry) {
			if (!errorNotice || !errorText) return;

			if (errorMsg) {
				errorText.textContent = errorMsg;
				errorNotice.style.display = 'flex';

				if (canRetry && errorRetryBtn) {
					errorRetryBtn.style.display = 'inline-block';
				} else if (errorRetryBtn) {
					errorRetryBtn.style.display = 'none';
				}
			} else {
				errorNotice.style.display = 'none';
				if (errorRetryBtn) errorRetryBtn.style.display = 'none';
			}
		}

		/**
		 * Starts a countdown timer for rate limit enforcement.
		 *
		 * @param {number} seconds
		 */
		function startRateLimitCountdown(seconds) {
			if (state.rateLimitTimer) {
				clearInterval(state.rateLimitTimer);
			}

			state.rateLimitRemaining = seconds;
			const template = i18n.rateLimited || 'Too many messages. Please try again in {seconds}s.';

			function tick() {
				if (state.rateLimitRemaining <= 0) {
					clearInterval(state.rateLimitTimer);
					state.rateLimitTimer = null;
					showError(null);
					if (composerInput) composerInput.disabled = false;
					if (sendButton) sendButton.disabled = false;
					if (composerInput) composerInput.focus();
					return;
				}

				const displayMsg = template.replace('{seconds}', String(state.rateLimitRemaining));
				showError(displayMsg, false);
				state.rateLimitRemaining--;
			}

			if (composerInput) composerInput.disabled = true;
			if (sendButton) sendButton.disabled = true;

			tick();
			state.rateLimitTimer = setInterval(tick, 1000);
		}

		/**
		 * Maps REST error codes to user-friendly localized messages.
		 *
		 * @param {Object} data Inbound response data.
		 * @return {string}
		 */
		function resolveErrorMessage(data) {
			const code = data && data.error && data.error.code;

			if (code === 'RATE_LIMITED') {
				return i18n.rateLimited || 'Too many requests. Please wait a moment.';
			}
			if (code === 'INVALID_INPUT') {
				return i18n.errorTooLong || 'Please check your message and try again.';
			}
			if (code === 'AI_TIMEOUT') {
				return i18n.errorTimeout || 'The assistant took too long to respond. Please try again.';
			}
			if (code === 'AI_UNAVAILABLE') {
				return i18n.errorUnavailable || 'The assistant is temporarily unavailable.';
			}
			if (code === 'ACCESS_DENIED') {
				return i18n.errorDenied || 'Chat is currently unavailable.';
			}

			return (data && data.error && data.error.message) || i18n.errorGeneric || 'Something went wrong. Please try again.';
		}

		/**
		 * Dispatches a message to the WordPress REST API endpoint.
		 *
		 * @param {string} [customText] Optional text override (for retry flow).
		 * @param {boolean} [isRetry=false] Whether this is a retry invocation.
		 */
		function handleSendMessage(customText, isRetry) {
			if (state.isSending || state.rateLimitRemaining > 0) {
				return;
			}

			const textToSend = typeof customText === 'string' ? customText.trim() : (composerInput ? composerInput.value.trim() : '');
			if (!textToSend) {
				return;
			}

			const maxLen = config.maxMessageLength || 1000;
			if (textToSend.length > maxLen) {
				showError(i18n.errorTooLong || 'Your message exceeds the maximum allowed length.', false);
				return;
			}

			// Clear previous error and record last message for potential retry
			showError(null);
			state.lastFailedMessage = textToSend;

			if (!isRetry) {
				appendMessage(textToSend, 'user');
				if (composerInput) {
					composerInput.value = '';
					composerInput.style.height = 'auto';
				}
			}

			// Lock composer and activate loading state
			state.isSending = true;
			if (composerInput) composerInput.disabled = true;
			if (sendButton) sendButton.disabled = true;
			if (loadingElem) {
				loadingElem.style.display = 'flex';
				scrollToBottom(true);
			}

			const restUrl = (config.restUrl || '/wp-json/gca/v1').replace(/\/$/, '') + '/chat';

			fetch(restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({
					message: textToSend,
					session_id: sessionToken,
				}),
			})
				.then(function (response) {
					const is429 = response.status === 429;
					return response.json().then(function (json) {
						return { ok: response.ok, status: response.status, data: json, is429: is429 };
					});
				})
				.then(function (res) {
					state.isSending = false;
					if (loadingElem) loadingElem.style.display = 'none';

					if (res.is429) {
						const retryAfter = (res.data && res.data.error && res.data.error.retry_after) || 30;
						startRateLimitCountdown(retryAfter);
						return;
					}

					if (composerInput) composerInput.disabled = false;
					if (sendButton) sendButton.disabled = false;
					if (composerInput) composerInput.focus();

					if (res.ok && res.data && res.data.success && res.data.data && typeof res.data.data.message === 'string') {
						state.lastFailedMessage = null;
						appendMessage(res.data.data.message, 'assistant');
					} else {
						const errMessage = resolveErrorMessage(res.data);
						showError(errMessage, true);
					}
				})
				.catch(function () {
					state.isSending = false;
					if (loadingElem) loadingElem.style.display = 'none';
					if (composerInput) composerInput.disabled = false;
					if (sendButton) sendButton.disabled = false;

					const isOffline = typeof navigator !== 'undefined' && !navigator.onLine;
					const netMsg = isOffline ? (i18n.offlineNotice || 'You appear to be offline.') : (i18n.networkError || 'Network connection failed.');
					showError(netMsg, true);
				});
		}

		/**
		 * Checks if the conversation has active user messages.
		 *
		 * @return {boolean}
		 */
		function hasUserMessages() {
			if (!messagesContainer) return false;
			const userMsgs = messagesContainer.querySelectorAll('.gca-message--user');
			return userMsgs.length > 0;
		}

		/**
		 * Opens the reset conversation confirmation dialog.
		 */
		function showResetConfirmation() {
			if (!hasUserMessages()) {
				// No active conversation to confirm, reset directly
				performResetConversation();
				return;
			}

			if (confirmDialog) {
				confirmDialog.style.display = 'flex';
				if (confirmCancelBtn) confirmCancelBtn.focus();
			}
		}

		/**
		 * Hides the reset confirmation dialog.
		 */
		function hideResetConfirmation() {
			if (confirmDialog) {
				confirmDialog.style.display = 'none';
			}
		}

		/**
		 * Performs the conversation reset against the backend and reinitializes local view.
		 */
		function performResetConversation() {
			hideResetConfirmation();

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
				// Best effort network call
			});

			// Reinitialize session token
			sessionToken = resetSessionToken();
			state.lastFailedMessage = null;
			state.unreadCount = 0;
			updateUnreadBadge();
			showError(null);

			if (state.rateLimitTimer) {
				clearInterval(state.rateLimitTimer);
				state.rateLimitTimer = null;
				state.rateLimitRemaining = 0;
				if (composerInput) composerInput.disabled = false;
				if (sendButton) sendButton.disabled = false;
			}

			// Reset message list to single welcome message
			if (messagesContainer) {
				const welcomeMsg = config.welcomeMessage || 'Hi! How can I help you today?';
				const initialAssistant = messagesContainer.querySelector('.gca-message--assistant');
				if (initialAssistant) {
					const p = initialAssistant.querySelector('.gca-message__text');
					if (p) p.textContent = welcomeMsg;
				}

				const allMessages = messagesContainer.querySelectorAll('.gca-message');
				allMessages.forEach(function (msg, idx) {
					if (idx > 0) {
						msg.remove();
					}
				});
			}

			switchScreen('chat');
		}

		// ---------------------------------------------------------------------
		// Event Listeners
		// ---------------------------------------------------------------------

		// Floating Launcher Toggle
		if (launcher) {
			launcher.addEventListener('click', function () {
				toggleFloatingWidget();
			});
		}

		// Close Action Button
		if (btnClose) {
			btnClose.addEventListener('click', function () {
				toggleFloatingWidget(false);
			});
		}

		// Reset Action Button
		if (btnReset) {
			btnReset.addEventListener('click', showResetConfirmation);
		}

		// Confirmation Dialog Actions
		if (confirmCancelBtn) {
			confirmCancelBtn.addEventListener('click', hideResetConfirmation);
		}
		if (confirmBackdrop) {
			confirmBackdrop.addEventListener('click', hideResetConfirmation);
		}
		if (confirmAcceptBtn) {
			confirmAcceptBtn.addEventListener('click', performResetConversation);
		}

		// Retry Action in Error Banner
		if (errorRetryBtn) {
			errorRetryBtn.addEventListener('click', function () {
				if (state.lastFailedMessage) {
					handleSendMessage(state.lastFailedMessage, true);
				}
			});
		}

		// Scroll to Bottom Button
		if (scrollBottomBtn) {
			scrollBottomBtn.addEventListener('click', function () {
				scrollToBottom(true);
			});
		}

		// Navigation Tabs
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

		// ---------------------------------------------------------------------
		// Pre-Chat Submission & Validation
		// ---------------------------------------------------------------------

		function setFieldError(fieldName, errorMsg) {
			if (!prechatForm) return;
			const input = prechatForm.querySelector('[name="' + fieldName + '"]');
			const group = prechatForm.querySelector('.gca-form-group[data-field="' + fieldName + '"]');
			const errorSpan = group ? group.querySelector('.gca-field-error') : null;

			if (input) {
				input.setAttribute('aria-invalid', 'true');
			}
			if (errorSpan) {
				errorSpan.textContent = errorMsg;
			}
		}

		function clearPrechatErrors() {
			if (!prechatForm) return;
			const inputs = prechatForm.querySelectorAll('.gca-input, .gca-textarea');
			inputs.forEach(function (input) {
				input.removeAttribute('aria-invalid');
			});

			const errorSpans = prechatForm.querySelectorAll('.gca-field-error');
			errorSpans.forEach(function (span) {
				span.textContent = '';
			});

			if (prechatErrorBanner) {
				prechatErrorBanner.textContent = '';
				prechatErrorBanner.style.display = 'none';
			}
		}

		function showPrechatErrorBanner(msg) {
			if (prechatErrorBanner) {
				prechatErrorBanner.textContent = msg;
				prechatErrorBanner.style.display = 'block';
			}
		}

		function setPrechatSubmitting(isSubmitting) {
			if (!prechatSubmitBtn) return;
			prechatSubmitBtn.disabled = isSubmitting;
			const textSpan = prechatSubmitBtn.querySelector('.gca-prechat-submit__text');
			const spinnerSpan = prechatSubmitBtn.querySelector('.gca-prechat-submit__spinner');

			if (isSubmitting) {
				if (textSpan) textSpan.textContent = i18n.submitting || 'Starting chat...';
				if (spinnerSpan) spinnerSpan.style.display = 'inline';
			} else {
				if (textSpan) textSpan.textContent = i18n.startChat || 'Start Chat';
				if (spinnerSpan) spinnerSpan.style.display = 'none';
			}
		}

		function handlePrechatSubmit() {
			if (!prechatForm) return;

			clearPrechatErrors();

			const prechatConfig = config.prechat || {};
			const formData = new FormData(prechatForm);
			const name = (formData.get('name') || '').trim();
			const email = (formData.get('email') || '').trim();
			const phone = (formData.get('phone') || '').trim();
			const requirement = (formData.get('requirement') || '').trim();
			const websiteUrl = (formData.get('website_url') || '').trim();

			let hasClientErrors = false;
			let firstErrorField = null;

			// 1. Client-side Name validation
			if (prechatConfig.collectName && prechatConfig.requireName && !name) {
				setFieldError('name', i18n.errorRequired || 'Please enter your name.');
				hasClientErrors = true;
				firstErrorField = firstErrorField || prechatForm.querySelector('[name="name"]');
			}

			// 2. Client-side Email validation
			if (prechatConfig.collectEmail) {
				if (prechatConfig.requireEmail && !email) {
					setFieldError('email', i18n.errorRequired || 'Please enter your email address.');
					hasClientErrors = true;
					firstErrorField = firstErrorField || prechatForm.querySelector('[name="email"]');
				} else if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
					setFieldError('email', 'Please enter a valid email address.');
					hasClientErrors = true;
					firstErrorField = firstErrorField || prechatForm.querySelector('[name="email"]');
				}
			}

			// 3. Client-side Phone validation
			if (prechatConfig.collectPhone) {
				if (prechatConfig.requirePhone && !phone) {
					setFieldError('phone', i18n.errorRequired || 'Please enter your phone number.');
					hasClientErrors = true;
					firstErrorField = firstErrorField || prechatForm.querySelector('[name="phone"]');
				} else if (phone && !/^[0-9+\s\-\(\)\.]{6,50}$/.test(phone)) {
					setFieldError('phone', 'Please enter a valid phone number.');
					hasClientErrors = true;
					firstErrorField = firstErrorField || prechatForm.querySelector('[name="phone"]');
				}
			}

			// 4. Client-side Requirement validation
			if (prechatConfig.collectRequirement && prechatConfig.requireRequirement && !requirement) {
				setFieldError('requirement', i18n.errorRequired || 'Please describe how we can help you.');
				hasClientErrors = true;
				firstErrorField = firstErrorField || prechatForm.querySelector('[name="requirement"]');
			}

			if (hasClientErrors) {
				if (firstErrorField) firstErrorField.focus();
				return;
			}

			setPrechatSubmitting(true);

			const payload = {
				session_id: sessionToken,
				name: name,
				email: email,
				phone: phone,
				requirement: requirement,
				website_url: websiteUrl,
			};

			fetch(config.restUrl + '/prechat', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify(payload),
			})
			.then(function (response) {
				return response.json();
			})
			.then(function (data) {
				setPrechatSubmitting(false);

				if (data && data.success) {
					state.prechatCompleted = true;
					try {
						sessionStorage.setItem('gca_prechat_' + sessionToken, '1');
					} catch (e) {}

					switchScreen('chat');
				} else {
					if (data && data.error) {
						if (data.error.fields && typeof data.error.fields === 'object') {
							for (const field in data.error.fields) {
								setFieldError(field, data.error.fields[field]);
							}
						} else {
							showPrechatErrorBanner(data.error.message || i18n.errorGeneric || 'Something went wrong.');
						}
					} else {
						showPrechatErrorBanner(i18n.errorGeneric || 'Something went wrong.');
					}
				}
			})
			.catch(function () {
				setPrechatSubmitting(false);
				showPrechatErrorBanner(i18n.networkError || 'Network connection failed.');
			});
		}

		// Pre-chat Form Submission Listener
		if (prechatForm) {
			prechatForm.addEventListener('submit', function (e) {
				e.preventDefault();
				handlePrechatSubmit();
			});
		}

		// Pre-chat Back Button Listener
		if (prechatBackBtn) {
			prechatBackBtn.addEventListener('click', function () {
				switchScreen('home');
			});
		}

		// Start Conversation Card
		if (startCard) {
			startCard.addEventListener('click', function () {
				const prechat = config.prechat || {};
				if (prechat.enabled && !state.prechatCompleted) {
					switchScreen('prechat');
				} else {
					switchScreen('chat');
				}
			});
		}

		// Send Button Click
		if (sendButton) {
			sendButton.addEventListener('click', function () {
				handleSendMessage();
			});
		}

		// Composer Textarea: Auto-grow & Enter Key Submission with IME Safety
		if (composerInput) {
			composerInput.addEventListener('keydown', function (e) {
				// Prevent premature submit during IME / Asian language composition
				if (e.isComposing || e.keyCode === 229) {
					return;
				}

				if (e.key === 'Enter' && !e.shiftKey) {
					e.preventDefault();
					handleSendMessage();
				}
			});

			composerInput.addEventListener('input', function () {
				this.style.height = 'auto';
				this.style.height = Math.min(this.scrollHeight, 120) + 'px';
			});
		}

		// Global Escape Key Listener for Widget and Confirmation Dialog
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				if (confirmDialog && confirmDialog.style.display === 'flex') {
					hideResetConfirmation();
					e.stopPropagation();
				} else if (isFloating && state.isOpen) {
					toggleFloatingWidget(false);
				}
			}
		});
	}

	// Auto-initialize all widgets when DOM is loaded
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			const widgets = document.querySelectorAll('.gca-widget');
			widgets.forEach(function (widget) {
				initWidgetInstance(widget);
			});
		});
	} else {
		const widgets = document.querySelectorAll('.gca-widget');
		widgets.forEach(function (widget) {
			initWidgetInstance(widget);
		});
	}
})();
