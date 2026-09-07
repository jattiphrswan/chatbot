# Project Overview: Gemini Chat Assistant

## 1. Executive Summary
**Gemini Chat Assistant** (`gemini-chat-assistant`) is an enterprise-grade WordPress plugin that integrates Google Gemini Large Language Models into any WordPress site. It provides an intuitive, real-time AI assistant for site visitors via a frontend shortcode/widget, alongside a comprehensive administrative dashboard for API configuration, analytics, logging, and health diagnostics.

## 2. Core Identifiers & Standards
- **Plugin Name:** `gemini-chat-assistant`
- **Plugin Slug:** `gemini-chat-assistant`
- **Main Plugin File:** `gemini-chat-assistant.php`
- **PHP Namespace:** `SkyFish\GeminiChat`
- **REST API Namespace:** `gca/v1`
- **Public Shortcode:** `[gemini_chat]`
- **Required Minimum PHP Version:** PHP 8.0+
- **Required Minimum WordPress Version:** WordPress 6.2+

## 3. Core Architectural Paradigm
```
┌─────────────────────────────────────────────────────────────┐
│                       End-User Browser                      │
│      (Frontend Chat Widget / Admin Health & Test UI)         │
└──────────────────────────────┬──────────────────────────────┘
                               │  HTTPS POST / GET (JSON)
                               │  X-WP-Nonce / Session Token
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                 WordPress REST API Layer                    │
│                     (Namespace: gca/v1)                     │
│  - POST /wp-json/gca/v1/chat                                │
│  - POST /wp-json/gca/v1/reset                               │
│  - GET  /wp-json/gca/v1/health                              │
└──────────────────────────────┬──────────────────────────────┘
                               │  Validated DTO / Request
                               ▼
┌─────────────────────────────────────────────────────────────┐
│             PHP Application & Service Layer                 │
│               (SkyFish\GeminiChat Namespace)                │
│  - Security: Rate Limiting, Nonce Verification, Encryption  │
│  - Database: Conversations, Messages, Logs Repository       │
│  - Services: Context Manager, Prompt Builder, Token Counter │
│  - Gemini Client: Server-Side cURL / wp_remote_post         │
└──────────────────────────────┬──────────────────────────────┘
                               │  Server-to-Server HTTPS
                               │  Encrypted API Key via Header/Query
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                      Google Gemini API                      │
│            (generativelanguage.googleapis.com)              │
└─────────────────────────────────────────────────────────────┘
```

> **CRITICAL SECURITY GUARANTEE:**
> The Google Gemini API key is stored server-side with AES-256-GCM encryption in WordPress options and is **NEVER** exposed to the client browser or outputted in frontend markup.

## 4. Key Capabilities & Features
1. **Interactive Frontend Chat Widget:**
   - Embeddable on any page/post using `[gemini_chat]`.
   - Floating chat bubble or static container layouts.
   - Markdown rendering, code snippet highlighting, streaming/typing indicator.
   - Session isolation and conversation context preservation.
2. **Robust Server-Side REST Endpoints:**
   - `POST /wp-json/gca/v1/chat`: Handles user queries, retrieves conversation history, communicates with Gemini API, stores interaction, and returns response.
   - `POST /wp-json/gca/v1/reset`: Clears user session history and starts a fresh conversation.
   - `GET /wp-json/gca/v1/health`: Verifies database health, API key validity, and external Gemini API connectivity.
3. **Comprehensive Admin Dashboard:**
   - **Settings Module:** Model selection (`gemini-1.5-flash`, `gemini-1.5-pro`, `gemini-2.0-flash`), API key configuration, system prompt customization, temperature, top_p, and token constraints.
   - **Logs & Analytics Module:** Real-time logging of chat traffic, error rates, token consumption, and active user sessions.
   - **Health & Diagnostics Module:** Self-test tool for API connectivity, database status, and REST route verification.
4. **Resilient Data Architecture:**
   - Custom optimized database tables for conversations, messages, and audit logs.
   - Automated migration engine using WordPress `dbDelta`.
