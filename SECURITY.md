# Security Architecture & Policies: Gemini Chat Assistant

## 1. Threat Model & Mitigation Strategy

| Threat Category | Potential Attack Vector | Mitigation Control |
| :--- | :--- | :--- |
| **API Key Exposure** | Client inspection, JS leakage, response payloads, database breaches | API keys are NEVER stored in `gca_settings`, public options, or returned in HTML source. Stored in isolated `gca_provider_credentials` encrypted with AES-256-CBC or loaded from environment variables / constants. Inputs are never populated with stored secrets. Frontend receives zero knowledge of keys. |
| **Unauthorized Chat Usage** | Malicious bot spamming REST endpoints | Enforce WordPress REST Nonces (`X-WP-Nonce`), IP-based rate limiting, and session throttling via transients. |
| **Cross-Site Scripting (XSS)** | Injection of malicious JS in chat prompts/replies | Strict sanitization of user input (`sanitize_text_field`), DOMPurify / safe HTML escaping during frontend markdown rendering. |
| **SQL Injection** | Malformed parameters in chat or log queries | Complete usage of `$wpdb->prepare()` for all dynamic SQL queries. |
| **Privilege Escalation** | Unauthorized access to admin settings/health API | Enforce `current_user_can('manage_options')` checks in all administrative controllers and page handlers. |
| **Denial of Service (DoS)** | Giant message payloads exhausting server RAM | Strict payload limits (`max_message_length`) and rate limits (`rate_limit_5m`, `rate_limit_1h`). |

## 2. Server-Side Secrets Architecture & Multi-Provider Credentials (Node N18)
- **Precedence Hierarchy:**
  1. **Environment Variables (Highest Priority):** `GEMINI_API_KEY`, `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`.
  2. **Server Constants (Secondary):** `GCA_GEMINI_API_KEY`, `GCA_OPENAI_API_KEY`, `GCA_CLAUDE_API_KEY` defined in `wp-config.php`.
  3. **Encrypted Database Storage (Fallback):** Option `gca_provider_credentials` (`autoload = 'no'`) encrypted with AES-256-CBC and WordPress `AUTH_KEY` salt.
- **Form Submission Isolation:** Leaving API key fields blank during settings save NEVER overwrites or deletes existing credentials. Only non-empty strings trigger updates.
- **Admin UI Isolation:** Admin UI renders password fields with `value=""` (never echoing stored secrets back to the browser). UI displays status badges (Configured / Not Configured) and source descriptions without exposing characters.
- **Explicit Key Removal:** Deleting stored database credentials requires explicit administrative POST requests (`action=gca_remove_provider_key`) protected by unique WordPress nonces (`gca_remove_provider_key_{provider}`) and `manage_options` capability checks.
- **Exception Masking:** `ProviderException::strip_credentials()` sanitizes error messages using regex to mask OpenAI (`sk-...`), Anthropic (`sk-ant-...`), and Google (`AIza...`) keys before they can reach logs or error views.
- **Live Provider Outbound HTTP Calls (N19 & N20):** `OpenAIProvider` (Node N19) and `ClaudeProvider` (Node N20) make live outbound HTTP requests only when explicitly enabled and configured with API credentials. When unconfigured or disabled, zero outbound requests are made (throwing `ProviderException::not_configured`). All API keys remain server-side and are never exposed client-side or logged.


## 3. REST API Security Layer
- **Public Routes (`POST /wp-json/gca/v1/chat`, `POST /wp-json/gca/v1/reset`):**
  - Requires valid `X-WP-Nonce` header.
  - Validates session format `^[a-zA-Z0-9_-]{16,64}$`.
  - Applies rate limit check: max 10 requests / min per IP.
- **Admin Routes (`GET /wp-json/gca/v1/health`):**
  - Requires active user session with `manage_options` capability.
  - Requires valid REST Nonce.

## 4. Input & Output Discipline
- All inputs are unwrapped from slashes via `wp_unslash()` and sanitized.
- Responses use strict JSON encodings and explicit HTTP status codes.

