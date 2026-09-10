/**
 * Gemini Chat Assistant - Admin Settings JavaScript
 *
 * @package SkyFish\GeminiChat
 */

document.addEventListener('DOMContentLoaded', function () {
	var tabs = document.querySelectorAll('.gca-tab-btn');
	var panels = document.querySelectorAll('.gca-tab-panel');

	if (!tabs.length) {
		return;
	}

	tabs.forEach(function (tab) {
		tab.addEventListener('click', function (e) {
			e.preventDefault();
			var target = this.getAttribute('data-tab');

			tabs.forEach(function (t) {
				t.classList.remove('active');
			});
			panels.forEach(function (p) {
				p.classList.remove('active');
			});

			this.classList.add('active');
			var activePanel = document.getElementById('gca-panel-' + target);
			if (activePanel) {
				activePanel.classList.add('active');
			}
		});
	});

	// Show/Hide password toggle for typed inputs.
	var toggleBtns = document.querySelectorAll('.gca-toggle-visibility-btn');
	toggleBtns.forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			var targetId = this.getAttribute('data-target');
			if (!targetId) {
				return;
			}
			var input = document.getElementById(targetId);
			var icon = this.querySelector('.dashicons');
			if (!input) {
				return;
			}

			if (input.type === 'password') {
				input.type = 'text';
				if (icon) {
					icon.classList.remove('dashicons-visibility');
					icon.classList.add('dashicons-hidden');
				}
			} else {
				input.type = 'password';
				if (icon) {
					icon.classList.remove('dashicons-hidden');
					icon.classList.add('dashicons-visibility');
				}
			}
		});
	});
});
