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

### 2.3 Static Analysis & Linting
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
