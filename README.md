# Gemini Chat Assistant (`gemini-chat-assistant`)

> Enterprise-grade WordPress Multi-AI Chat Assistant powered by Google Gemini, OpenAI, and Anthropic Claude.

## Overview
**Gemini Chat Assistant** is a production-grade WordPress plugin developed under the PHP namespace `SkyFish\GeminiChat`. It enables website administrators to deploy a responsive, customizable AI chat assistant to frontend visitors via a shortcode `[gemini_chat]` or automatic floating widget. The plugin supports Google Gemini, OpenAI, and Anthropic Claude through a unified architecture while keeping all API keys and credentials completely secure on the server side.

## Key Capabilities
- **Multi-AI Provider Architecture:** Native integration with Google Gemini, OpenAI (Responses API), and Anthropic Claude (Messages API).
- **Dynamic Provider & Model Selection:** Centralized model registry allowing administrators and optional website visitors to select AI providers and models.
- **AI Profiles & Persona Engine:** Configurable AI personas with customizable roles, tones, response styles, and behavioral constraints.
- **Website Knowledge & FAQ Grounding (RAG):** Context retrieval system grounding AI responses with untrusted prompt-injection defense fences.
- **Business Integrations & Human Handoff:** Human escalation detection, lead capture, WooCommerce product search actions, and native `wp_mail()` notifications.
- **Zero Client-Side Secret Exposure:** API credentials are NEVER stored in public options or rendered in HTML/JavaScript. Credentials reside in isolated AES-256 encrypted storage, server environment variables, or `wp-config.php` constants.
- **Rate Limiting & Cost Protection:** Dual-tier transient throttling (session & IP) executing before any external AI dispatch.
- **REST Endpoints (`gca/v1`):**
  - `POST /wp-json/gca/v1/chat`
  - `POST /wp-json/gca/v1/reset`
  - `POST /wp-json/gca/v1/prechat`
  - `GET  /wp-json/gca/v1/providers`
  - `GET  /wp-json/gca/v1/health` (Admin only)
- **Native Admin Management:** Comprehensive dashboard including Settings, AI Profiles, Appearance Customizer, Conversations, Leads, FAQs, Website Knowledge, Integrations, and Analytics.

## System Requirements
- PHP 8.0 or higher
- WordPress 6.2 or higher
- OpenSSL PHP Extension (AES-256 encryption)
- cURL PHP Extension

---

## Installation & Setup Guide

### 1. Upload & Activate Plugin
1. Download the release package (`gemini-chat-assistant-1.0.1.zip`).
2. In your WordPress Admin, navigate to **Plugins &rarr; Add New &rarr; Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

### 2. Configure AI Providers & API Credentials
1. In the WordPress sidebar, open **Gemini Chat &rarr; Settings**.
2. Select your desired **Default Provider** (`Google Gemini`, `OpenAI`, or `Anthropic Claude`).
3. Enter the API key for your chosen provider(s):
   - **Google Gemini:** Enter Gemini API key (or define `GEMINI_API_KEY` / `GCA_GEMINI_API_KEY` in `wp-config.php`).
   - **OpenAI:** Enter OpenAI API key (`sk-...`, or define `OPENAI_API_KEY` / `GCA_OPENAI_API_KEY`).
   - **Anthropic Claude:** Enter Claude API key (`sk-ant-...`, or define `ANTHROPIC_API_KEY` / `GCA_CLAUDE_API_KEY`).
4. Select the default model for each enabled provider.
5. Click **Save Settings**. Use the **Test Connection** buttons to verify connectivity.

> **Note:** API provider accounts and usage charges are separate and billed directly by Google, OpenAI, or Anthropic.

### 3. Customize Chatbot & Appearance
1. **AI Persona:** Visit **Gemini Chat &rarr; AI Assistant** to customize bot personality, instructions, tone, and behavioral rules.
2. **Appearance:** Visit **Gemini Chat &rarr; Appearance** to adjust brand colors, avatar, widget sizing, launcher icon, and placement.
3. **Public Selection Controls:** If you wish to allow visitors to pick the AI provider or model, enable the toggles under the **Public Chat Controls** section in Settings.
4. **Deploy:** Place `[gemini_chat]` on any page, or rely on the global floating widget launcher.

---

## Deactivation & Uninstall Policies
- **Deactivation:** Flushes rewrite rules and clears temporary caches. Retains all settings, API credentials, conversation history, AI profiles, and leads.
- **Uninstall:** When deleted via WordPress Admin, plugin settings and conversation records are preserved by default. To completely purge all database tables and options on uninstall, enable **Wipe all data on uninstall** in **Gemini Chat &rarr; Settings** prior to deleting the plugin.

---

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

## License
GPLv2 or later.
