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
│                    (v1/interactions API)                    │
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
- **Gemini Client (`SkyFish\GeminiChat\GeminiClient`):** Communicates with Google Gemini Interactions API (`/v1/interactions`) via WordPress `wp_remote_post()`. Handles server-side API keys (`x-goog-api-key` header), payload assembly (`model`, `input`, `system_instruction`, `previous_interaction_id`), `steps[]` response parsing, usage metrics, and error normalization.
- **AI Profiles & Prompt Service (`SkyFish\GeminiChat\Admin\ProfileService`):** Manages AI personas, instructions, tone, response style, behavioral rules, fallback message guidance, and deterministic effective system instruction construction.
  - *Prompt Precedence Hierarchy:* Active Profile prompt &rarr; legacy `system_instruction` fallback &rarr; hardcoded safe default (`You are a helpful customer support assistant for this website.`).
- **Context Manager (`SkyFish\GeminiChat\Services\ContextManager`):** Retrieves previous message history for a session, formats conversation history according to Gemini multi-turn format, and applies token/message window constraints.
- **Security & Rate Limiting (`SkyFish\GeminiChat\Security\*`):** Enforces transient-based rate limits per IP/session, validates nonces, and manages server-side credential isolation.

### 2.4 Data Tier
- Custom database tables prefixed with `{$wpdb->prefix}gca_`:
  1. `gca_conversations`: Stores conversation session metadata.
  2. `gca_messages`: Stores individual user and assistant messages.
  3. `gca_leads`: Stores pre-chat lead inquiries and contact information.
  4. `gca_logs`: Stores system events, API latency, token metrics, and errors.
- WordPress Options (`wp_options`):
  - `gca_settings`: Serialized array of configuration settings (model, active_profile_id, rate limits, widget toggles).
  - `gca_ai_profiles`: Serialized array of up to 25 configured AI profiles (`autoload = 'no'`).
  - `gca_provider_credentials`: Dedicated option (`autoload = 'no'`) storing AES-256-CBC encrypted provider API keys (N18).
  - `gca_db_version`: Current schema migration version.

> **CRITICAL CREDENTIAL ARCHITECTURE (N18):**
> API credentials are **NEVER** stored in `gca_settings` or returned in client-side HTML/REST responses. They are loaded strictly with precedence:
> 1. Server environment variables (`GEMINI_API_KEY`, `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`)
> 2. `wp-config.php` constants (`GCA_GEMINI_API_KEY`, `GCA_OPENAI_API_KEY`, `GCA_CLAUDE_API_KEY`)
> 3. Encrypted database credentials in `gca_provider_credentials` (AES-256-CBC with `AUTH_KEY` salt). Empty form submissions never overwrite stored keys.

### 2.5 Multi-Provider Architecture (N18–N21)
- **`ProviderInterface` (`SkyFish\GeminiChat\Providers\ProviderInterface`):** Strict contract standardizing multi-provider access across AI backends (`get_id()`, `get_name()`, `get_models()`, `chat()`, `test_connection()`).
- **`AbstractProvider` (`SkyFish\GeminiChat\Providers\AbstractProvider`):** Base class encapsulating shared ID, name, model list, configuration checks, and API key lookup routines (N19.5 DRY refactor).
- **`ProviderRegistry` (`SkyFish\GeminiChat\Providers\ProviderRegistry`):** Central container for registering and retrieving configured AI provider instances (`gemini`, `openai`, `claude`).
- **`ModelRegistry` (`SkyFish\GeminiChat\Providers\ModelRegistry`):** Centralized single source of truth for supported model identifiers, human-readable labels, and defaults across all providers (`gemini`, `openai`, `claude`).
- **`ProviderSelectionService` (`SkyFish\GeminiChat\Providers\ProviderSelectionService`):** Domain service decoupling selection resolution from controllers. Resolves effective provider and model based on admin defaults, public visitor toggles, and strict provider usability checks (`assert_provider_usable`). Generates safe public metadata without exposing secrets.
- **`GeminiProvider`:** First-party provider wrapping `GeminiClient` with model metadata queried dynamically from `ModelRegistry`.
- **`OpenAIClient` & `OpenAIProvider`:** Live operational OpenAI provider adapter communicating via POST `/v1/responses` with structured instruction, multi-turn history mapping, and safe credential isolation (Node N19).
- **`ClaudeClient` & `ClaudeProvider`:** Live operational Anthropic Claude provider adapter communicating via POST `/v1/messages` with top-level `system` instruction, multi-turn history mapping, `max_tokens`, multiple content block concatenation, and safe credential isolation (Node N20).


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

