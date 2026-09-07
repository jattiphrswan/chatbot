# Components Guide: Gemini Chat Assistant

## 1. Architectural Component Map

```
SkyFish\GeminiChat
├── Core/
│   ├── Plugin: Central singleton, initialization, hook orchestrator.
│   ├── Activator: Activation lifecycle handler, triggers schema migration.
│   ├── Deactivator: Deactivation lifecycle handler, cache and transient flushing.
│   └── I18n: Internationalization and text-domain loader (`gemini-chat-assistant`).
├── Database/
│   ├── Migrator: Manages SQL table creations and version bumps via dbDelta.
│   ├── ConversationRepository: CRUD operations for `wp_gca_conversations`.
│   ├── MessageRepository: CRUD operations for `wp_gca_messages`.
│   ├── LeadRepository: CRUD operations for `wp_gca_leads`.
│   ├── AnalyticsRepository: Metrics aggregator across messages and conversations.
│   ├── FaqRepository: CRUD operations, sorting, and Home visibility for `wp_gca_faqs`.
│   ├── KnowledgeRepository: Sources, chunks, and candidate queries for `wp_gca_knowledge_*`.
│   ├── HandoffRepository: CRUD operations, associations, and admin pagination for `wp_gca_handoffs`.
│   └── LogRepository: Append-only logger for `wp_gca_logs`.
├── Handoff/
│   └── HandoffService: Escalation orchestration, intent detection, status transitions, and lead linkage.
├── Knowledge/
│   ├── KnowledgeIndexer: Content extraction, normalization, hashing, multibyte chunking, and sync hooks.
│   ├── KnowledgeRetriever: Lexical tokenization, candidate scoring, top-K clamping, and budget limits.
│   └── KnowledgeContextBuilder: Structured untrusted reference framing and prompt-injection defense.
├── Integrations/
│   ├── IntegrationInterface: Operational contract for WordPress business integrations.
│   ├── ActionInterface: Declarative contract for executable business actions with risk levels.
│   ├── ActionResult: Normalized result object guaranteeing zero raw stack traces or leaks.
│   ├── ActionValidator: Deterministic argument type and bounds validator.
│   ├── IntegrationRegistry: Central registry with ID regex validation and extensibility hook.
│   ├── ActionExecutor: Controlled execution pipeline with availability and enabled checks.
│   ├── WooCommerce/
│   │   ├── WooCommerceIntegration: Read-only business integration for WooCommerce catalog.
│   │   ├── WooCommerceFormatter: Normalizes WC_Product objects into sanitized arrays.
│   │   ├── SearchProductsAction: Read action for searching products by keyword.
│   │   ├── GetProductAction: Read action for single product detail lookup by ID.
│   │   └── SearchByCategoryAction: Read action for querying products by category.
│   └── Handoff/
│       ├── HandoffIntegration: Business integration for human assistance and escalation.
│       ├── CreateHandoffAction: Write action for recording handoff requests with conversation context.
│       └── SendHandoffNotificationAction: External action for internal team email notification dispatches.
├── Notifications/
│   └── NotificationService: Internal operational email dispatcher via wp_mail() with header injection defense and idempotency.
├── Services/
│   ├── GeminiClient: HTTP transport wrapper for Google Gemini REST API.
│   ├── ContextManager: Manages rolling conversational window and Gemini message formatting.
│   ├── TokenCounter: Estimates token count and warns on budget limits.
│   └── PromptBuilder: Injects system instructions, context metadata, and history.
├── Security/
│   ├── RateLimiter: IP and session throttling via WordPress transients.
│   └── NonceValidator: Verifies WP REST nonces and capabilities.
├── REST/
│   ├── RestServer: Registers routes under `gca/v1` namespace.
│   ├── ChatController: Handles `/chat` and `/reset` endpoints.
│   └── HealthController: Handles `/health` diagnostic endpoint.
├── Admin/
│   ├── AdminMenu: Registers WP admin menu entries under `Gemini Chat`.
│   ├── ProfileService: Manages AI profiles, active profile selection, and prompt building.
│   ├── SettingsPage: Admin settings screen for model config, toggles, limits, privacy.
│   ├── LogsPage: Audit trail viewer, conversation inspector, latency metrics.
│   └── HealthPage: Real-time connectivity and status diagnostics page.
└── Shortcode/
    └── ChatShortcode: Handles `[gemini_chat]` shortcode and frontend asset enqueuing.
```

## 2. Frontend Component Map
- **ChatWidgetContainer (`chat.js` / `chat.css`):**
  - Floating trigger button & badge.
  - Chat window modal with header (bot name, status, reset button, close button).
  - Home Screen: Greeting, Start a Conversation card, Quick Help FAQ list.
  - FAQ Detail Screen: Dedicated question view, multiline answer, and "Start a Conversation" CTA.
  - Pre-Chat Screen: Contact inquiry form with validation and honeypot protection.
  - Chat Screen: Message stream list, typing status indicator, composer input, autoscroll.
