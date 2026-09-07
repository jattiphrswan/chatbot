# Security Architecture & Policies: Gemini Chat Assistant

## 1. Threat Model & Mitigation Strategy

| Threat Category | Potential Attack Vector | Mitigation Control |
| :--- | :--- | :--- |
| **API Key Exposure** | Client inspection, JS leakage, response payloads | API keys are stored in `wp_options` encrypted with AES-256-GCM. Frontend receives zero knowledge of the key. |
| **Unauthorized Chat Usage** | Malicious bot spamming REST endpoints | Enforce WordPress REST Nonces (`X-WP-Nonce`), IP-based rate limiting, and session throttling via transients. |
| **Cross-Site Scripting (XSS)** | Injection of malicious JS in chat prompts/replies | Strict sanitization of user input (`sanitize_text_field`), DOMPurify / safe HTML escaping during frontend markdown rendering. |
| **SQL Injection** | Malformed parameters in chat or log queries | Complete usage of `$wpdb->prepare()` for all dynamic SQL queries. |
| **Privilege Escalation** | Unauthorized access to admin settings/health API | Enforce `current_user_can('manage_options')` checks in all administrative controllers and page handlers. |
| **Denial of Service (DoS)** | Giant message payloads exhausting server RAM | Strict payload limits (max 4000 characters per message) and timeout thresholds (15s for Gemini API calls). |

## 2. Secrets Management & Encryption
- **Encryption Algorithm:** `AES-256-GCM` using OpenSSL.
- **Key Derivation:** Derived from WordPress secret keys (`AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`).
- **Storage:** Stored in `wp_options` under option key `gca_api_key_encrypted`.
- **Masking:** When displayed in the WP Admin settings UI, the key is always masked (e.g., `AIzaSy************`).

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