## 4. FAQ & WordPress-Native RAG Architecture (Node N16)

```
Visitor Question
       ↓
ChatService
       ├── ProfileService (Active N15 AI Profile)
       └── KnowledgeRetriever (when knowledge_enabled = true)
                 ↓
           Tokenize & Filter Stopwords
                 ↓
           MySQL Candidate Search (gca_knowledge_chunks)
                 ↓
           Lexical Scoring (Term freq, Phrase match, FAQ boost)
                 ↓
           Top-K Clamping (1–8) & Context Budget (500–12000 chars)
                 ↓
           KnowledgeContextBuilder (Untrusted Reference Data Framing)
                 ↓
Combined Prompt: [AI Profile Instructions] + [Website Reference Context]
                 ↓
           GeminiClient -> Google Gemini API
                 ↓
           Grounded Conversational Response
```

### Key RAG Tenets in N16:
1. **Gemini-First WordPress-Native:** No external vector databases (Pinecone, Weaviate, Qdrant) or external runtime servers required. Fully runs inside standard WordPress hosting.
2. **Zero-Token Static FAQs:** Clicking quick help FAQ items on Chatbot Home renders local text instantly with zero Gemini API calls.
3. **Strict Source Eligibility:** Only published, public, non-password-protected WordPress Pages, Posts, and active FAQs are indexed.
4. **WooCommerce Text-Only:** When WooCommerce is active, only textual titles and descriptions are indexed; live prices, stock, and cart actions are reserved for N17.
5. **Prompt-Injection Defense:** Website content is framed as untrusted reference data with explicit instructions commanding the model to treat it as passive factual data and ignore any embedded override instructions.

## 5. Business Integrations Framework & WooCommerce Read Integration (Nodes N17.1 & N17.2)

```
External Business Event or Intent
             ↓
    ActionExecutor
             ↓
    IntegrationRegistry
             ├── WooCommerceIntegration ('woocommerce')
             │     ├── SearchProductsAction ('woocommerce.search_products') [READ]
             │     ├── GetProductAction ('woocommerce.get_product') [READ]
             │     └── SearchByCategoryAction ('woocommerce.search_by_category') [READ]
             ↓
   Input Validation (ActionValidator)
     - string, int, number, bool, enum
     - Discards/rejects rogue arguments
             ↓
   Action Execution (ActionInterface)
     - Native WooCommerce functions (wc_get_products, wc_get_product)
     - Zero external HTTP APIs, zero credentials
             ↓
   ActionResult (Normalized Output via WooCommerceFormatter)
     - Safe arrays: id, name, url, sku, price, sale_price, stock_status, category
     - Zero stack traces, zero database errors
```

### Core Tenets of N17.1 & N17.2:
1. **Business Services Only:** Integrations connect WordPress services (WooCommerce, handoff, email, contact links), never AI model providers. Gemini remains the sole AI model provider.
2. **Deterministic Declarative Schemas:** Actions strictly define argument types and bounds. Rogue arguments are rejected before execution.
3. **Safe Exception Containment:** All action executions are trapped and normalized into `ActionResult` objects. No raw database, HTTP, or PHP exceptions are ever returned to callers.
4. **No Auto-Execution in N17.1/N17.2:** The framework establishes contracts and safe pipelines. Autonomous tool-calling loops by Gemini are not active.
5. **Read-Only Scope (N17.2):** Strictly read-only product and catalog queries. Cart, checkout, payment, and order modifications are strictly prohibited.
6. **Zero External APIs:** WooCommerce queries use native PHP functions (`wc_get_products()`, `wc_get_product()`) without outbound REST HTTP requests.

