# Component Inventory: Gemini Chat Assistant

| Component Class / File | Namespace / Layer | Primary Responsibility | Dependencies |
| :--- | :--- | :--- | :--- |
| `Plugin` | `SkyFish\GeminiChat\Core` | Bootstraps plugin, hooks into WP lifecycle, registers services. | `Activator`, `Deactivator`, `RestServer`, `AdminMenu`, `ChatShortcode` |
| `Activator` | `SkyFish\GeminiChat\Core` | Executes activation routines, triggers database migrations. | `Migrator` |
| `Deactivator` | `SkyFish\GeminiChat\Core` | Cleans transients, flushes rewrite rules. | WordPress Core |
| `I18n` | `SkyFish\GeminiChat\Core` | Loads translation files. | WordPress Core |
| `Migrator` | `SkyFish\GeminiChat\Database` | Runs `dbDelta()` to create or upgrade `wp_gca_*` tables. | `$wpdb`, `wp-admin/includes/upgrade.php` |
| `ConversationRepository` | `SkyFish\GeminiChat\Database` | Handles CRUD operations for `wp_gca_conversations`. | `$wpdb` |
| `MessageRepository` | `SkyFish\GeminiChat\Database` | Handles CRUD operations for `wp_gca_messages`. | `$wpdb` |
| `LeadRepository` | `SkyFish\GeminiChat\Database` | Handles CRUD operations for `wp_gca_leads`. | `$wpdb` |
| `AnalyticsRepository` | `SkyFish\GeminiChat\Database` | Computes KPIs and trends across conversations and messages. | `$wpdb` |
| `FaqRepository` | `SkyFish\GeminiChat\Database` | Handles CRUD, search, pagination, and Home queries for `wp_gca_faqs`. | `$wpdb` |
| `KnowledgeRepository` | `SkyFish\GeminiChat\Database` | Handles sources, chunks, and candidate search for `wp_gca_knowledge_*`. | `$wpdb` |
| `KnowledgeIndexer` | `SkyFish\GeminiChat\Knowledge` | Content extraction, normalization, hashing, multibyte chunking, and sync hooks. | `KnowledgeRepository`, `FaqRepository`, `SettingsService` |
| `KnowledgeRetriever` | `SkyFish\GeminiChat\Knowledge` | Lexical search, candidate scoring, top-K clamping, and budget limits. | `KnowledgeRepository`, `SettingsService` |
| `KnowledgeContextBuilder` | `SkyFish\GeminiChat\Knowledge` | Structured untrusted reference context framing and prompt-injection defense. | None |
| `IntegrationInterface` | `SkyFish\GeminiChat\Integrations` | Operational contract for business integrations. | None |
| `ActionInterface` | `SkyFish\GeminiChat\Integrations` | Declarative contract for executable business actions with risk levels. | None |
| `ActionResult` | `SkyFish\GeminiChat\Integrations` | Normalized result object with safe error codes and zero raw traces. | None |
| `ActionValidator` | `SkyFish\GeminiChat\Integrations` | Deterministic parameter validation, type casting, and rogue argument rejection. | None |
| `IntegrationRegistry` | `SkyFish\GeminiChat\Integrations` | Central registry for approved business integrations with duplicate protection. | `gca_register_integrations` hook |
| `ActionExecutor` | `SkyFish\GeminiChat\Integrations` | Safe execution pipeline enforcing availability, enabled state, and exception safety. | `IntegrationRegistry`, `ActionValidator` |
| `LogRepository` | `SkyFish\GeminiChat\Database` | Writes logs to `wp_gca_logs`. | `$wpdb` |
| `GeminiClient` | `SkyFish\GeminiChat\Services` | Makes HTTP calls to Google Gemini API endpoints. | `wp_remote_post`, `Encryption` |
| `ContextManager` | `SkyFish\GeminiChat\Services` | Compiles conversation history into Gemini format. | `MessageRepository`, `TokenCounter` |
| `TokenCounter` | `SkyFish\GeminiChat\Services` | Estimates token consumption. | None |
| `PromptBuilder` | `SkyFish\GeminiChat\Services` | Builds prompt with system instructions and page context. | None |
| `Encryption` | `SkyFish\GeminiChat\Security` | Encrypts/decrypts API key using `openssl_encrypt`. | `AUTH_KEY`, `SECURE_AUTH_KEY` |
| `RateLimiter` | `SkyFish\GeminiChat\Security` | Enforces request rate limits via transients. | WordPress Transients API |
| `NonceValidator` | `SkyFish\GeminiChat\Security` | Validates `X-WP-Nonce` and capabilities. | `wp_verify_nonce` |
| `RestServer` | `SkyFish\GeminiChat\REST` | Registers REST routes under namespace `gca/v1`. | `register_rest_route` |
| `ChatController` | `SkyFish\GeminiChat\REST` | Handles `POST /wp-json/gca/v1/chat` and `POST /reset`. | `GeminiClient`, `ContextManager`, `RateLimiter` |
| `HealthController` | `SkyFish\GeminiChat\REST` | Handles `GET /wp-json/gca/v1/health`. | `Migrator`, `GeminiClient` |
| `AdminMenu` | `SkyFish\GeminiChat\Admin` | Adds admin pages under WordPress dashboard. | `add_menu_page`, `add_submenu_page` |
| `ProfileService` | `SkyFish\GeminiChat\Admin` | Manages AI profiles, active selection, and prompt construction. | `SettingsService`, `wp_options` |
| `SettingsPage` | `SkyFish\GeminiChat\Admin` | Renders settings screen. | `Encryption` |
| `LogsPage` | `SkyFish\GeminiChat\Admin` | Renders audit logs screen. | `LogRepository`, `ConversationRepository` |
| `HealthPage` | `SkyFish\GeminiChat\Admin` | Renders diagnostics & self-test screen. | `HealthController` |
| `ChatShortcode` | `SkyFish\GeminiChat\Shortcode` | Enqueues scripts and renders `[gemini_chat]`. | `wp_enqueue_script`, `wp_localize_script` |
| `frontend-chat.js` | `Assets / Frontend` | Handles UI events, REST fetch, message rendering. | WordPress REST API |
| `frontend-chat.css` | `Assets / Frontend` | Styles chat widget, bubble, animations. | Modern CSS3 |
| `admin-dashboard.js` | `Assets / Admin` | Handles admin settings AJAX, test connections. | WordPress REST API |
| `admin-dashboard.css`| `Assets / Admin` | Styles admin settings and logs tables. | WP Admin CSS |
