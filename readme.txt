=== Gemini Chat Assistant ===
Contributors: SkyFish
Tags: ai, chatbot, gemini, chat assistant, google gemini
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enterprise-grade WordPress AI Chat Assistant powered by Google Gemini API.

== Description ==

Gemini Chat Assistant connects your WordPress site to Google Gemini LLMs securely and reliably.

Key features:
* Secure server-side architecture (Gemini API key is never exposed to the client).
* Responsive chat interface embeddable anywhere using `[gemini_chat]`.
* Robust REST API endpoints under `/wp-json/gca/v1/`.
* Admin dashboard with real-time logs, analytics, and health diagnostics.
* Enterprise security: AES-256-GCM encryption, rate limiting, and nonce verification.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/gemini-chat-assistant` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Gemini Chat > Settings** to configure your Google Gemini API Key and preferences.
4. Place the `[gemini_chat]` shortcode on any post, page, or widget area.

== Frequently Asked Questions ==

= Is the Gemini API Key exposed to site visitors? =
No. The API key is stored encrypted on your server and used solely in server-to-server requests.

= Which Gemini models are supported? =
Supported models include `gemini-1.5-flash`, `gemini-1.5-pro`, and `gemini-2.0-flash`.

== Changelog ==

= 1.0.0 =
* Initial release with secure REST architecture, admin dashboard, and shortcode support.
