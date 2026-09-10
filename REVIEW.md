# Project review — 2026-09-10

## Result and scope

The automated baseline passes, but the plugin has functional and security-related defects that need attention before release. This was a local source review, test run, syntax check, and comparison of the existing release archive. Application files were not changed.

- `php tests/run-all-tests.php`: 35/35 suites passed.
- `php -l`: all 122 PHP files discovered by `rg --files` passed on PHP 8.2.33.
- `node --check`: all 3 JavaScript files passed on Node 22.15.1.
- Existing ZIP: 91 entries; 12 included files differ from their workspace counterparts.
- The runtime bootstrap suite simulates WordPress with mocks. Passing it does not verify real WordPress activation, SQL migrations, browser behavior, or live provider requests.
- No live WordPress/database/browser or paid AI API verification was performed. PHP 8.0 compatibility was not executed. Current provider model availability was not verified.

## Findings, ordered by priority

### 1. High: logged-in-only chat loses browser authentication

Locations: `public/js/chat.js:689`, `:786`, `:1031`; `includes/class-assets.php`; `includes/class-rest-controller.php:216`.

The frontend sends JSON requests without a REST nonce, and its localized configuration supplies none. WordPress cookie authentication treats a REST request without a nonce as anonymous. Consequently, setting `guest_access=false` rejects even logged-in users using the widget; with guest access enabled, their conversations are treated as guest conversations.

Fix: generate and send a `wp_rest` nonce for authenticated browser requests and cover logged-in and anonymous behavior through actual WordPress REST dispatch. Public nonce handling should also be reconciled with PROJECT_INSTRUCTIONS.md; a guest nonce alone is not an abuse-prevention mechanism.

WordPress reference: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/

### 2. High: knowledge index can retain newly protected content

Locations: `includes/Knowledge/KnowledgeIndexer.php:190`, `:357`; `includes/Database/KnowledgeRepository.php:415`; `includes/Admin/AdminMenu.php:922`.

Source-type disabling returns before checking post privacy, and disabling knowledge globally skips save synchronization. Retrieval only checks the index's stored status, without rechecking the original post or enabled source types.

Reproduction sequence: index a published page, disable knowledge, password-protect that page while keeping its published status, then re-enable knowledge without a full sync. The old chunks remain eligible for answers. The status-transition hook does not remove them because the publication status did not change. Separately, disabling page/post/FAQ knowledge does not remove or exclude previously indexed chunks.

Fix: invalidate protected content regardless of indexing settings, filter disabled source types during retrieval, and reconcile the index when settings change. Verify original content visibility before returning chunks.

### 3. Medium: rate-limit updates are not atomic

Location: `includes/class-rate-limiter.php`, `check_and_consume()` and `increment_window()`.

All limit checks happen separately from counter consumption. Incrementing also uses a separate get/modify/set sequence despite its atomicity comment. Concurrent PHP workers can pass the same remaining allowance and overwrite increments, allowing more paid requests than configured. This is a source-level concurrency finding, not a measured load-test result.

Fix: reserve allowance using an atomic storage operation or a correctly scoped lock; test concurrent requests against a real shared backend.

### 4. Medium: resetting during a request displays the old reply in the new conversation

Locations: `public/js/chat.js:722`, `:781`.

Reset remains available while chat is pending. It changes the session token and clears the UI, but does not cancel the old fetch or invalidate its callbacks. When the pending request resolves, its assistant reply is appended to the new conversation. Reset also retains `state.prechatCompleted`, despite generating a new session token.

Fix: abort or ignore obsolete requests using a per-conversation generation identifier, and explicitly reset or transfer prechat state according to the intended flow. Verify with a delayed network response in a browser.

### 5. Medium: full knowledge sync permanently skips older content

Location: `includes/Knowledge/KnowledgeIndexer.php:424`.

`sync_all()` fetches at most 200 published posts/pages/products with no pagination. Repeating it selects the same batch; older existing content remains unindexed until individually saved. FAQs similarly stop at 500. The `errors` counter never increments, and indexing failures are counted as skips.

Fix: process successive batches with a cursor or pagination, persist progress for large sites, and distinguish actual failures from unchanged content.

### 6. Medium: release archive does not match reviewed source

Artifact: `dist/gemini-chat-assistant-1.0.1.zip`.

SHA-256 comparisons of each included file against its corresponding workspace file found differences in:

- `includes/class-assets.php`
- `includes/class-gemini-client.php`
- `includes/Admin/AdminMenu.php`
- `includes/Admin/SettingsService.php`
- `includes/Database/LeadRepository.php`
- `includes/Database/SessionService.php`
- `includes/notifications/class-notification-service.php`
- `includes/Providers/ModelRegistry.php`
- `public/css/chat.css`
- `public/js/chat.js`
- `templates/chat-widget.php`
- `templates/admin/settings.php`

The workspace test results therefore do not establish the archive's behavior. Rebuild and verify the archive after fixes. The existing archive was not overwritten.

### 7. Medium: health endpoint reports only Gemini configuration

Location: `includes/class-rest-controller.php:413`.

The health response uses the Gemini client and legacy model setting regardless of the selected provider. A working OpenAI-only or Claude-only installation reports `configured=false` and an unrelated model. It also reports `healthy` without checking database operation or provider connectivity.

Fix: report the effective provider/model and distinguish configuration status from connectivity and database checks.

### 8. Low: documented testing tools do not exist in the repository

Location: `TESTING.md`.

The documented `composer test`, `composer lint`, `composer phpstan`, and `npm run lint` commands cannot run from this checkout: no composer.json or package.json exists. The documented PHPUnit directories are absent. The claim that the runtime bootstrap suite verifies real WordPress is inconsistent with that suite's mocks.

Fix: document the actual PHP runner and syntax checks, label mocked tests correctly, and add the advertised tool configurations only if those checks are intended to be supported.

## Additional observations

- Knowledge code calls several `mb_*` functions without guards, while README requirements omit mbstring. Validate operation without that extension or document and enforce it.
- SecretStore silently falls back to XOR obfuscation if OpenSSL is absent or encryption fails. That fallback does not meet the README's AES-encrypted-storage promise; reject credential persistence with a clear admin error when strong encryption is unavailable.
- The release script skips a missing LICENSE file silently. A license identifier is present in the plugin header, but no standalone LICENSE file is present in the checkout.

## Suggested verification after fixes

Use a disposable WordPress installation to test activation and migrations, guest and logged-in chat, prechat and reset during pending requests, all three providers, knowledge privacy changes, concurrent rate limiting, and installation from the rebuilt ZIP. Run the current 35 suites again alongside focused regression coverage for these defects.
