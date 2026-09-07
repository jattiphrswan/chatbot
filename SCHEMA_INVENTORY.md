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
      "required": ["message", "conversation_id", "request_id", "meta"],
      "properties": {
        "message": { "type": "string" },
        "conversation_id": { "type": "string" },
        "request_id": { "type": "string" },
        "meta": {
          "type": "object",
          "required": ["model"],
          "properties": {
            "model": { "type": "string" }
          }
        }
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
  "required": ["model", "assistant_name", "max_message_length", "rate_limit_5m", "rate_limit_1h"],
  "properties": {
    "enabled": { "type": "boolean", "default": true },
    "assistant_name": { "type": "string", "default": "AI Assistant" },
    "greeting": { "type": "string", "default": "Welcome!" },
    "welcome_message": { "type": "string", "default": "Hi! How can I help you today?" },
    "placeholder": { "type": "string", "default": "Type your message..." },
    "model": { "type": "string", "default": "gemini-3.8-flash" },
    "system_instruction": { "type": "string", "default": "You are a helpful customer support assistant for this website." },
    "widget_enabled": { "type": "boolean", "default": true },
    "embedded_chat_enabled": { "type": "boolean", "default": true },
    "desktop_enabled": { "type": "boolean", "default": true },
    "mobile_enabled": { "type": "boolean", "default": true },
    "prechat_enabled": { "type": "boolean", "default": false },
    "collect_name": { "type": "boolean", "default": false },
    "require_name": { "type": "boolean", "default": false },
    "collect_email": { "type": "boolean", "default": false },
    "require_email": { "type": "boolean", "default": false },
    "collect_phone": { "type": "boolean", "default": false },
    "require_phone": { "type": "boolean", "default": false },
    "collect_requirement": { "type": "boolean", "default": false },
    "require_requirement": { "type": "boolean", "default": false },
    "faq_enabled": { "type": "boolean", "default": false },
    "faq_show_home": { "type": "boolean", "default": false },
    "guest_access": { "type": "boolean", "default": true },
    "max_message_length": { "type": "integer", "default": 2000, "minimum": 100, "maximum": 10000 },
    "rate_limit_5m": { "type": "integer", "default": 15, "minimum": 1, "maximum": 500 },
    "rate_limit_1h": { "type": "integer", "default": 100, "minimum": 5, "maximum": 5000 },
    "store_messages": { "type": "boolean", "default": true },
    "store_leads": { "type": "boolean", "default": true },
    "retention_days": { "type": "integer", "default": 30, "minimum": 1, "maximum": 365 },
    "active_profile_id": { "type": "string", "default": "" }
  }
}
```

### 2.2 `gca_ai_profiles` Options Schema

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "AIProfilesOption",
  "type": "object",
  "maxProperties": 25,
  "additionalProperties": {
    "type": "object",
    "required": ["id", "name", "tone", "system_prompt", "response_style", "enabled", "created_at", "updated_at"],
    "properties": {
      "id": { "type": "string" },
      "name": { "type": "string", "maxLength": 100 },
      "description": { "type": "string", "maxLength": 1000 },
      "role": { "type": "string", "maxLength": 2000 },
      "tone": { "type": "string", "enum": ["professional", "friendly", "concise", "helpful", "conversational", "formal"] },
      "system_prompt": { "type": "string", "maxLength": 15000 },
      "rules": { "type": "string", "maxLength": 10000 },
      "response_style": { "type": "string", "enum": ["concise", "balanced", "detailed"] },
      "fallback_message": { "type": "string", "maxLength": 2000 },
      "enabled": { "type": "boolean" },
      "created_at": { "type": "string", "format": "date-time" },
      "updated_at": { "type": "string", "format": "date-time" }
    }
  }
}
```

---

## 3. Node N16 Database Tables (Schema Version 1.2.0)

### 3.1 `{$wpdb->prefix}gca_faqs` Schema
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "GcaFaqRecord",
  "type": "object",
  "required": ["id", "public_id", "question", "answer", "is_active", "show_on_home", "sort_order", "created_at", "updated_at"],
  "properties": {
    "id": { "type": "integer" },
    "public_id": { "type": "string", "pattern": "^faq_[a-f0-9]{32}$" },
    "question": { "type": "string", "maxLength": 300 },
    "answer": { "type": "string", "maxLength": 5000 },
    "category": { "type": "string", "maxLength": 100 },
    "sort_order": { "type": "integer" },
    "is_active": { "type": "integer", "enum": [0, 1] },
    "show_on_home": { "type": "integer", "enum": [0, 1] },
    "created_at": { "type": "string", "format": "date-time" },
    "updated_at": { "type": "string", "format": "date-time" }
  }
}
```

### 3.2 `{$wpdb->prefix}gca_knowledge_sources` Schema
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "GcaKnowledgeSourceRecord",
  "type": "object",
  "required": ["id", "source_type", "source_id", "title", "url", "content_hash", "chunk_count", "status", "indexed_at"],
  "properties": {
    "id": { "type": "integer" },
    "source_type": { "type": "string", "enum": ["post", "page", "product", "faq"] },
    "source_id": { "type": "integer" },
    "title": { "type": "string", "maxLength": 255 },
    "url": { "type": "string", "maxLength": 500 },
    "content_hash": { "type": "string", "maxLength": 64 },
    "chunk_count": { "type": "integer" },
    "status": { "type": "string", "enum": ["indexed", "pending", "failed"] },
    "indexed_at": { "type": "string", "format": "date-time" },
    "created_at": { "type": "string", "format": "date-time" },
    "updated_at": { "type": "string", "format": "date-time" }
  }
}
```

### 3.3 `{$wpdb->prefix}gca_knowledge_chunks` Schema
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "GcaKnowledgeChunkRecord",
  "type": "object",
  "required": ["id", "source_id", "chunk_index", "content", "token_estimate", "created_at"],
  "properties": {
    "id": { "type": "integer" },
    "source_id": { "type": "integer" },
    "chunk_index": { "type": "integer" },
    "content": { "type": "string" },
    "token_estimate": { "type": "integer" },
    "created_at": { "type": "string", "format": "date-time" }
  }
}
```

---

## 4. Gemini Client Internal Normalized Response Schema

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "GeminiClientNormalizedResult",
  "type": "object",
  "required": ["interaction_id", "text", "model", "status", "usage"],
  "properties": {
    "interaction_id": { "type": "string" },
    "text": { "type": "string" },
    "model": { "type": "string" },
    "status": {
      "type": "string",
      "enum": ["completed", "failed", "cancelled", "incomplete", "in_progress", "requires_action"]
    },
    "usage": {
      "type": "object",
      "required": ["input_tokens", "output_tokens", "thought_tokens", "total_tokens"],
      "properties": {
        "input_tokens": { "type": "integer" },
        "output_tokens": { "type": "integer" },
        "thought_tokens": { "type": "integer" },
        "total_tokens": { "type": "integer" },
        "cached_tokens": { "type": ["integer", "null"] },
        "tool_use_tokens": { "type": ["integer", "null"] }
      }
    }
  }
}
```

---

## 5. Integration Framework ActionResult Schema (Node N17.1)

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "ActionResult",
  "type": "object",
  "required": ["success", "data", "message", "error"],
  "properties": {
    "success": { "type": "boolean" },
    "data": {},
    "message": { "type": ["string", "null"] },
    "error": {
      "type": ["object", "null"],
      "properties": {
        "code": { "type": "string" },
        "message": { "type": "string" }
      },
      "required": ["code", "message"]
    }
  },
  "additionalProperties": false
}
```
