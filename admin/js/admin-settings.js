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
});
