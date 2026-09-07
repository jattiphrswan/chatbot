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
│   └── LogRepository: Append-only logger for `wp_gca_logs`.
├── Services/
│   ├── GeminiClient: HTTP transport wrapper for Google Gemini REST API.
│   ├── ContextManager: Manages rolling conversational window and Gemini message formatting.
│   ├── TokenCounter: Estimates token count and warns on budget limits.
│   └── PromptBuilder: Injects system instructions, context metadata, and history.
├── Providers/
│   ├── ProviderInterface: Unified contract for all AI model providers.
│   ├── ProviderRegistry: Central provider registration and resolution service.
│   ├── ProviderResponse: Normalized provider response value object.
│   ├── ProviderException: Standardized provider error classifications.
│   ├── GeminiProvider: Production adapter wrapping GeminiClient.
│   ├── OpenAIProvider: Placeholder adapter for OpenAI models.
│   └── ClaudeProvider: Placeholder adapter for Anthropic Claude models.
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
- **ChatWidgetContainer (`frontend-chat.js` / `frontend-chat.css`):**
  - Floating trigger button & badge.
  - Chat window modal with header (bot name, status, reset button, close button).
  - Message stream list (user bubbles, assistant bubbles, markdown rendered blocks).
  - Typing / streaming status indicator.
  - Input form with text input, send button, and keyboard shortcuts (`Enter` to send, `Shift+Enter` for newline).
