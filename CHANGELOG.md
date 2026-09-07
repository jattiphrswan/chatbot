# Changelog

All notable changes to the **Gemini Chat Assistant** plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
