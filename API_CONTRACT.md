# API Contract: Gemini Chat Assistant

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
      "model": "gemini-3.8-flash"
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
