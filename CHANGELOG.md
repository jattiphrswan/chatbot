# Changelog

All notable changes to the **Gemini Chat Assistant** plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0-N10] - 2026-09-07
### Added
- Native WordPress Admin Dashboard Shell (`templates/admin/dashboard.php`) under top-level `Gemini Chat` menu with submenus for `Dashboard` and `Settings`.
- Real-time system and configuration overview cards (Chatbot status, API key status, model slug, database version, message storage, guest access).
- Setup & Verification checklist and Quick Actions navigation panel.
- Scoped admin stylesheet (`admin/css/admin-settings.css`) with responsive 3-column grid, Dashicon integration, and RTL-safe styles.
- Unit and static test suite in `tests/test-dashboard.php`.

## [1.0.0-N9] - 2026-09-07
### Added
- Production-grade frontend client (`public/js/chat.js`) with explicit UX State Model (`isOpen`, `activeScreen`, `isSending`, `unreadCount`, `lastFailedMessage`, `rateLimitRemaining`, `userScrolledUp`).
- Unread message badge on floating launcher incrementing on background assistant responses and clearing on panel open.
- Autoscroll engine with user scroll intent detection and smooth scroll-to-bottom floating action control.
- Rate limit countdown timer handling HTTP 429 `Retry-After` windows with live seconds display.
- One-click retry flow for network/upstream failures without message duplication.
- Reset conversation confirmation modal preventing accidental session loss.
- IME composition safety (`isComposing` and keyCode 229 checks) preventing premature submission during multilingual input.
- Scoped CSS enhancements in `public/css/chat.css` supporting `100dvh` mobile viewports, mobile safe areas (`env(safe-area-inset)`), and `@media (prefers-reduced-motion: reduce)`.
- Standalone test suite in `tests/test-chat-ux.php`.

## [1.0.0-N8] - 2026-09-07
### Added
- Dual-tier transient rate limiter (`includes/class-rate-limiter.php`) enforcing 5-minute and 1-hour session limits with secondary privacy-safe hashed-IP abuse ceiling.
- HTTP 429 response formatting with `Retry-After` header and payload metadata in `includes/class-rest-controller.php`.
- Client IP resolution filter `gca_client_ip` supporting reverse proxies/CDNs with HMAC-SHA256 privacy hashing.
- Complete test suite `tests/test-rate-limiter.php` validating multi-tier limits, session rotation protection, and IP hashing safety.

## [1.0.0-N7] - 2026-09-07
### Added
- Multi-turn conversation memory continuation using Gemini Interactions API `previous_interaction_id`.
- Transient and database interaction ID synchronization (`SessionService::get_interaction_id`, `SessionService::set_interaction_id`, `ConversationRepository::update_interaction_id`).
- Automated stale/expired interaction detection and one-time safe retry recovery.
- Session-isolated reset semantics detaching interaction state.
- Comprehensive conversation memory test suite in `tests/test-conversation-memory.php`.

## [1.0.0-N6] - 2026-09-07
### Added
- Frontend shortcode handler `[gemini_chat]` in `includes/class-shortcode.php`.
- Asset management & script localization service in `includes/class-assets.php`.
- Clean, accessible multi-mode template in `templates/chat-widget.php` supporting both floating and embedded modes.
- Public scoped stylesheet in `public/css/chat.css` with CSS custom properties and mobile-responsive viewport rules down to 320px.
- Vanilla JavaScript client in `public/js/chat.js` with cryptographically secure session generation, navigation between Home and Chat tabs, safe DOM message rendering, and integration with `gca/v1/chat` and `gca/v1/reset`.
- Standalone test suites: `tests/test-shortcode.php`, `tests/test-assets.php`, `tests/test-public-ui.php`.

## [1.0.0-N5] - 2026-09-07
### Added
- WordPress REST API Controller (`includes/class-rest-controller.php`) registering routes `POST /gca/v1/chat`, `POST /gca/v1/reset`, and `GET /gca/v1/health`.
- Input validation service (`includes/class-validator.php`) for messages, session IDs, and context.
- Application orchestration service (`includes/class-chat-service.php`) managing chat flow, session state, Gemini interaction, error mapping, and conditional message persistence.
- Unit and mock test suites: `test-validator.php`, `test-chat-service.php`, `test-rest-controller.php`.

## [1.0.0-N4] - 2026-09-07
### Added
- Google Gemini Interactions API (v1) client (`includes/class-gemini-client.php`) targeting endpoint `https://generativelanguage.googleapis.com/v1/interactions`.
- Server-side key resolution (`GEMINI_API_KEY` / `GCA_GEMINI_API_KEY`), `steps[]` text extraction, usage token parsing, and transport/HTTP error normalization.
- Standalone test suite: `tests/test-gemini-client.php`.

## [1.0.0-N3] - 2026-09-07
### Added
- Database schema migration engine (`includes/Database/Migrator.php`) for `wp_gca_conversations` and `wp_gca_messages`.
- Repository CRUD layers (`ConversationRepository.php`, `MessageRepository.php`).
- Session management service (`SessionService.php`) with SHA-256 HMAC session token hashing and transient caching.
- Standalone test suite: `tests/test-database.php`.

## [1.0.0-N2] - 2026-09-07
### Added
- WordPress Admin Settings System (`SettingsService.php`, `AdminMenu.php`, `templates/admin/settings.php`).
- Server-side secret architecture with zero database storage of API keys.
- Standalone test suite: `tests/test-settings.php`.

## [1.0.0-N1] - 2026-09-07
### Added
- WordPress plugin scaffold with `gemini-chat-assistant.php` entrypoint.
- Core classes: `Plugin` (`includes/class-plugin.php`), `Activator` (`includes/class-activator.php`), and `Deactivator` (`includes/class-deactivator.php`).
- Security silent directory index guards (`index.php`) in root, `admin/`, `public/`, `templates/`, and `languages/`.
- Standard `uninstall.php` lifecycle handler and `readme.txt` documentation.

## [1.0.0-N0] - 2026-09-07
### Added
- Complete project documentation suite across 20 canonical files.
- Architectural design specifications with multi-tier request flow (Browser -> WP REST API -> PHP Service -> Gemini API).
- REST API contracts for `/chat`, `/reset`, and `/health` under namespace `gca/v1`.
- Database shapes for `wp_gca_conversations`, `wp_gca_messages`, and `wp_gca_logs`.
- Component inventories and class mapping for `SkyFish\GeminiChat`.
- Roadmap dependency graph from Node N0 through N10.
- Security and encryption policies (AES-256-GCM, transient rate limiting, zero client-side API key exposure).
