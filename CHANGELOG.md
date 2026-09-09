# Changelog

All notable changes to the **Gemini Chat Assistant** plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-09-09
### Added
- **Multi-AI Provider Foundation:** Native multi-provider support across Google Gemini, OpenAI Responses API (`/v1/responses`), and Anthropic Claude Messages API (`/v1/messages`).
- **Provider & Model Selection:** Centralized `ModelRegistry` and `ProviderSelectionService` with administrator defaults and optional visitor frontend selection controls.
- **Mid-Conversation Provider Switching:** Switch seamlessly between Gemini, OpenAI, and Claude without losing conversation history or context.
- **Secure Credential Architecture:** AES-256-CBC database encryption for API keys in dedicated `gca_provider_credentials` option with server environment variable (`GEMINI_API_KEY`, `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`) and `wp-config.php` constant overrides.
- **Credential Masking:** Regex scrubbing in `ProviderException` guaranteeing zero secret leakage in logs, REST responses, or admin UI notices.
- **AI Profiles & Persona Customizer:** Configurable AI personas with customizable roles, tones, response styles, and behavioral constraints.
- **Website Knowledge & FAQ RAG Grounding:** Untrusted knowledge injection fences protecting against prompt injection attacks.
- **Business Integrations & Escalation:** Human handoff detection, native `wp_mail()` notifications, WooCommerce read catalog queries, and Direct Contact Channels (Phone, Email, WhatsApp).
- **Rate Limiting & Cost Protection:** Dual-tier transient rate limiter enforcing session and IP quotas prior to external AI dispatch.
- **Admin Dashboard & Analytics:** Comprehensive administration suite for settings, appearance customization, conversations, leads, and usage analytics.

## [1.3.4-N17.5] - 2026-09-07
### Added
- Direct Contact Channels Integration (`templates/chat-widget.php`, `public/css/chat.css`, `templates/admin/settings.php`, `includes/Admin/SettingsService.php`, `includes/class-assets.php`):
  - Direct client-side contact channel options (Phone/Call via `tel:`, Email client drafting via `mailto:`, and WhatsApp chat via `https://wa.me/` deep links with optional pre-filled messages).
  - Public Chatbot UI Home screen integration: renders accessible `.gca-contact-channels` container with contact action buttons and icons beneath quick help items.
  - Responsive styling and high-contrast focus rings for contact channel buttons (`.gca-contact-btn--phone`, `.gca-contact-btn--email`, `.gca-contact-btn--whatsapp`).
  - WordPress Admin Settings UI: added dedicated "Contact Channels" tab panel (`#gca-panel-contact`) in `Gemini Chat -> Settings` for configuring individual channel toggles, phone numbers, email addresses, labels, and WhatsApp prefilled messages.
  - Strict input sanitization in `SettingsService`: filters phone numbers to permitted dial characters, WhatsApp numbers to digits and country codes, validates email addresses with `sanitize_email()`, and clamps labels (50 chars) and WhatsApp messages (300 chars).
  - Secure asset localization in `Assets::get_localized_config()` providing client-side runtime access to sanitized contact link objects.
  - Business Integrations screen update (`templates/admin/integrations.php`): displays live active/disabled status and transport specifications for Direct Contact Channels.
  - Zero third-party API footprint: strictly no Meta Graph API, WhatsApp Cloud API, Twilio, SMS APIs, or external CRM webhooks.
  - Comprehensive unit test suite in `tests/test-direct-contact-channels.php` covering defaults, sanitization, URL formation, XSS defense, localization, and negative assertions.