## 6. Human Handoff Framework (Node N17.3)

```
Visitor Message
       │
       ▼
Chat UI (POST /chat)
       │
       ▼
  ChatService
       │
       ├─► 1. Handoff Detection (detect_handoff_intent: explicit keywords & regex)
       │         │ (If handoff detected)
       │         ▼
       ├─► 2. HandoffService::create_handoff()
       │         │
       │         ├─► Lookup active conversation & lead (from N11 & N14)
       │         ├─► Validate reason (customer_request, unknown_answer, complex_question, sales_request, technical_issue)
       │         └─► Persist to wp_gca_handoffs table (default status: 'pending')
       │
       ▼
Admin Dashboard (Gemini Chat -> Handoffs)
       │
       └─► Inspect, update status (pending -> assigned -> resolved / cancelled), review transcript & contact details
```

### Core Tenets of N17.3:
1. **Safe Intent Detection:** ChatService checks inbound messages for explicit human assistance intent without autonomous AI execution loops.
2. **Controlled Statuses & Reasons:** Status transitions (`pending`, `assigned`, `resolved`, `cancelled`) and reason types (`customer_request`, `unknown_answer`, `complex_question`, `sales_request`, `technical_issue`) are strictly constrained.
3. **Lead Association:** Integrates directly with N14 leads; if the visitor has submitted contact details, the existing lead is linked rather than creating duplicates.
4. **Integration Framework Connection:** Provides `HandoffIntegration` (`handoff`) and `CreateHandoffAction` (`handoff.create`, `RISK_WRITE`) registered in `IntegrationRegistry`.
5. **Handoff Persistence:** Guaranteed before notification dispatch. Handoff remains valid if email transport fails.

## 7. Email Notifications Framework (Node N17.4)

```
Handoff Successfully Persisted in wp_gca_handoffs
       │
       ▼
NotificationService::send_handoff_notification()
       │
       ├─► 1. Check is_enabled() from gca_settings
       │       └─► Disabled: Return early (NOTIFICATIONS_DISABLED)
       │
       ├─► 2. Check has_been_sent() via transient (idempotency key)
       │       └─► Already sent: Return (ALREADY_NOTIFIED)
       │
       ├─► 3. Validate & sanitize recipients (max 10, is_email, strip CR/LF)
       │       └─► Fallback to get_option('admin_email') if none configured
       │
       ├─► 4. Build plain-text body with strict PII minimization
       │       └─► Excludes: session tokens, IP addresses, Gemini API keys, chat transcripts
       │       └─► Includes: Reason, Status, Lead Name/Email/Phone (if captured), Admin URLs
       │
       ├─► 5. Dispatch via WordPress native wp_mail()
       │       ├─► Sent (accepted by transport) -> mark_as_sent() transient
       │       └─► Failed -> normalize EMAIL_SEND_FAILED (handoff remains safe)
       │
       └─► 6. Action Integration: SendHandoffNotificationAction (handoff.send_notification, RISK_EXTERNAL)
```

### Core Tenets of N17.4:
1. **WordPress Native Transport:** Relies exclusively on `wp_mail()`. Zero direct PHPMailer calls, zero raw `mail()`, zero external third-party email APIs (SendGrid, Mailgun, Brevo, SES).
2. **Header Injection Defense:** Recipient addresses and subject lines are aggressively sanitized against CR (`\r`) and LF (`\n`) characters.
3. **Failure Isolation:** Any `wp_mail()` failure is caught and normalized internally. It never deletes the handoff, deletes the lead, breaks conversation flow, or leaks errors to the visitor.
4. **Delivery Semantics:** wp_mail() returning true confirms WordPress accepted the message for delivery; it is documented accurately as "Sent / Accepted for sending" rather than guaranteed inbox receipt.
5. **Idempotency Protection:** Uses a 7-day transient flag keyed by handoff public UUID (`gca_notif_sent_{md5}`) to prevent duplicate email alerts on retries.

## 8. Direct Contact Channels Architecture (Node N17.5)

