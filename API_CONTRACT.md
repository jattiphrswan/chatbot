# API Contract: Gemini Chat Assistant

Chat requests optionally carry X-GCA-Request-ID (gca_ plus 16?64 lowercase hex/hyphen characters); invalid values are replaced by a server-generated ID. This identifier is correlation metadata, not authorization. Browser request headers retain it even if a proxy returns 504. The same ID travels through REST, ChatService, generation diagnostics, and safe application errors. No automatic resend. Application status is distinguished from the browser/proxy HTTP status in admin diagnostics.

Upstream chat failures: GEMINI_TIMEOUT=504; GEMINI_AUTH_FAILED=502 (upstream credential failure, not visitor authentication); GEMINI_RATE_LIMITED=429; GEMINI_MODEL_UNAVAILABLE=503; GEMINI_UNAVAILABLE=503. Local visitor rate limiting remains RATE_LIMITED/429. Public text is safe and excludes Google details. Existing admin Test Connection persists staged diagnostics through the WordPress runtime.

## 1. Overview
- **REST Namespace:** `gca/v1`
- **Base URL:** `/wp-json/gca/v1`
- **Authentication / Authorization:**
  - Public endpoints (`/chat`, `/reset`) require a valid WordPress REST Nonce (`X-WP-Nonce`) generated via `wp_create_nonce('wp_rest')`.
  - Admin endpoints (`/health`) require `current_user_can('manage_options')` and a valid REST nonce.
- **Content Type:** `application/json; charset=UTF-8`

---

## 2. Endpoint Specifications

### 2.1 Send Message (Chat)
Processes a user prompt, retrieves session history, queries the Gemini API, persists conversation turns, and returns the assistant response.

- **Route:** `POST /wp-json/gca/v1/chat`
- **Permission Callback:** Public / Nonce-verified (`X-WP-Nonce`)
- **Rate Limit:** 10 requests / minute per IP (configurable)

#### Request Headers
```http
Content-Type: application/json
X-WP-Nonce: <wp_rest_nonce_string>
```

#### Request Payload
```json
{
  "session_id": "gca_sess_9a8b7c6d5e4f3g2h1",
  "message": "Hello! How does this plugin work?",
  "provider": "openai",
  "model": "gpt-4o-mini",
  "context": {
    "page_id": 42,
    "page_title": "Contact Us",
    "page_url": "https://example.com/contact"
  }
}
```

#### Payload Schema Validation
- `session_id` (string, required): Format `^[a-zA-Z0-9_-]{16,64}$`.
- `message` (string, required): 1 to 4000 characters. Trimmed and sanitized.
- `provider` (string, optional): Target AI provider identifier (`gemini`, `openai`, `claude`). Honored when `allow_public_provider_selection` is enabled.
- `model` (string, optional): Target AI model identifier valid for the resolved provider. Honored when `allow_public_model_selection` is enabled.
- `context` (object, optional):
  - `page_id` (integer, optional)
  - `page_title` (string, optional, max 255 chars)
  - `page_url` (string, optional, valid URL)

#### Response Payload (200 OK)
```json
{
  "success": true,
  "data": {
    "message": "Hello! How can I help you today?",
    "conversation_id": "550e8400-e29b-41d4-a716-446655440000",
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "meta": {
      "provider": "openai",
      "model": "gpt-4o-mini"
    }
  }
}
```

#### Error Response (400 Bad Request / 403 Forbidden / 429 Too Many Requests / 500-504 Upstream Errors)
```json
{
  "success": false,
  "error": {
    "code": "INVALID_INPUT",
    "message": "Please enter a valid message.",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

---

### 2.2 Reset Session
Clears active conversation state for the specified session identifier.

- **Route:** `POST /wp-json/gca/v1/reset`
- **Permission Callback:** Public / Nonce-verified (`X-WP-Nonce`)

#### Request Payload
```json
{
  "session_id": "gca_sess_9a8b7c6d5e4f3g2h1"
}
```

#### Response Payload (200 OK)
```json
{
  "success": true,
  "data": {
    "session_id": "gca_sess_9a8b7c6d5e4f3g2h1",
    "status": "reset_successful",
    "timestamp": "2026-09-07T11:31:00Z"
  }
}
```

---

### 2.3 Health & Diagnostics Check
Performs end-to-end self-tests including database integrity, option configuration, and Google Gemini API connectivity.

- **Route:** `GET /wp-json/gca/v1/health`
- **Permission Callback:** `current_user_can('manage_options')`

#### Response Payload (200 OK)
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "plugin_version": "1.0.0",
    "configured": true,
    "model": "gemini-3.8-flash",
    "database_version": "1.0.0"
  }
}
```

---

### 2.4 Public AI Providers & Models Metadata
Returns public configuration and supported models for enabled and configured AI providers.

- **Route:** `GET /wp-json/gca/v1/providers`
- **Permission Callback:** Public / Nonce-verified (`X-WP-Nonce`)

#### Response Payload (200 OK)
```json
{
  "success": true,
  "data": {
    "default_provider": "gemini",
    "allow_public_provider_selection": true,
    "allow_public_model_selection": true,
    "providers": [
      {
        "id": "gemini",
        "name": "Google Gemini",
        "default_model": "gemini-2.5-flash",
        "models": [
          { "id": "gemini-2.5-flash", "name": "Gemini 2.5 Flash" },
          { "id": "gemini-2.5-pro", "name": "Gemini 2.5 Pro" },
          { "id": "gemini-1.5-flash", "name": "Gemini 1.5 Flash" }
        ]
      },
      {
        "id": "openai",
        "name": "OpenAI",
        "default_model": "gpt-4o-mini",
        "models": [
          { "id": "gpt-4o-mini", "name": "GPT-4o Mini" },
          { "id": "gpt-4o", "name": "GPT-4o" },
          { "id": "gpt-4.1-mini", "name": "GPT-4.1 Mini" }
        ]
      }
    ]
  }
}
```
