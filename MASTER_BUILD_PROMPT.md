# Master Build Prompt: Gemini Chat Assistant

## Objective
Build a robust, secure, and production-ready WordPress plugin named **`gemini-chat-assistant`** under PHP namespace **`SkyFish\GeminiChat`** and REST namespace **`gca/v1`**, providing an interactive chat interface via the shortcode **`[gemini_chat]`**.

## Non-Negotiable Architectural Rules
1. **Request Flow:**
   `Browser` → `WordPress REST API (/wp-json/gca/v1/)` → `PHP Application/Service Layer (SkyFish\GeminiChat)` → `Google Gemini API`.
2. **API Key Protection:**
   The Google Gemini API key is loaded server-side exclusively via environment variables (`GEMINI_API_KEY`) or `wp-config.php` (`GCA_GEMINI_API_KEY`). It is **NEVER** stored in `gca_settings`, database tables, or exposed to the client browser.
3. **Endpoints Required:**
   - `POST /wp-json/gca/v1/chat`
   - `POST /wp-json/gca/v1/reset`
   - `GET /wp-json/gca/v1/health`
4. **Database Shapes:**
   - `{$wpdb->prefix}gca_conversations`
   - `{$wpdb->prefix}gca_messages`
   - `{$wpdb->prefix}gca_logs`
5. **Dashboard Modules:**
   - Configuration / Settings (Model: `gemini-3.7-flash`, System Prompt, Rate Limits, Widget Toggles)
   - Logs & Conversation History
   - Health Check & API Status Diagnostics
6. **Execution Protocol:**
   Strict node-by-node execution (N0 to N10) with zero application code in N0.