## 5. AI Profile & Prompt Security
- **No Public Prompt Exposure:** The effective system prompt, profile instructions, and behavioral rules are strictly server-side data sent to GeminiClient. They are never sent to frontend JavaScript, REST responses, page HTML, or browser local storage.
- **Plain Text Storage:** System instructions and rules are treated as plain text strings (`sanitize_textarea_field`). They are never executed, `eval()`'d, or rendered as unescaped HTML.
- **Admin Capability:** Profile creation, editing, activation, duplication, and deletion strictly require `current_user_can('manage_options')` and unique action nonces.
- **Zero Credential Contamination:** Stored profiles and constructed prompts are audited to ensure they never include API keys, database credentials, or visitor PII.
- **Safe Options Quota:** Total profiles are capped at 25 and saved with `autoload = 'no'` to prevent database and cache bloat.

## 6. Knowledge Retrieval & RAG Security
- **Prompt Injection Containment:** Retrieved website chunks and FAQ entries are treated as **untrusted external data**. Injected context is strictly enclosed within custom isolation fences (`=== UNTRUSTED KNOWLEDGE BASE START ===` and `=== UNTRUSTED KNOWLEDGE BASE END ===`) with explicit instructions directing the model to treat the content as reference facts only and never follow directives, override rules, or reveal private instructions.
- **Shortcode Stripping Without Execution:** The knowledge indexer strips shortcodes using `strip_shortcodes()` without executing their callback handlers, preventing arbitrary code execution, recursive loops, or unwanted database side effects during indexing.
- **Content Sanitization:** HTML tags and WordPress block comments are stripped prior to storage and chunking. No raw HTML or unescaped user inputs are stored in knowledge chunks.
- **Strict Content Exclusions:** Drafts, private posts, password-protected pages, trash items, revisions, and auto-drafts are unconditionally excluded from indexing.
- **FAQ XSS Defense:** FAQ questions and answers are sanitized via `sanitize_text_field()` and `wp_kses_post()` during persistence. Public chat widget renders FAQ answers safely via DOM `textContent` (zero `innerHTML` injection).
- **Administrative Access:** All index manipulation operations (batch sync, index clear, test retrieval, FAQ CRUD) require `manage_options` capabilities and valid WordPress security nonces.

## 7. Business Integrations Security Policy (Node N17.1)
- **No Public Generic Action Execution:** The integration framework does NOT expose generic action execution endpoints to public visitors or REST clients. Actions can only be invoked by authorized internal services with explicit parameter schemas.
- **Strict Identifier Pattern Enforcement:** Integration slugs must match `/^[a-z0-9_-]{2,50}$/` and Action slugs must match `/^[a-z0-9_-]+\.[a-z0-9_-]+$/`. Path traversal sequences (`..`), script tags, spaces, or dynamic class/function calls are unconditionally rejected.
- **Rogue Argument Rejection:** Every action defines a strict, declarative input schema. Unknown arguments are rejected with an `INVALID_ACTION_ARGUMENTS` error code, preventing unexpected parameter tampering or injection.
- **Risk Classification & Policies:** Every action declares a risk category (`read`, `write`, `external`), laying the groundwork for granular permission policies and confirmation requirements in subsequent subnodes.
- **Robust Exception Containment:** `ActionExecutor` wraps all action executions in `try / catch (\Throwable)`. Exceptions return sanitized `ACTION_FAILED` results; raw PHP exception messages, stack traces, and database/server credentials are never exposed.
- **Zero External Credential Storage in N17.1:** No external API keys, tokens, or webhook secrets are stored in the database or exposed via options in N17.1.
- **No Multi-Provider AI Architecture:** Integrations represent business services only. Multi-provider AI abstractions remain strictly forbidden; Gemini remains the sole AI model provider.

