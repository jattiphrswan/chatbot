# Security Architecture & Policies: Gemini Chat Assistant

## 1. Threat Model & Mitigation Strategy

| Threat Category | Potential Attack Vector | Mitigation Control |
| :--- | :--- | :--- |
| **API Key Exposure** | Client inspection, JS leakage, response payloads, database breaches | API key is NEVER stored in `gca_settings`, database tables, or options. Loaded strictly server-side from `GEMINI_API_KEY` or `wp-config.php` (`GCA_GEMINI_API_KEY`). Frontend receives zero knowledge of the key. |
| **Unauthorized Chat Usage** | Malicious bot spamming REST endpoints | Enforce WordPress REST Nonces (`X-WP-Nonce`), IP-based rate limiting, and session throttling via transients. |
| **Cross-Site Scripting (XSS)** | Injection of malicious JS in chat prompts/replies | Strict sanitization of user input (`sanitize_text_field`), DOMPurify / safe HTML escaping during frontend markdown rendering. |
| **SQL Injection** | Malformed parameters in chat or log queries | Complete usage of `$wpdb->prepare()` for all dynamic SQL queries. |
| **Privilege Escalation** | Unauthorized access to admin settings/health API | Enforce `current_user_can('manage_options')` checks in all administrative controllers and page handlers. |
| **Denial of Service (DoS)** | Giant message payloads exhausting server RAM | Strict payload limits (`max_message_length`) and rate limits (`rate_limit_5m`, `rate_limit_1h`). |

## 2. Server-Side Secrets Architecture
- **Environment Variable (Preferred):** `GEMINI_API_KEY` read via `getenv()`, `$_ENV`, or `$_SERVER`.
- **Server Constant (Fallback):** `GCA_GEMINI_API_KEY` defined in `wp-config.php`.
- **Database Isolation:** Zero credentials stored in `wp_options` or custom tables.
- **Admin UI Isolation:** Admin UI only reports status as **Configured** or **Not Configured**. Never outputs complete, partial, or masked keys.


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
