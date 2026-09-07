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
| `LogRepository` | `SkyFish\GeminiChat\Database` | Writes logs to `wp_gca_logs`. | `$wpdb` |
| `GeminiClient` | `SkyFish\GeminiChat\Services` | Makes HTTP calls to Google Gemini API endpoints. | `wp_remote_post`, `Encryption` |
| `ContextManager` | `SkyFish\GeminiChat\Services` | Compiles conversation history into Gemini format. | `MessageRepository`, `TokenCounter` |
| `TokenCounter` | `SkyFish\GeminiChat\Services` | Estimates token consumption. | None |
| `PromptBuilder` | `SkyFish\GeminiChat\Services` | Builds prompt with system instructions and page context. | None |
| `ProviderInterface` | `SkyFish\GeminiChat\Providers` | Contract declaring normalized provider operations. | None |
| `ProviderRegistry` | `SkyFish\GeminiChat\Providers` | Registers and resolves AI provider adapters. | `ProviderInterface`, `ProviderException` |
| `ProviderResponse` | `SkyFish\GeminiChat\Providers` | Value object normalizing AI provider completion data. | None |
| `ProviderException` | `SkyFish\GeminiChat\Providers` | Standardized error encapsulation for provider failures. | `\Exception` |
| `GeminiProvider` | `SkyFish\GeminiChat\Providers` | Adapts GeminiClient to ProviderInterface. | `GeminiClient`, `ProviderResponse` |
| `OpenAIProvider` | `SkyFish\GeminiChat\Providers` | Placeholder adapter for OpenAI models (N16). | `ProviderInterface`, `ProviderResponse` |
| `ClaudeProvider` | `SkyFish\GeminiChat\Providers` | Placeholder adapter for Anthropic Claude models (N16). | `ProviderInterface`, `ProviderResponse` |
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
