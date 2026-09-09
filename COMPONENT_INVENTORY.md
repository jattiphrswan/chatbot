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
| `WooCommerceIntegration` | `SkyFish\GeminiChat\Integrations\WooCommerce` | Read-only business integration contract for WooCommerce catalog. | `SearchProductsAction`, `GetProductAction`, `SearchByCategoryAction` |
| `WooCommerceFormatter` | `SkyFish\GeminiChat\Integrations\WooCommerce` | Normalizes WC_Product objects into sanitized product summary/detail arrays. | None |
| `SearchProductsAction` | `SkyFish\GeminiChat\Integrations\WooCommerce` | Read-only action executing keyword product queries via `wc_get_products()`. | `wc_get_products()`, `WooCommerceFormatter` |
| `GetProductAction` | `SkyFish\GeminiChat\Integrations\WooCommerce` | Read-only action retrieving single product details via `wc_get_product()`. | `wc_get_product()`, `WooCommerceFormatter` |
| `SearchByCategoryAction` | `SkyFish\GeminiChat\Integrations\WooCommerce` | Read-only action querying products by category term slug via `wc_get_products()`. | `wc_get_products()`, `WooCommerceFormatter` |
| `HandoffRepository` | `SkyFish\GeminiChat\Database` | CRUD operations and admin joins for `wp_gca_handoffs`. | `$wpdb` |
| `HandoffService` | `SkyFish\GeminiChat\Handoff` | Orchestrates escalation detection, controlled status transitions, and lead linkage. | `HandoffRepository`, `ConversationRepository`, `LeadRepository`, `NotificationService` |
| `HandoffIntegration` | `SkyFish\GeminiChat\Integrations\Handoff` | Human handoff integration adapter registered under slug `handoff`. | `CreateHandoffAction`, `SendHandoffNotificationAction`, `HandoffService` |
| `CreateHandoffAction` | `SkyFish\GeminiChat\Integrations\Handoff` | Write action for recording handoff requests with conversation context. | `HandoffService` |
| `SendHandoffNotificationAction` | `SkyFish\GeminiChat\Integrations\Handoff` | External action for dispatching handoff notification emails via `wp_mail()`. | `NotificationService`, `HandoffRepository` |
| `NotificationService` | `SkyFish\GeminiChat\Notifications` | Internal team email notification dispatcher via WordPress native `wp_mail()`. | `wp_mail()`, `SettingsService`, `HandoffRepository`, Transients |
| `Direct Contact Channels` | `Frontend / Templates / Assets` | Configurable direct contact options (Phone, Email, WhatsApp) with deep links. | `SettingsService`, `Assets`, `chat-widget.php`, `chat.css` |
| `ProviderInterface` | `SkyFish\GeminiChat\Providers` | Contract for unified AI provider adapters. | None |
| `AbstractProvider` | `SkyFish\GeminiChat\Providers` | Base class for AI providers implementing common getters, configuration checks, and validation. | `ProviderInterface`, `SettingsService`, `ProviderException` |
| `ProviderRegistry` | `SkyFish\GeminiChat\Providers` | Container registry for AI providers with enabled/configured queries. | `ProviderInterface` |
| `ModelRegistry` | `SkyFish\GeminiChat\Providers` | Centralized registry and metadata provider for all supported AI models. | None |
| `ProviderSelectionService` | `SkyFish\GeminiChat\Providers` | Selection resolution, usability assertions, and safe public metadata generation. | `ProviderRegistry`, `SettingsService`, `ModelRegistry` |
| `ProviderResponse` | `SkyFish\GeminiChat\Providers` | Normalized value object for multi-provider AI responses. | None |
| `ProviderException` | `SkyFish\GeminiChat\Providers` | Normalized domain exception with automated credential masking. | None |
| `GeminiProvider` | `SkyFish\GeminiChat\Providers` | Google Gemini AI provider implementation extending AbstractProvider and wrapping `GeminiClient`. | `GeminiClient`, `AbstractProvider` |
| `OpenAIClient` | `SkyFish\GeminiChat\Providers` | Dedicated HTTP client executing requests against OpenAI Responses API (`/v1/responses`). | `wp_remote_post`, `ProviderException`, `ProviderResponse` |
| `OpenAIProvider` | `SkyFish\GeminiChat\Providers` | Live OpenAI AI provider adapter extending AbstractProvider (delegates to OpenAIClient). | `OpenAIClient`, `SettingsService`, `AbstractProvider`, `ProviderResponse` |
| `ClaudeClient` | `SkyFish\GeminiChat\Providers` | Dedicated HTTP client executing requests against Anthropic Messages API (`/v1/messages`). | `wp_remote_post`, `ProviderException`, `ProviderResponse` |
| `ClaudeProvider` | `SkyFish\GeminiChat\Providers` | Live Anthropic Claude AI provider adapter extending AbstractProvider (delegates to ClaudeClient). | `ClaudeClient`, `SettingsService`, `AbstractProvider`, `ProviderResponse` |
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
