# Changelog

All notable changes to the **Gemini Chat Assistant** plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
