/**
 * Admin Appearance Builder JS
 *
 * Handles live preview synchronization and WordPress media library avatar upload.
 *
 * @package SkyFish\GeminiChat\Admin
 */

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		initAvatarUploader();
		initColorPickers();
		initLivePreview();
	});

	/**
	 * WordPress Media Library Avatar Uploader.
	 */
	function initAvatarUploader() {
		var selectBtn = document.getElementById('gca-select-avatar-btn');
		var removeBtn = document.getElementById('gca-remove-avatar-btn');
		var avatarInput = document.getElementById('gca-avatar-id');
		var previewBox = document.getElementById('gca-avatar-preview-box');
		var mockAvatarWrap = document.querySelector('.gca-mock-header__avatar');
		var mediaFrame = null;

		if (!selectBtn || !avatarInput || !previewBox) {
			return;
		}

		selectBtn.addEventListener('click', function (e) {
			e.preventDefault();

			if (typeof wp === 'undefined' || !wp.media) {
				return;
			}

			if (mediaFrame) {
				mediaFrame.open();
				return;
			}

			mediaFrame = wp.media({
				title: 'Select Assistant Avatar / Logo',
				button: {
					text: 'Use as Avatar'
				},
				library: {
					type: 'image'
				},
				multiple: false
			});

			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				if (!attachment || !attachment.id) {
					return;
				}

				avatarInput.value = attachment.id;

				var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

				previewBox.innerHTML = '<img src="' + escapeAttr(url) + '" alt="" id="gca-avatar-preview-img" />';
				if (removeBtn) {
					removeBtn.style.display = 'inline-block';
				}

				if (mockAvatarWrap) {
					mockAvatarWrap.innerHTML = '<img src="' + escapeAttr(url) + '" alt="" id="gca-mock-avatar-img" />';
				}
			});

			mediaFrame.open();
		});

		if (removeBtn) {
			removeBtn.addEventListener('click', function (e) {
				e.preventDefault();
				avatarInput.value = '0';
				previewBox.innerHTML = '<span class="dashicons dashicons-format-chat" id="gca-avatar-placeholder-icon"></span>';
				removeBtn.style.display = 'none';

				if (mockAvatarWrap) {
					mockAvatarWrap.innerHTML = '<span class="dashicons dashicons-format-chat" id="gca-mock-avatar-dashicon"></span>';
				}
			});
		}
	}

	/**
	 * Color Pickers & Hex Text Fields Two-Way Synchronization.
	 */
	function initColorPickers() {
		var nativePickers = document.querySelectorAll('.gca-color-picker-native');
		var hexInputs = document.querySelectorAll('.gca-color-hex');

		nativePickers.forEach(function (picker) {
			var targetId = picker.getAttribute('data-sync');
			var hexInput = targetId ? document.getElementById(targetId) : null;

			picker.addEventListener('input', function () {
				if (hexInput) {
					hexInput.value = picker.value.toUpperCase();
					updateMockColors();
				}
			});
		});

		hexInputs.forEach(function (hexInput) {
			hexInput.addEventListener('input', function () {
				var val = hexInput.value.trim();
				if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
					var picker = hexInput.parentElement.querySelector('.gca-color-picker-native');
					if (picker) {
						picker.value = val;
					}
					updateMockColors();
				}
			});
		});
	}

	/**
	 * Live Preview Synchronization.
	 */
	function initLivePreview() {
		var nameInput = document.getElementById('gca-assistant-name');
		var greetingInput = document.getElementById('gca-greeting');
		var descInput = document.getElementById('gca-welcome-message');

		var mockName = document.getElementById('gca-mock-name');
		var mockGreeting = document.getElementById('gca-mock-greeting');
		var mockDesc = document.getElementById('gca-mock-desc');

		if (nameInput && mockName) {
			nameInput.addEventListener('input', function () {
				mockName.textContent = nameInput.value || 'AI Assistant';
			});
		}

		if (greetingInput && mockGreeting) {
			greetingInput.addEventListener('input', function () {
				var strong = mockGreeting.querySelector('strong');
				if (strong) {
					strong.textContent = (greetingInput.value || 'Welcome!') + ' 👋';
				}
			});
		}

		if (descInput && mockDesc) {
			descInput.addEventListener('input', function () {
				mockDesc.textContent = descInput.value || '';
			});
		}

		// Border Radius
		var radiusInput = document.getElementById('gca-border-radius');
		var previewWrap = document.getElementById('gca-mock-preview-wrap');
		if (radiusInput && previewWrap) {
			radiusInput.addEventListener('input', function () {
				var r = parseInt(radiusInput.value, 10);
				if (!isNaN(r)) {
					previewWrap.style.borderRadius = r + 'px';
				}
			});
		}

		// Launcher Icons
		var iconRadios = document.querySelectorAll('input[name*="[launcher_icon]"]');
		var mockLauncherIcon = document.querySelector('.gca-mock-launcher .dashicons');
		var iconMap = {
			chat: 'dashicons-format-chat',
			message: 'dashicons-email',
			headset: 'dashicons-phone',
			sparkle: 'dashicons-star-filled'
		};

		iconRadios.forEach(function (radio) {
			radio.addEventListener('change', function () {
				if (radio.checked && mockLauncherIcon && iconMap[radio.value]) {
					mockLauncherIcon.className = 'dashicons ' + iconMap[radio.value];
				}
			});
		});
	}

	/**
	 * Updates the live mock colors according to current field values.
	 */
	function updateMockColors() {
		var headerBg = getVal('gca-color-header-bg');
		var headerText = getVal('gca-color-header-text');
		var panelBg = getVal('gca-color-panel-bg');
		var bodyText = getVal('gca-color-text');
		var aiBubble = getVal('gca-color-ai-bubble');
		var aiText = getVal('gca-color-ai-text');
		var userBubble = getVal('gca-color-user-bubble');
		var userText = getVal('gca-color-user-text');
		var btnColor = getVal('gca-color-button');
		var launcherBg = getVal('gca-color-launcher-bg');
		var launcherIcon = getVal('gca-color-launcher-icon');

		var mockHeader = document.querySelector('.gca-mock-header');
		if (mockHeader) {
			if (headerBg) mockHeader.style.backgroundColor = headerBg;
			if (headerText) mockHeader.style.color = headerText;
		}

		var mockBody = document.querySelector('.gca-mock-body');
		if (mockBody) {
			if (panelBg) mockBody.style.backgroundColor = panelBg;
			if (bodyText) mockBody.style.color = bodyText;
		}

		var mockAiBubble = document.querySelector('.gca-mock-bubble--ai');
		if (mockAiBubble) {
			if (aiBubble) mockAiBubble.style.backgroundColor = aiBubble;
			if (aiText) mockAiBubble.style.color = aiText;
		}

		var mockUserBubble = document.querySelector('.gca-mock-bubble--user');
		if (mockUserBubble) {
			if (userBubble) mockUserBubble.style.backgroundColor = userBubble;
			if (userText) mockUserBubble.style.color = userText;
		}

		var mockBtn = document.querySelector('.gca-mock-btn');
		if (mockBtn) {
			if (btnColor) mockBtn.style.backgroundColor = btnColor;
		}

		var mockLauncher = document.querySelector('.gca-mock-launcher');
		if (mockLauncher) {
			if (launcherBg) mockLauncher.style.backgroundColor = launcherBg;
			if (launcherIcon) mockLauncher.style.color = launcherIcon;
		}
	}

	function getVal(id) {
		var el = document.getElementById(id);
		return el && el.value ? el.value.trim() : null;
	}

	function escapeAttr(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}
})();