```
Admin Settings (Gemini Chat -> Settings -> Contact Channels)
       │
       ├─► contact_channels_enabled (bool)
       ├─► contact_phone_enabled, contact_phone_number, contact_phone_label
       ├─► contact_email_enabled, contact_email_address, contact_email_label
       └─► contact_whatsapp_enabled, contact_whatsapp_number, contact_whatsapp_label, contact_whatsapp_message
       │
       ▼
Public Assets Localization (Assets::get_localized_config) & Template Rendering (chat-widget.php)
       │
       ├─► Phone:    <a href="tel:{sanitized_phone}">
       ├─► Email:    <a href="mailto:{sanitized_email}">
       └─► WhatsApp: <a href="https://wa.me/{clean_digits}?text={urlencoded_message}" target="_blank" rel="noopener noreferrer">
       │
       ▼
Visitor Client (Direct OS/Browser Deep Links)
       ├─► No server-side HTTP calls
       ├─► No Twilio / SMS APIs
       ├─► No WhatsApp Business / Meta Graph APIs
       ├─► No CRM webhooks
       └─► Zero AI token consumption
```

### Core Tenets of N17.5:
1. **Client-Side Deep Links:** Contact actions use standard URI schemes (`tel:`, `mailto:`, `https://wa.me/`). All connection handling occurs entirely on the user's client device via native dialers, email clients, or WhatsApp applications.
2. **Zero Third-Party APIs:** No Meta Graph API, WhatsApp Cloud API, Twilio, SendGrid, or external CRM webhooks are used.
3. **No Gemini Intermediation Required:** Visitors can access contact channels directly from the chat widget home screen without prompting or executing AI model calls.
4. **Sanitization & Boundary Safety:** Phone numbers maintain valid dial characters, WhatsApp numbers are strictly stripped to digits and country code, emails are validated via `sanitize_email()`, and labels/messages have bounded length limits (50 chars for labels, 300 chars for WhatsApp prefilled messages).

## 9. Multi-Provider Foundation Architecture (Node N18)

```
┌────────────────────────────────────────────────────────┐
│                   ProviderRegistry                     │
│  - gemini: GeminiProvider (Default)                    │
│  - openai: OpenAIProvider                              │
│  - claude: ClaudeProvider                              │
└──────────────────────────┬─────────────────────────────┘
                           │
       ┌───────────────────┴───────────────────┐
       ▼                                       ▼
┌──────────────────────────────┐ ┌──────────────────────────────┐
│       ProviderInterface      │ │   Credential Resolution      │
│ - get_id(): string           │ │ 1. Environment Variable      │
│ - get_name(): string         │ │ 2. wp-config.php Constant    │
│ - get_models(): array        │ │ 3. Database (AES-256 Encrypted)
│ - chat(messages, options)    │ └──────────────────────────────┘
│ - test_connection(): bool    │
└──────────────────────────────┘
```

- **Credential Precedence:** Environment variable (`OPENAI_API_KEY`, etc.) > wp-config Constant (`GCA_OPENAI_API_KEY`, etc.) > Database option (`gca_provider_credentials`, AES-256-CBC encrypted).
- **Client-Side Isolation:** API keys are never echoed back in HTML form inputs, REST responses, or public widgets.

## 10. Live OpenAI Responses API Integration (Node N19)