## [1.3.3-N17.4] - 2026-09-07
### Added
- WordPress Native Human Handoff Email Notifications (`includes/notifications/class-notification-service.php`, `includes/Integrations/Handoff/SendHandoffNotificationAction.php`):
  - Service layer `NotificationService` coordinating internal team notifications for new handoff requests via WordPress core `wp_mail()`.
  - Recipient management: supports up to 10 email addresses parsed from comma- or newline-separated input, aggressive whitespace trimming, control-character stripping, and graceful fallback to `get_option('admin_email')`.
  - Email header injection defense: strips CR (`\r`), LF (`\n`), and ASCII control characters from email subjects and recipient inputs.
  - Plain-text notification formatter with strict PII minimization: includes handoff reason label, status, formatted creation timestamp, lead contact details (if captured), and safe admin deep-links (`admin_url()`), while strictly excluding session tokens, visitor IP addresses, Gemini API keys, and raw conversation transcripts.
  - Idempotency protection: 7-day transient marker keyed by handoff public UUID (`gca_notif_sent_{md5}`) prevents duplicate email alerts from repeated chat turns or retries.
  - Error isolation: `wp_mail()` failures are caught and normalized internally (`EMAIL_SEND_FAILED`); handoffs and conversations remain completely unaffected.
  - Business Integration Framework Action `SendHandoffNotificationAction` (`handoff.send_notification`) registering with risk classification `external`.
  - WordPress Admin Settings UI integration: dedicated "Notifications" tab with toggles, recipients textarea, configurable subject line, and explicit "Send Test Email" action guarded by `manage_options` and nonces.
  - Admin Handoff Detail view enhancement: shows live notification dispatch status badge (`Sent`, `Pending / Not Sent`, `Disabled`).
  - Business Integrations dashboard update: displays live status of Email Notifications integration (`wp_mail()` transport).
  - Comprehensive unit test suite in `tests/test-email-notifications.php` covering recipient parsing & limits, subject CRLF stripping, body formatting, idempotency, failure isolation, settings sanitization, and framework action registration.

## [1.3.2-N17.3] - 2026-09-07
### Added
- Native WordPress Human Handoff System (`includes/handoff/`, `includes/Database/HandoffRepository.php`, `includes/Integrations/Handoff/`):
  - Database table `{$wpdb->prefix}gca_handoffs` managed via `Migrator` (schema v1.3.0) with foreign key indices for conversation, lead, status, reason, and unique `public_id`.
  - Data access layer `HandoffRepository` providing CRUD operations, joined queries with conversations and leads, pagination, search, status filtering, and active duplicate detection (`get_active_by_conversation_id`).
  - Core service `HandoffService` managing controlled statuses (`pending`, `assigned`, `resolved`, `cancelled`), controlled reasons (`customer_request`, `unknown_answer`, `complex_question`, `sales_request`, `technical_issue`), regex and keyword intent detection (`detect_handoff_intent`), conversation existence verification, and automatic N14 Lead association without duplicating contact records.
  - Business Integration Framework Action `CreateHandoffAction` (`handoff.create`) registering under `HandoffIntegration` (`handoff`) with risk classification `write` and declarative argument validation.
  - Chat orchestration integration in `ChatService`: evaluates user turns for escalation intents, persists handoff requests atomically, and returns structured `meta.handoff` payload to client.
  - WordPress Admin Handoff Management UI (`templates/admin/handoffs.php` and `templates/admin/handoff-detail.php`) accessible via submenu `Gemini Chat -> Handoffs` (`gca-handoffs`) protected by `manage_options` capability, featuring status filtering, inline badge styling, transcript modal links, and customer contact cards.
  - Comprehensive unit test suite in `tests/test-handoff-service.php` verifying schema constraints, status transitions, intent detection, lead linkage, duplicate prevention, and zero unauthorized external transport/mail calls.

## [1.3.1-N17.2] - 2026-09-07
### Added
- WooCommerce Read-Only Business Integration (`includes/Integrations/WooCommerce/`):
  - Integration adapter `WooCommerceIntegration` registering under slug `woocommerce` with dynamic availability detection via `class_exists('WooCommerce')` and `function_exists('wc_get_products')`.
  - Normalization formatter `WooCommerceFormatter` safely extracting product attributes (`id`, `name`, `url`, `sku`, `price`, `sale_price`, `stock_status`, `category`, `short_description`, `categories`, `description`) without exposing raw models, database errors, or internal credentials.
  - Keyword search action `SearchProductsAction` (`woocommerce.search_products`) querying published products via `wc_get_products()` with query length validation (1-200 chars) and limit clamping (max 10 products).
  - Single product detail action `GetProductAction` (`woocommerce.get_product`) looking up published products by positive integer ID via `wc_get_product()`.
  - Category search action `SearchByCategoryAction` (`woocommerce.search_by_category`) querying published products by category term slug via `wc_get_products()` (max 10 products).
  - Admin integration status in `templates/admin/integrations.php`: displays live WooCommerce installation status and integration availability without clickable transaction/checkout controls.
  - Comprehensive unit test suite in `tests/test-woocommerce-integration.php` covering availability detection, input schema validation, limit bounds, formatted output shapes, non-fatal absence handling, and strict exclusion of cart/checkout/payment operations.

