# System Architecture: Gemini Chat Assistant

## 1. High-Level Architecture

The architecture of **Gemini Chat Assistant** is strictly tiered and follows a unidirectional request-response pipeline.

```
┌─────────────────────────────────────────────────────────────┐
│                       Client Browser                        │
│   - Frontend Chat UI Widget (JavaScript / CSS / Shortcode)  │
│   - Admin Dashboard UI (Settings, Logs, Health Checks)      │
└──────────────────────────────┬──────────────────────────────┘
                               │  HTTPS Request (REST)
                               │  Headers: X-WP-Nonce, Content-Type: application/json
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                 WordPress REST API Layer                    │
│                     (Namespace: gca/v1)                     │
│  - Endpoint: POST /wp-json/gca/v1/chat                      │
│  - Endpoint: POST /wp-json/gca/v1/reset                     │
│  - Endpoint: GET  /wp-json/gca/v1/health                    │
└──────────────────────────────┬──────────────────────────────┘
                               │  Validated Request Object
                               ▼
┌─────────────────────────────────────────────────────────────┐
│               PHP Application & Service Layer               │
│               (SkyFish\GeminiChat Namespace)                │
│                                                             │
│  ┌────────────────────────┐    ┌─────────────────────────┐  │
│  │    Security Engine     │    │   Repository & DB Layer │  │
│  │  - NonceValidator      │    │  - ConversationRepo     │  │
│  │  - RateLimiter         │    │  - MessageRepository    │  │
│  │  - Encryption (AES)    │    │  - LogRepository        │  │
│  └────────────────────────┘    └─────────────────────────┘  │
│                                                             │
│  ┌────────────────────────┐    ┌─────────────────────────┐  │
│  │    Service Engine      │    │   Gemini Client Engine  │  │
│  │  - ContextManager      │    │  - GeminiClient (cURL)  │  │
│  │  - PromptBuilder       │    │  - Payload Sanitizer    │  │
│  │  - TokenCounter        │    │  - Error Normalizer     │  │
│  └────────────────────────┘    └─────────────────────────┘  │
└──────────────────────────────┬──────────────────────────────┘
                               │  Encrypted Server-to-Server HTTPS
                               │  Authorization / API Key in Server Env/Header
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                   Google Gemini API Gateway                 │
│         https://generativelanguage.googleapis.com/          │
│                (v1beta models:generateContent)              │
└─────────────────────────────────────────────────────────────┘
```

## 2. Structural Breakdown

### 2.1 Frontend Tier
- **Public Chat Shortcode:** `[gemini_chat]` renders the chat widget on any post or page.
- **Client Script:** `assets/js/frontend-chat.js` interacts with the REST endpoints using `fetch` API. Nonces are passed via `X-WP-Nonce`.
- **Client Styles:** `assets/css/frontend-chat.css` provides responsive styles and theme isolation.

### 2.2 WordPress REST API Tier
- **Namespace:** `gca/v1`
- **Controllers:**
  - `SkyFish\GeminiChat\REST\ChatController`: Registers and handles `/chat` and `/reset` routes.
  - `SkyFish\GeminiChat\REST\HealthController`: Registers and handles `/health` route.

### 2.3 PHP Application & Service Tier
- **Core Orchestration (`SkyFish\GeminiChat\Core\Plugin`):** Bootstraps services, hooks into WordPress lifecycle, registers autoloader, and manages singleton container.
- **Gemini Client (`SkyFish\GeminiChat\Services\GeminiClient`):** Communicates with Google Gemini API endpoint via `wp_remote_post`. Manages payload assembly, timeout handling, error mapping, and response parsing.
- **Context Manager (`SkyFish\GeminiChat\Services\ContextManager`):** Retrieves previous message history for a session, formats conversation history according to Gemini multi-turn format, and applies token/message window constraints.
- **Security & Rate Limiting (`SkyFish\GeminiChat\Security\*`):** Enforces transient-based rate limits per IP/session, validates nonces, and manages server-side credential isolation.

### 2.4 Data Tier
- Custom database tables prefixed with `{$wpdb->prefix}gca_`:
  1. `gca_conversations`: Stores conversation session metadata.
  2. `gca_messages`: Stores individual user and assistant messages.
  3. `gca_logs`: Stores system events, API latency, token metrics, and errors.
- WordPress Options (`wp_options`):
  - `gca_settings`: Serialized array of configuration settings (model, system instructions, rate limits, widget toggles).
  - `gca_db_version`: Current schema migration version.

> **CRITICAL CREDENTIAL ARCHITECTURE:**
> The Gemini API Key is **NEVER** stored in `gca_settings` or `wp_options`. It is loaded strictly from the server environment (`GEMINI_API_KEY`) with fallback to the `GCA_GEMINI_API_KEY` constant in `wp-config.php`.

## 3. Security Boundary & Data Flow

```
+-------------------------------------------------------------+
| Browser Context (Untrusted)                                 |
| - Zero knowledge of Gemini API Key                          |
| - Supplies: Session ID, User Message, WP REST Nonce         |
+------------------------------+------------------------------+
                               |
                               | (HTTPS REST Call)
                               v
+-------------------------------------------------------------+
| Server Context (Trusted WordPress Environment)              |
| - Validates Nonce & Rate Limits                             |
| - Retrieves Gemini API Key (GEMINI_API_KEY / wp-config)     |
| - Builds Context & Calls Gemini API                         |
| - Logs Metrics & Saves Message History                      |
+------------------------------+------------------------------+
                               |
                               | (Server-to-Server HTTPS)
                               v
+-------------------------------------------------------------+
| Google Gemini API Context (External Cloud)                  |
| - Generates completions based on system + conversation data |
+-------------------------------------------------------------+
```

## 4. Multi-Provider AI Architecture

```
Browser
  ↓
WordPress Chat Layer
  ↓
ProviderRegistry
  ↓
ProviderInterface
  ├── GeminiProvider
  ├── OpenAIProvider
  └── ClaudeProvider
```

The system employs a provider abstraction layer decoupling conversational controllers from concrete AI vendor SDKs. The `ProviderRegistry` resolves implementations of `ProviderInterface` (`GeminiProvider`, `OpenAIProvider`, `ClaudeProvider`) dynamically based on administrator configuration, ensuring normalized input formatting and response structures via `ProviderResponse`.

