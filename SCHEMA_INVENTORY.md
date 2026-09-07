# Schema Inventory: Gemini Chat Assistant

## 1. REST API Schemas

### 1.1 `POST /wp-json/gca/v1/chat` Request Schema
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "ChatRequest",
  "type": "object",
  "required": ["session_id", "message"],
  "properties": {
    "session_id": {
      "type": "string",
      "pattern": "^[a-zA-Z0-9_-]{16,64}$",
      "description": "Unique client session identifier."
    },
    "message": {
      "type": "string",
      "minLength": 1,
      "maxLength": 4000,
      "description": "User input prompt text."
    },
    "context": {
      "type": "object",
      "properties": {
        "page_id": { "type": "integer" },
        "page_title": { "type": "string", "maxLength": 255 },
        "page_url": { "type": "string", "format": "uri" }
      },
      "additionalProperties": false
    }
  },
  "additionalProperties": false
}
```

### 1.2 `POST /wp-json/gca/v1/chat` Response Schema
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "ChatResponse",
  "type": "object",
  "required": ["success", "data"],
  "properties": {
    "success": { "type": "boolean" },
    "data": {
      "type": "object",
      "required": ["session_id", "message_id", "reply", "finish_reason", "tokens_used", "created_at"],
      "properties": {
        "session_id": { "type": "string" },
        "message_id": { "type": "integer" },
        "reply": { "type": "string" },
        "finish_reason": { "type": "string" },
        "tokens_used": {
          "type": "object",
          "required": ["prompt_tokens", "completion_tokens", "total_tokens"],
          "properties": {
            "prompt_tokens": { "type": "integer" },
            "completion_tokens": { "type": "integer" },
            "total_tokens": { "type": "integer" }
          }
        },
        "created_at": { "type": "string", "format": "date-time" }
      }
    }
  }
}
```

### 1.3 `POST /wp-json/gca/v1/reset` Request & Response Schema
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "ResetRequest",
  "type": "object",
  "required": ["session_id"],
  "properties": {
    "session_id": {
      "type": "string",
      "pattern": "^[a-zA-Z0-9_-]{16,64}$"
    }
  },
  "additionalProperties": false
}
```

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "ResetResponse",
  "type": "object",
  "required": ["success", "data"],
  "properties": {
    "success": { "type": "boolean" },
    "data": {
      "type": "object",
      "required": ["session_id", "status", "timestamp"],
      "properties": {
        "session_id": { "type": "string" },
        "status": { "type": "string" },
        "timestamp": { "type": "string", "format": "date-time" }
      }
    }
  }
}
```

---

## 2. Settings Storage Schema (`gca_settings`)

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "PluginSettings",
  "type": "object",
  "required": ["model", "temperature", "max_tokens", "rate_limit"],
  "properties": {
    "model": {
      "type": "string",
      "enum": ["gemini-1.5-flash", "gemini-1.5-pro", "gemini-2.0-flash"],
      "default": "gemini-1.5-flash"
    },
    "system_instruction": {
      "type": "string",
      "maxLength": 10000,
      "default": "You are a helpful customer support assistant for this website."
    },
    "temperature": {
      "type": "number",
      "minimum": 0.0,
      "maximum": 2.0,
      "default": 0.7
    },
    "top_p": {
      "type": "number",
      "minimum": 0.0,
      "maximum": 1.0,
      "default": 0.95
    },
    "max_tokens": {
      "type": "integer",
      "minimum": 64,
      "maximum": 8192,
      "default": 1024
    },
    "rate_limit": {
      "type": "object",
      "properties": {
        "requests_per_minute": { "type": "integer", "default": 10 },
        "daily_ip_cap": { "type": "integer", "default": 100 }
      }
    },
    "ui_theme": {
      "type": "object",
      "properties": {
        "primary_color": { "type": "string", "default": "#1a73e8" },
        "position": { "type": "string", "enum": ["bottom-right", "bottom-left", "inline"], "default": "bottom-right" },
        "bot_title": { "type": "string", "default": "AI Assistant" },
        "welcome_message": { "type": "string", "default": "Hi! How can I help you today?" }
      }
    }
  }
}
```
