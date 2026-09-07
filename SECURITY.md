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
