# Project Instructions & Engineering Rules

## 1. Project Identity & Boundaries
- **Plugin Name:** `gemini-chat-assistant`
- **PHP Root Namespace:** `SkyFish\GeminiChat`
- **REST Namespace:** `gca/v1`
- **Shortcode:** `[gemini_chat]`
- **Coding Standard:** WordPress Coding Standards (WPCS) with strict PHP 8.0+ typing and PSR-4 autoloading.

## 2. Architectural Rules (Non-Negotiable)
1. **Zero Client-Side Secret Exposure:** Under no circumstances should the Gemini API key, encryption keys, or server secrets be exposed in HTML, JS scripts, data attributes, localized script variables, or client responses.
2. **REST API Gateway:** All client interactions must flow exclusively through the WordPress REST API under `/wp-json/gca/v1/`. Direct AJAX (`admin-ajax.php`) is strictly forbidden.
3. **Database Layer Discipline:** All database writes and queries must use WordPress `$wpdb->prepare()` with explicit types and table prefixes (`{$wpdb->prefix}gca_*`).
4. **Defense-in-Depth Security:**
   - Public chat endpoints must validate `X-WP-Nonce` and enforce IP-based / session-based rate limits.
   - Admin settings and diagnostic endpoints must check `current_user_can('manage_options')` and administrative nonces.
   - All input must be sanitized via `sanitize_text_field`, `wp_unslash`, etc.
   - All output must be escaped via `esc_html`, `esc_attr`, `wp_json_encode`, or `wp_kses_post`.

## 3. Node Execution Protocol
The project progresses strictly through sequentially defined nodes (N0 through N10).
- **Node N0:** Documentation Validation & Specification Alignment (Current Node). No application code may be written in N0.
- **Node N1:** Plugin scaffold, directory layout, composer autoloading, and lifecycle hooks.
- **Node N2:** Database schema definitions, activation routines, and migration engine.
- **Node N3:** Gemini API client, HTTP service layer, payload builders, and token calculators.
- **Node N4:** WordPress REST API route registration, schema validation, and request controllers.
- **Node N5:** Admin management dashboard, settings panel, logs viewer, and health diagnostics.
- **Node N6:** Frontend chat widget, asset enqueuing, and `[gemini_chat]` shortcode implementation.
- **Node N7:** Session management, rolling context window, and chat reset handlers.
- **Node N8:** Security hardening, encryption engine, rate limiter, and error shields.
- **Node N9:** Comprehensive test suites (Unit, Integration, REST API, UI).
- **Node N10:** Release packaging, production asset minification, and deployment readiness.

## 4. Documentation Maintenance Rule
Whenever a schema, endpoint, database column, or component is updated, all corresponding documentation files (`ARCHITECTURE.md`, `API_CONTRACT.md`, `DB_SHAPES.md`, `SCHEMA_INVENTORY.md`, `COMPONENTS.md`, `COMPONENT_INVENTORY.md`, `SECURITY.md`, `TESTING.md`) must be synchronized immediately.
