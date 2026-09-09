# Testing Strategy & Quality Assurance: Gemini Chat Assistant

## 1. Testing Pyramid

```
                ┌──────────────────┐
                │   E2E & UI       │  (Playwright / Cypress / Manual WP)
                │     Tests        │
                ├──────────────────┤
                │   Integration    │  (WordPress REST API, Database migrations,
                │     Tests        │   Repository operations with WP_Mock/WP_Unit)
                ├──────────────────┤
                │   Unit Tests     │  (PHPUnit: GeminiClient, TokenCounter,
                │   (Fast & Pure)  │   RateLimiter, Encryption, PromptBuilder)
                └──────────────────┘
```

## 2. Test Suites

### 2.1 Unit Tests (`tests/Unit/`)
- **`GeminiClientTest.php`:** Mocks `wp_remote_post` responses to test valid responses, rate limit responses (429), API error responses (400/500), timeout handling, and token calculations.
- **`EncryptionTest.php`:** Verifies two-way encryption and decryption fidelity with AES-256-GCM.
- **`RateLimiterTest.php`:** Verifies throttling behavior, transient key generation, and reset intervals.
- **`ContextManagerTest.php`:** Verifies rolling window truncation and multi-turn message formatting.

### 2.2 Integration Tests (`tests/Integration/`)
- **`RestRoutesTest.php`:** Dispatches synthetic requests to `/wp-json/gca/v1/chat`, `/wp-json/gca/v1/reset`, and `/wp-json/gca/v1/health` verifying status codes, schema compliance, and authorization blocks.
- **`DatabaseMigrationTest.php`:** Runs `Migrator::migrate()` against a test database and asserts that table structures, indexes, and foreign keys are created correctly.
- **`ShortcodeTest.php`:** Verifies that `[gemini_chat]` properly enqueues styles and scripts with localized nonces.

### 2.3 Knowledge & FAQ Test Suites (`tests/`)
- **`test-faqs.php`:** Verifies FAQ Repository CRUD, table constants, XSS sanitization (`wp_kses_post`), question/category length capping, active/home filtering, sort ordering, and search filtering.
- **`test-knowledge-indexer.php`:** Verifies HTML tag stripping, shortcode stripping without execution, Gutenberg block comment removal, multibyte sentence chunking boundaries, chunk count limits, content deduplication via SHA-256 hashes, and non-public post status exclusion.
- **`test-knowledge-retriever.php`:** Verifies query tokenization, stopword removal, candidate scoring (exact phrase matches, title matches, FAQ boosts), irrelevant query rejection, relevance thresholding, and budget enforcement.
- **`test-rag-context.php`:** Verifies untrusted context framing delimiters, prompt-injection defense containment, source attribution formatting, HTML exclusion in context output, and seamless N15 AI Profile persona/rules preservation.
- **`test-integrations-framework.php`:** Verifies Business Integration Framework contracts, ActionInterface schemas, ActionResult value objects, ActionValidator type checking & rogue argument stripping, ActionExecutor exception handling, duplicate registration protection, and risk classification metadata.
- **`test-woocommerce-integration.php`:** Verifies WooCommerceIntegration availability detection, SearchProductsAction query validation & limits, GetProductAction ID casting & publish check, SearchByCategoryAction, WooCommerceFormatter attribute sanitization, and strict exclusion of cart/checkout functions.
- **`test-handoff-service.php`:** Verifies Human Handoff schema v1.3.0, HandoffRepository CRUD operations & active request duplicate prevention, HandoffService intent detection & automatic N14 Lead association, controlled status transitions, integration registry adapter registration, and strict exclusion of external transport/mail/multi-provider APIs.
- **`test-email-notifications.php`:** Verifies NotificationService recipient parsing, whitespace trimming & 10-recipient ceiling, subject sanitization & CR/LF header injection defense, plain-text body formatting with PII minimization, failure isolation on wp_mail() false, idempotency markers via transients, settings sanitization, and SendHandoffNotificationAction framework registration.
- **`test-direct-contact-channels.php`:** Verifies Direct Contact Channels settings defaults, phone/email/WhatsApp sanitization, tel/mailto/wa.me URL construction, XSS protection, localized config structures, and negative security assertions (zero third-party APIs).
- **`test-provider-credentials.php`:** Verifies ProviderInterface contracts, ProviderRegistry lookup/checks, Gemini/OpenAI/Claude adapters, AES-256 encrypted credential persistence in `gca_provider_credentials`, environment/constant override priorities, empty submission protection, regex credential masking in ProviderException, and zero outbound HTTP requests for placeholder adapters.
- **`test-openai-integration.php`:** Verifies OpenAIClient Responses API endpoint (`POST /v1/responses`), Bearer authorization headers, payload structure (`store: false`, model, instructions, input array), multi-turn history alternation, single and multi-part output text assembly, token usage extraction into `ProviderResponse`, request ID capture, HTTP 400/401/404/408/429/500/WP_Error mapping to `ProviderException`, secret scrubbing in exception messages, admin 1-token test connection query, ChatService dynamic routing, zero Gemini regressions, and Claude placeholder isolation.
- **`test-claude-integration.php`:** Verifies ClaudeClient Messages API endpoint (`POST /v1/messages`), `x-api-key` and `anthropic-version: 2023-06-01` headers, payload construction (`model`, `max_tokens`, top-level `system` prompt, sanitized `messages` array), multi-turn history alternation, single and multi-block text assembly, token usage extraction into `ProviderResponse`, stop reason normalization, request ID capture, HTTP 400/401/403/404/408/413/429/500/529/WP_Error mapping to `ProviderException`, secret scrubbing, admin 1-token test connection query, ChatService multi-turn routing, and Gemini/OpenAI regression prevention.


### 2.4 Static Analysis & Linting
- **PHP_CodeSniffer (PHPCS):** WordPress-Core, WordPress-Extra, and WordPress-Docs standards.
- **PHPStan:** Level 8 static analysis for strict typing and null safety.
- **ESLint / Prettier:** Linting and formatting for JavaScript and CSS assets.

## 3. Test Execution Commands
```bash
# Run PHPUnit tests
composer test

# Run PHPCS linting
composer lint

# Run PHPStan static analysis
composer phpstan

# Run frontend linting
npm run lint
```
