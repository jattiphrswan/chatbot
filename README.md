# Gemini Chat Assistant (`gemini-chat-assistant`)

> Enterprise-grade WordPress AI Chat Assistant powered by Google Gemini API.

## Overview
**Gemini Chat Assistant** is a WordPress plugin developed under the PHP namespace `SkyFish\GeminiChat`. It enables website administrators to deploy a responsive AI chat assistant to frontend visitors via a simple shortcode `[gemini_chat]`, while keeping the Google Gemini API key completely secure on the server side.

## Key Features
- **Unidirectional Secure Architecture:** Browser -> WordPress REST API (`gca/v1`) -> PHP Application Layer -> Google Gemini API.
- **Zero Client-Side Secret Exposure:** The Gemini API key is loaded server-side exclusively via environment variables (`GEMINI_API_KEY`) or `wp-config.php` (`GCA_GEMINI_API_KEY`) and is never stored in DB options or exposed to the frontend.
- **REST Endpoints:**
  - `POST /wp-json/gca/v1/chat`
  - `POST /wp-json/gca/v1/reset`
  - `GET /wp-json/gca/v1/health`
- **Shortcode:** `[gemini_chat]` (supports floating widget and inline embed modes).
- **Admin Dashboard:** Includes Settings (Model: `gemini-3.8-flash`, System Prompt, Rate Limits, Widget Toggles), Logs & Analytics, and Health Diagnostics.
- **Database Architecture:** Optimized custom tables `wp_gca_conversations`, `wp_gca_messages`, and `wp_gca_logs`.

## System Requirements
- PHP 8.0 or higher
- WordPress 6.2 or higher
- OpenSSL PHP Extension (for AES-256-GCM)
- cURL PHP Extension

## Documentation Suite
- [Architecture & Data Flow](ARCHITECTURE.md)
- [API Contract & Endpoints](API_CONTRACT.md)
- [Database Shapes & Schemas](DB_SHAPES.md)
- [Schema Inventory](SCHEMA_INVENTORY.md)
- [Component Guide](COMPONENTS.md)
- [Component Inventory](COMPONENT_INVENTORY.md)
- [Security Architecture](SECURITY.md)
- [Testing & QA Strategy](TESTING.md)
- [Project Roadmap](ROADMAP.md)
- [Master Build Prompt](MASTER_BUILD_PROMPT.md)
- [Editing Guide](EDITING_GUIDE.md)

## License
GPLv2 or later.