## [1.3.0-N17.1] - 2026-09-07
### Added
- WordPress-native Business Integration Framework Foundation:
  - Integration interface `includes/Integrations/IntegrationInterface.php` declaring operational contracts (`get_id`, `get_name`, `get_description`, `is_available`, `is_enabled`, `get_actions`).
  - Action interface `includes/Integrations/ActionInterface.php` with controlled risk classifications (`read`, `write`, `external`), input schemas, and execution contracts.
  - Normalized action result value object `includes/Integrations/ActionResult.php` guaranteeing immutable outputs with zero credential leakage and zero raw stack traces.
  - Action argument validator `includes/Integrations/ActionValidator.php` providing deterministic type validation (`string`, `integer`, `number`, `boolean`, `enum`), boundary enforcement, and rogue argument stripping.
  - Centralized integration registry `includes/Integrations/IntegrationRegistry.php` with strict slug regex validation (`/^[a-z0-9_-]{2,50}$/`), action index mapping, duplicate registration rejection, and WordPress extensibility hook `do_action( 'gca_register_integrations', $registry )`.
  - Secure action executor `includes/Integrations/ActionExecutor.php` verifying host integration availability, enabled status, input validation, and robust exception containment (`try / catch (\Throwable)`).
  - WordPress Admin Integrations overview screen (`templates/admin/integrations.php`) accessible via submenu `Gemini Chat -> Integrations` (`gca-integrations`) under `manage_options` capability.
  - Dashboard Quick Action link to Business Integrations and Roadmap preview card for Node N17.
  - Comprehensive unit test suite in `tests/test-integrations-framework.php` validating interfaces, registries, validation rules, error sanitization, and security policies.

## [1.2.0-N16] - 2026-09-07
### Added
- Native WordPress FAQ System:
  - Database table `{$wpdb->prefix}gca_faqs` managed via `Migrator` (schema v1.2.0) supporting question, answer, category, sort order, active status, and home visibility.
  - Data access layer `includes/Database/FaqRepository.php` providing CRUD operations, pagination, search, category filtering, active FAQs listing, and home FAQs retrieval.
  - WordPress Admin FAQ Management module (`templates/admin/faqs.php`) accessible via submenu `Gemini Chat -> FAQs` (`gca-faqs`) with search, filter by category, inline add/edit modal, active status toggle, and deletion.
  - Public chat widget Home screen integration: renders active "Quick Help" FAQ buttons. Clicking opens Screen 4 (`gca-screen--faq`) displaying the full answer with static zero-token DOM rendering (zero Gemini API calls).
- Native WordPress Website Knowledge / RAG Engine:
  - Database tables `{$wpdb->prefix}gca_knowledge_sources` and `{$wpdb->prefix}gca_knowledge_chunks` with compound indexing and foreign key cascade deletion.
  - Data access layer `includes/Database/KnowledgeRepository.php` handling source sync, chunk insertion/querying, candidate lexical search, and total chunk/source statistics.
  - Native Indexer `includes/Knowledge/KnowledgeIndexer.php`: content normalization (strips shortcodes without execution, removes HTML and Gutenberg comments), SHA-256 deduplication, multibyte sentence-aware chunking (target ~1,600 chars, 200 overlap), and lifecycle hooks on `save_post`, `before_delete_post`, and `transition_post_status`. Only published, public, non-password-protected Pages/Posts/FAQs/Products (textual only) are indexed.
  - Deterministic Lexical Retriever `includes/Knowledge/KnowledgeRetriever.php`: MySQL-based lexical candidate matching with stopword filtering, term frequency scoring, exact phrase boosting, title matching, FAQ item boost, relevance thresholding, top-K clamping (1–8), and context budget enforcement (500–12000 chars).
  - Knowledge Context Builder `includes/Knowledge/KnowledgeContextBuilder.php`: formats retrieved chunks into untrusted reference data blocks enclosed in explicit safety delimiters, with instructions compelling the LLM to treat content as passive facts, resist prompt-injection overrides, and provide clean source attribution.
  - ChatService integration: automatically grounds system prompt with retrieved context when `knowledge_enabled = true`, seamlessly preserving active N15 AI Profile persona and rules.
  - WordPress Admin Knowledge screen (`templates/admin/knowledge.php`) accessible via submenu `Gemini Chat -> Knowledge` (`gca-knowledge`) with live chunk counts, source toggles, manual sync, index clearing, and retrieval test tool.
