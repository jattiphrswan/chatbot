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
- **Node N0:** Documentation Baseline (Specification & Architecture Alignment). Zero application code.
- **Node N1:** WordPress Plugin Foundation (Scaffold, entrypoint, lifecycle hooks, directory structure).
- **Node N2:** Admin Settings System (Admin menu, settings storage, configuration UI).
- **Node N3:** Data & Session Foundation (Database schema, tables, migrations, repositories).
- **Node N4:** Gemini Client (Gemini API service, HTTP transport, token estimation, payload builders).
- **Node N5:** WordPress REST API (Route registration, request controllers, permission callbacks).
- **Node N6:** Public Chat UI ([gemini_chat] shortcode, frontend HTML/CSS widget).
- **Node N7:** Conversation Memory (Session management, multi-turn history, context window).
- **Node N8:** Security & Rate Limiting (Encryption engine, rate limiters, nonces).
- **Node N9:** Full Chat UX (Interactive frontend client, markdown parser, event handling).
- **Node N10:** Admin Dashboard Shell (Unified admin dashboard, logs/analytics, health checks).

## 4. Documentation Maintenance Rule
Whenever a schema, endpoint, database column, or component is updated, all corresponding documentation files (`ARCHITECTURE.md`, `API_CONTRACT.md`, `DB_SHAPES.md`, `SCHEMA_INVENTORY.md`, `COMPONENTS.md`, `COMPONENT_INVENTORY.md`, `SECURITY.md`, `TESTING.md`) must be synchronized immediately.