## 8. WooCommerce Read-Only Integration Security Policy (Node N17.2)
- **Strict Read-Only Enforcement:** Only `RISK_READ` catalog query actions are provided (`woocommerce.search_products`, `woocommerce.get_product`, `woocommerce.search_by_category`). Operations that mutate WooCommerce state (cart additions, checkout, order generation, payment processing, customer modification) are strictly excluded from the codebase.
- **Zero Credentials & Native API Calls:** All product queries use WordPress-native PHP functions (`wc_get_products()`, `wc_get_product()`). No WooCommerce REST API consumer keys or secrets are required, stored, or exposed.
- **Publication Status Filtering:** Only products with `publish` status are returned. Drafts, private products, and trashed items are unconditionally excluded from lookups.
- **Query Bounds & Denial-of-Service Defense:** Search queries are limited to a maximum of 200 characters and category terms to 100 characters. Result sets are clamped to a hard ceiling of 10 items to prevent memory exhaustion and expensive database joins.
- **Sanitized Output Normalization:** Output data is filtered through `WooCommerceFormatter`, which strips all HTML tags, sanitizes text fields, formats prices, and produces clean JSON-serializable associative arrays with zero internal objects or sensitive properties exposed.

## 9. Human Handoff Security Policy (Node N17.3)
- **Controlled Status & Reason Validation:** Statuses are constrained strictly to `pending`, `assigned`, `resolved`, `cancelled`. Reason classifications are constrained to `customer_request`, `unknown_answer`, `complex_question`, `sales_request`, `technical_issue`. Rogue statuses and reasons are rejected at the service and repository layers.
- **Access Control & Capability Checks:** Viewing and updating handoffs in the WordPress admin panel strictly requires the `manage_options` capability. Unauthorized users (such as Subscribers or Authors) are immediately denied via `current_user_can('manage_options')` checks.
- **CSRF & Nonce Protection:** All status transition and deletion actions in the admin panel are protected by specific WordPress nonces (`gca_update_handoff_{id}`, `gca_delete_handoff_{id}`).
- **Zero Outbound Transports in N17.3:** No live notification dispatchers (`wp_mail`, SMS, WhatsApp, Webhooks) are invoked during handoff creation in N17.3, eliminating unauthorized communication risks until N17.4.
- **No Direct Visitor REST Execution:** No public `/handoff` REST endpoint exists. Handoffs can only be initiated through validated chat turn orchestration in `ChatService` or internal business actions.

## 10. Email Notifications Security Policy (Node N17.4)
- **Native wp_mail() Transport Only:** Dispatches rely solely on WordPress core `wp_mail()`. Zero direct socket operations, zero direct PHPMailer calls, and zero external third-party email API credentials (SendGrid, Mailgun, Brevo, SES) stored or exposed.
- **Header Injection & CRLF Defense:** Recipient addresses and subject lines are aggressively sanitized against carriage return (`\r`), line feed (`\n`), and control characters (`\x00-\x1F\x7F`). Multiline attacker injections are completely neutralized.
- **Recipient Bounds & Validation:** Recipient lists are clamped to a hard ceiling of 10 email addresses. Every address is individually verified with `sanitize_email()` and `is_email()`. Invalid addresses are stripped.
- **PII & Credential Minimization:** Email notifications contain only operational data needed for responding (reason, status, lead contact details if captured, and admin links). Session hashes, visitor IP addresses, Gemini API keys, model parameters, and raw conversation transcripts are unconditionally excluded.
- **Idempotency Protection:** Prevents duplicate notification spam through 7-day transient markers keyed by handoff public UUID (`gca_notif_sent_{md5}`). Repeated requests for the same handoff return `ALREADY_NOTIFIED` without triggering redundant emails.
- **Strict Error Isolation:** If `wp_mail()` returns false or fails, the failure is normalized internally (`EMAIL_SEND_FAILED`). The handoff remains valid, lead data remains intact, and no error message is exposed to the visitor.
- **Zero Visitor Autoresponders:** Notifications are strictly sent to internal team members. Visitors are never emailed automatically in N17.4.