- Comprehensive test suites:
  - `tests/test-faqs.php`: repository CRUD, XSS protection, length capping, sort ordering, search, and home filtering.
  - `tests/test-knowledge-indexer.php`: normalization, shortcode stripping, chunking boundaries, SHA-256 deduplication, status/password exclusions.
  - `tests/test-knowledge-retriever.php`: query tokenization, stopwords, candidate scoring, FAQ boost, phrase match boost, thresholding.
  - `tests/test-rag-context.php`: untrusted framing delimiters, prompt-injection defense containment, attribution formatting, zero HTML, N15 profile preservation.

## [1.0.0-N15] - 2026-09-07
### Added
- WordPress-native AI Profiles & Custom Prompts module (`includes/Admin/ProfileService.php`, `templates/admin/ai-assistant.php`, `templates/admin/ai-profile-edit.php`) accessible via submenu `Gemini Chat -> AI Assistant` (`gca-ai-assistant`).
- Configuration storage via WordPress Options API (`gca_ai_profiles`, `autoload = 'no'`) supporting up to 25 configurable AI profiles.
- Seamless, non-destructive migration from legacy `gca_settings['system_instruction']` into a default "General Assistant" profile on first access.
- Centralized deterministic prompt builder in `ProfileService::get_effective_system_instruction()` assembling Persona Role, System Instructions, Tone, Response Style, Behavioral Rules, and Fallback Message guidance.
- Centralized active profile ID management in `gca_settings['active_profile_id']` with safe fallback hierarchy (active profile -> first enabled -> legacy setting fallback -> hardcoded safe default).
- Profile lifecycle operations: Create, Edit, Duplicate (clones with new UUID and inactive status), Delete (with last-profile and active-profile safeguards), and Activate.
- Architectural integration with `ChatService`: `ChatService` queries `ProfileService::get_effective_system_instruction()` while keeping `GeminiClient` as a strictly separated transport layer.
- Enhanced Admin Settings page: "AI & Model" tab replaced editable textarea with an active AI profile reference card and deep-link to AI Assistant, preserving legacy prompt through hidden input during general settings save.
- Dashboard integration: Added "Active AI Profile" status card, "Manage AI Assistant Profiles" Quick Action, and activated Node N15 Roadmap card.
- Comprehensive unit test suite in `tests/test-ai-profiles.php` covering CRUD, sanitization, XSS, whitelist enforcement, deterministic prompt assembly, secret isolation, and ChatService integration.

## [1.0.0-N14] - 2026-09-07
### Added
- Configurable Pre-Chat lead capture form (`templates/chat-widget.php`, `public/js/chat.js`, `public/css/chat.css`) supporting name, email, phone, and inquiry requirement fields with individual collect and require controls.
- Dedicated Leads database schema (`{$wpdb->prefix}gca_leads`) managed via `Migrator` (bumped schema to v1.1.0) storing public UUID, conversation association, user ID, contact info, requirement text, status, and timestamps.
- Database abstraction layer in `includes/Database/LeadRepository.php` providing CRUD operations, server-side pagination, search across name/email/phone/UUID, status filtering, and public ID resolution.
- Lead application service (`includes/class-lead-service.php`) managing honeypot validation, rate limiting, duplicate protection per conversation, `store_leads` privacy enforcement, and conversation linking.
- Authoritative server-side validation in `includes/class-validator.php` (`validate_name`, `validate_email`, `validate_phone`, `validate_requirement`, `validate_prechat`) with support for international phone numbers, Unicode names, and character limits.
- REST API endpoint `POST /gca/v1/prechat` in `includes/class-rest-controller.php` with guest access checks, transient rate limiting, detailed field error responses, and honeypot protection.
- WordPress Admin Leads Management module (`templates/admin/leads.php`, `templates/admin/lead-detail.php`) accessible via submenu `Gemini Chat -> Leads` (`gca-leads`) with search, status filtering (`new`, `contacted`, `closed`), pagination, safe `mailto:`/`tel:` contact links, conversation transcript deep links, status updates, and single lead deletion with nonce verification.
- Admin Dashboard integration linking to Lead Inquiries and active Roadmap status card.
- Standalone test suites in `tests/test-leads.php` and `tests/test-prechat.php`.

