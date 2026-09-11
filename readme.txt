=== Gemini Chat Assistant ===
Contributors: SkyFish
Tags: ai, chatbot, gemini, chat assistant, google gemini
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.0.3
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
2. Define your `GEMINI_API_KEY` environment variable or add `define('GCA_GEMINI_API_KEY', 'your_key_here');` in your `wp-config.php`.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Navigate to **Gemini Chat > Settings** to configure model (`gemini-3.8-flash`), system prompts, and behavior.
5. Place the `[gemini_chat]` shortcode on any post, page, or widget area.

== Frequently Asked Questions ==

= Is the Gemini API Key exposed to site visitors? =
No. The API key is loaded server-side only from environment variables or `wp-config.php` and is never exposed to the client or saved in the database.

= Which Gemini model is configured by default? =
The default model is `gemini-3.8-flash` (configurable in settings).

== Changelog ==

= 1.0.0 =
* Initial release with secure REST architecture, admin dashboard, and shortcode support.