## 11. Direct Contact Channels Security Policy (Node N17.5)
- **Zero External API Storage or Transmission:** No third-party API credentials, webhook endpoints, or access tokens (Meta Graph API, WhatsApp Business API, Twilio, SendGrid) are accepted, stored, or transmitted by the server.
- **Strict Input Sanitization & URL Scheme Control:**
  - Phone numbers are sanitized to strip all characters except digits, spaces, hyphens, dots, parentheses, and leading plus (`/[^0-9+\-().\s]/`).
  - WhatsApp numbers are sanitized to strict digits and leading plus (`/[^0-9+]/`).
  - Contact email addresses are validated using `sanitize_email()` and `is_email()`.
  - All output URLs are restricted to verified schemes (`tel:`, `mailto:`, and `https://wa.me/`). Rogue schemes (`javascript:`, `data:`, `file:`) are strictly forbidden.
- **Boundary & Length Defense:**
  - Channel labels are sanitized via `sanitize_text_field()` and clamped to 50 characters.
  - WhatsApp prefilled message templates are sanitized and clamped to 300 characters.
- **XSS Defense & Attribute Escaping:**
  - All attributes rendered in widget HTML (`href`, `aria-label`, button text) are strictly escaped via `esc_url()`, `esc_attr()`, and `esc_html()`.
  - External links (`https://wa.me/`) explicitly include `target="_blank"` with `rel="noopener noreferrer"` to prevent reverse tab-nabbing vulnerabilities.
- **Zero Automated Outbound Calling or Messaging:** The server never makes calls, sends SMS, or sends WhatsApp messages on behalf of the visitor or administrator.
- **Gemini Execution Independence:** Contact channel buttons are client-side navigation elements that execute without invoking Gemini API prompts, preventing unauthorized prompt injection or token depletion.

## 12. Live OpenAI Integration Security Policy (Node N19)
- **Server-Side Bearer Authentication:** OpenAI credentials (`sk-...`) are stored securely and transmitted strictly via HTTP `Authorization: Bearer <API_KEY>` headers to `https://api.openai.com/v1/responses`. Credentials are NEVER included in URL query strings or JSON request bodies.
- **Client-Side Secret Isolation:** Public chat visitors and client JavaScript never receive or observe OpenAI credentials. Neither REST `/chat` responses, widget markup, nor localized scripts contain OpenAI API keys.
- **Strict Exception & Log Redaction:** `ProviderException::strip_credentials()` automatically sanitizes exception messages using regex before errors can surface in administration UI, server logs, or debug traces. Patterns masked include:
  - OpenAI keys: `/sk-[a-zA-Z0-9_\-]{20,}/`
  - Anthropic keys: `/sk-ant-[a-zA-Z0-9_\-]{20,}/`
  - Google Gemini keys: `/AIza[0-9A-Za-z\-_]{35}/`
  - Authorization headers: `/Bearer\s+[a-zA-Z0-9_\-\.]{20,}/`
- **Zero State Accumulation (`store: false`):** All chat requests dispatched via `OpenAIClient` specify `"store": false` by default, ensuring visitor prompts and messages are not stored in OpenAI-hosted conversation threads.
- **CSRF & Capability Protection for Admin Test Connection:** The administrative "Test OpenAI Connection" action (`gca_test_openai_connection`) strictly requires `current_user_can('manage_options')` and validates a WordPress security nonce (`gca_test_openai_connection`).
- **Minimal 1-Token Test Query:** Admin connection testing enforces `max_output_tokens: 1` with a minimal prompt ("Ping"), preventing unnecessary token consumption or quota drain during verification.
- **Rate Limit Enforcement:** Dual-tier rate limiting (transient IP & session ceilings) applies uniformly before any OpenAI or Gemini API dispatch occurs.
- **Provider Boundary Isolation:** Live network communication in N19 is strictly constrained to `OpenAIProvider`. Anthropic Claude remains safely inert as a placeholder adapter throwing `ProviderException::not_configured` with zero outbound network activity.