## [1.0.0-N13] - 2026-09-07
### Added
- Native WordPress Appearance Builder module (`templates/admin/appearance.php`, `admin/js/admin-appearance.js`) accessible via submenu `Gemini Chat -> Appearance` (`gca-appearance`).
- Chatbot identity controls including Assistant Name, Greeting headline, Welcome description, and Avatar / Logo upload using the native WordPress Media Library modal (`wp.media`) with instant preview and removal.
- Comprehensive color palette controls with two-way synchronization between native HTML5 color pickers and validated 6-digit hex input fields: Primary Accent, Header Background, Header Text, Panel Background, Main Body Text, Assistant Bubble Background/Text, User Bubble Background/Text, Button Background/Text, and Launcher Button Background/Icon.
- Layout, dimension, and launcher customization: Screen Position (`bottom-right`, `bottom-left`), Launcher Icon choices (`chat`, `message`, `headset`, `sparkle`), clamped Desktop Panel Width (320–600px), clamped Desktop Panel Height (450–850px), Border Radius (0–40px), and Launcher Button Diameter (44–80px).
- Device and responsive viewport visibility controls: Desktop (>1024px), Tablet (601px–1024px), and Mobile (<=600px, fullscreen responsive mode).
- Dynamic, scoped CSS custom variable generation (`includes/Admin/AppearanceService.php`) injected cleanly via `wp_add_inline_style()` on frontend asset enqueuing with zero custom CSS textareas.
- Interactive live admin preview sidebar card reflecting all branding text, custom colors, border curvature, launcher icon, and avatar changes in real time without external Gemini API calls.
- Safe Reset to Defaults administrative action (`admin_post_gca_reset_appearance`) resetting all appearance attributes to defaults while strictly preserving AI model, rate limits, API keys, and message storage settings.
- Dashboard integration with Quick Actions linking to Appearance customizer and updated Roadmap status card.
- Comprehensive unit and mock test suite in `tests/test-appearance.php`.

## [1.0.0-N12] - 2026-09-07
### Added
- Native WordPress Admin Analytics & Insights module (`templates/admin/analytics.php`) accessible via submenu `Gemini Chat -> Analytics` (`gca-analytics`).
- Predefined and custom date range filtering (Today, Last 7 Days, Last 30 Days, Last 90 Days, All Time, and custom start/end picker) with full UTC/local timezone translation.
- Real aggregate SQL query layer (`includes/Database/AnalyticsRepository.php`) computing total conversations, distinct unique sessions, stored messages, average turns, token usage, and average assistant latency without loading full rows into PHP memory.
- Business logic service (`includes/Admin/AnalyticsService.php`) handling date bounds, zero-division safety, continuous daily time series timeline generation, and audience/status/model distribution metrics.
- Lightweight, responsive native SVG bar visualization for daily conversation and message trends with accessible HTML table fallback.
- Status breakdown (Active vs Closed percentage bars), Audience breakdown (Guest vs Logged-in Users), and AI Model usage distribution list.
- Dashboard integration with Quick Actions linking to Analytics and updated Roadmap status card.
- Comprehensive unit and mock test suite in `tests/test-analytics.php`.

## [1.0.0-N11] - 2026-09-07
### Added
- WordPress Admin Conversations Management module (`templates/admin/conversations.php`, `templates/admin/conversation-detail.php`) accessible via submenu `Gemini Chat -> Conversations` (`gca-conversations`).
- Server-side paginated conversation list supporting safe status filtering (`all`, `active`, `closed`), search by public UUID or title, and whitelisted column sorting (`created_at`, `updated_at`, `last_message_at`).
- Full conversation transcript view with chronological thread rendering, speaker role distinctions (Visitor, AI Assistant, System), AI model badge indicators, and strict XSS output escaping (`esc_html`, `esc_attr`).
- Admin POST actions with `manage_options` capability check and per-resource nonce verification: Close conversation (`gca_close_conversation`), Reopen conversation (`gca_reopen_conversation`), and Delete conversation (`gca_delete_conversation`).
- Cascading deletion in `ConversationRepository::delete_by_public_id()` cleaning up child messages via `MessageRepository::delete_by_conversation_id()` prior to parent row removal.
- Quick Actions integration on the Admin Dashboard shell linking directly to Conversations management.
- Unit and mock test suite in `tests/test-conversations-page.php`.

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