```
ChatService (handle_chat)
       │
       ├─► Check SettingsService::get_default_provider() === 'openai'
       │
       ▼
OpenAIProvider::chat( $messages, $options )
       │
       ├─► Validate enabled & API key configured
       ├─► Extract instructions (ProfileService system prompt + RAG website chunks)
       ├─► Normalize user & assistant turns to chronological input array
       │
       ▼
OpenAIClient::create_response( $model, $input, $options )
       │
       ├─► Endpoint: POST https://api.openai.com/v1/responses
       ├─► Headers:
       │     - Authorization: Bearer <API_KEY> (Server-Side Only)
       │     - Content-Type: application/json
       ├─► Payload:
       │     - model: "gpt-4o-mini" / "gpt-4o" / "gpt-4-turbo"
       │     - instructions: "<system prompt + RAG knowledge>"
       │     - input: [ { role: "user"|"assistant", content: "..." } ]
       │     - store: false (Explicitly disabled)
       │
       ▼
HTTP Transport: wp_remote_post()
       │
       ├─► Success (HTTP 200):
       │     - Locate output item where type == "message" && role == "assistant"
       │     - Aggregate all content items with type == "output_text"
       │     - Parse token usage: input_tokens, output_tokens, total_tokens
       │     - Capture request_id from x-request-id header or response id
       │     - Return ProviderResponse
       │
       └─► Error Handling:
             - 400 -> ProviderException::invalid_request
             - 401 -> ProviderException::authentication_error
             - 404 -> ProviderException::model_unavailable
             - 408 -> ProviderException::timeout
             - 429 -> ProviderException::rate_limited
             - 5xx -> ProviderException::server_error
             - WP_Error -> ProviderException::connection_failed
             - Malformed / missing output -> ProviderException::invalid_response
             - All exception messages sanitized via ProviderException::strip_credentials()
```

### Core Tenets of N19:
1. **Modern Responses API:** Employs OpenAI's `/v1/responses` API with `store: false` to avoid external chat state accumulation.
2. **Deterministic History & Prompt Layering:** Context memory messages are strictly formatted as alternating `user` and `assistant` items; system persona instructions and RAG retrieval snippets are isolated within `instructions`.
3. **Multi-Chunk Assembly:** Handles multi-part `output_text` chunks across the message content array.
4. **Minimal Token Test Connection:** Admin connection testing executes a 1-token query (`max_output_tokens: 1`) without running open-ended requests.
5. **Secret Redaction:** Strict regex scrubbing prevents API keys (`sk-*`, `AIza*`, `Bearer *`) from appearing in error traces or logs.
6. **Zero Claude Leaks & Zero Gemini Regressions:** Gemini chat remains completely operational and unaffected; Claude remains safely inert as a placeholder.

## 11. Live Anthropic Claude Messages API Integration (Node N20)

```
ChatService (handle_chat)
       │
       ├─► Check SettingsService::get_default_provider() === 'claude'
       │
       ▼
ClaudeProvider::chat( $messages, $options )
       │
       ├─► Validate enabled & API key configured (validate_configuration via AbstractProvider)
       ├─► Extract system instructions (ProfileService system prompt + RAG website chunks)
       ├─► Normalize user & assistant turns to chronological input array (normalize_input_messages)
       │
       ▼
ClaudeClient::create_response( $messages, $options )
       │
       ├─► Endpoint: POST https://api.anthropic.com/v1/messages
       ├─► Headers:
       │     - x-api-key: <ANTHROPIC_API_KEY> (Server-Side Only)
       │     - anthropic-version: 2023-06-01
       │     - Content-Type: application/json
       │     - Accept: application/json
       ├─► Payload:
       │     - model: "claude-3-5-haiku-20241022" / "claude-3-5-sonnet-20241022" / "claude-3-opus-20240229" / etc.
       │     - max_tokens: 1024 (Required by Anthropic)
       │     - system: "<system prompt + RAG knowledge>" (Top-level string, never inserted as a fake user turn)
       │     - messages: [ { role: "user"|"assistant", content: "..." } ]
       │
       ▼
HTTP Transport: wp_remote_post()
       │
       ├─► Success (HTTP 200):
       │     - Iterate over content[] array
       │     - Aggregate all blocks where type == "text"
       │     - Parse token usage: input_tokens, output_tokens, total_tokens = input + output
       │     - Normalize stop_reason: end_turn|stop_sequence -> "stop", max_tokens -> "length", tool_use -> "tool_calls"
       │     - Capture request_id from request-id / x-request-id header or body id
       │     - Return ProviderResponse
       │
       └─► Error Handling:
             - 400 -> ProviderException::invalid_request
             - 401 -> ProviderException::authentication_failed
             - 403 -> ProviderException::authentication_failed
             - 404 -> ProviderException::model_unavailable
             - 408 -> ProviderException::timeout
             - 413 -> ProviderException::invalid_request
             - 429 -> ProviderException::rate_limited
             - 500, 502, 503, 504, 529 -> ProviderException::provider_unavailable
             - WP_Error -> ProviderException::timeout / provider_unavailable
             - Malformed / missing text -> ProviderException::invalid_response
             - All exception messages sanitized via ProviderException::strip_credentials()
```

### Core Tenets of N20:
1. **Modern Messages API:** Implements Anthropic's `/v1/messages` endpoint with `anthropic-version: 2023-06-01`.
2. **Top-Level System Parameter:** Normalizes system instructions and RAG retrieval snippets strictly into Anthropic's top-level `system` property, never injecting them as fake user messages.
3. **Mandatory `max_tokens` Guard:** Always transmits a sensible, safe `max_tokens` constraint (default 1024).
4. **Multi-Block Text Parsing:** Robustly combines all `text` blocks in the response `content` array in their original order.
5. **Admin 1-Token Test Connection:** Administrators can verify Claude connectivity with minimal overhead (`max_tokens: 1`) via dedicated admin action and UI notices.
6. **Provider Ecosystem Status:**
   - Google Gemini: **LIVE** / operational
   - OpenAI: **LIVE** / operational
   - Anthropic Claude: **LIVE-capable** when configured

---

## 13. Provider & Model Selection System (Node N21)

### Architecture Overview:
Node N21 introduces dynamic provider and model selection while decoupling selection logic, validation, and metadata generation from REST controllers and chat services.

```
Visitor Request / Chat Widget (Screen 2)
       │
       ├─► Provider selector (.gca-ai-selector-provider) [Optional: allow_public_provider_selection]
       ├─► Model selector (.gca-ai-selector-model)       [Optional: allow_public_model_selection]
       │
       ▼
REST Controller (POST /chat)
       │
       ├─► Receives: session_id, message, provider, model
       │
       ▼
ChatService::handle_chat()
       │
       ▼
ProviderSelectionService::resolve_effective_selection( $requested_provider, $requested_model )
       │
       ├─► 1. Evaluate Provider:
       │      - If public provider selection allowed and provider requested -> validate & resolve
       │      - Else -> fallback to admin default provider (SettingsService::get_default_provider())
       │      - Enforce assert_provider_usable(): must be known, enabled, and have configured API key
       │
       ├─► 2. Evaluate Model:
       │      - If public model selection allowed and model requested:
       │          - Verify model belongs to the effective provider (reject cross-provider injection)
       │          - Verify model is supported in ModelRegistry
       │      - Else:
       │          - Fallback to admin configured default model for that provider (or ModelRegistry default)
       │
       └─► Return [ $effective_provider, $effective_model ]
       │
       ▼
Chat Execution Engine
       │
       ├─► Mid-Conversation Provider Switching:
       │      - Message history across all providers preserved in DB
       │      - If switching to Gemini from non-Gemini provider: clears stale interaction ID
       │
       ├─► Provider Dispatch:
       │      - Gemini: dispatch_gemini_turn( ..., $model_id )
       │      - Multi-turn (OpenAI/Claude): dispatch_multi_turn_provider( ..., $model_id )
       │
       └─► Message Persistence & Metadata:
              - Stores message with provider and model metadata in wp_gca_messages
              - Returns provider and model in REST response meta object
```

### Core Tenets of N21:
1. **Single Source of Truth:** `ModelRegistry` centralizes supported models, names, and default models across all providers. Provider adapters query `ModelRegistry::get_models_for_provider()`.
2. **Strict Server-Side Validation:** Requests specifying disabled, unconfigured, or mismatched provider-model pairs are rejected with `400 Bad Request` (`PROVIDER_NOT_AVAILABLE`, `INVALID_MODEL`).
3. **Safe Metadata Exposure:** `GET /wp-json/gca/v1/providers` and script localization expose only enabled, configured providers and their supported models. Zero API keys, credentials, or header details are leaked.
4. **Accessible Frontend Controls:** An accessible selector toolbar (`.gca-ai-selector-bar`) appears in Screen 2 when enabled. Changing provider dynamically cascades and updates the available model dropdown.
