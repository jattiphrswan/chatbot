# NODE 24 STATUS: BLOCKED

## Follow-up: WordPress critical error

The latest screenshot shows `/gca/v1/chat` HTTP 504 and HTTP 500, with WordPress critical-error HTML displayed literally in the widget. This is evidence of a server failure, not proof of invalid Google credentials. A subsequent live probe returned HTTP 429, so further live retries were stopped. The exact PHP fatal message has been requested from the hosting log/recovery email and is still unavailable.

The chat service call is now guarded against catchable PHP Throwable errors. The REST response remains structured JSON with a generic CHAT_SERVER_ERROR; an admin-only Last Chat Server Error panel records the UTC time, request ID, exception class, relative file and line. Exception messages/traces are deliberately excluded because they can contain secrets. This cannot recover uncatchable memory exhaustion, execution-limit termination or errors occurring before the guarded service call. Those still require hosting logs.

The widget now suppresses WordPress critical-error HTML and handles non-JSON proxy failures as server errors (504 as timeout), instead of showing HTML or blaming the visitor's connection. JS asset cache versioning ensures this update loads. Tests: REST controller 30/30, chat error messages 5/5, assets 13/13, PHP/JS syntax and diff checks passed. These are error-handling improvements, not a proven fix for the underlying live PHP failure.

## Follow-up: admin passes, frontend fails

The user's latest screenshot proves admin generation returned HTTP 200 and passed. A fresh live frontend request (`give me brief about website`) still returned public HTTP 503 / `GEMINI_UNAVAILABLE`, request ID `0388d553-fed2-4e5d-a453-1badd4828f4b`. The exact Google error for that chat request remains inaccessible from the public response.

The settings page now exposes **Latest Chat Generation** independently of Test Connection. It displays the actual chat model, Google HTTP/code/status/message, transport error, timestamp and request ID. Admin tests cannot overwrite this frontend record. The contradictory missing-key hint is corrected when a configured server fallback is present. Regression tests: 57 passed, 0 failed; modified PHP syntax passed. Current dist folder and ZIP include this diagnostic follow-up. This improves diagnosis; it is not a claim that the live frontend failure has been fixed.

After installing this update, send one frontend message and reload Settings. The Latest Chat Generation section supplies the evidence needed for the remaining fix. Earlier findings below are retained as historical validation.

Local implementation and verification completed on 2026-09-10. The patch has not been deployed to templates.skyfish.in. Live acceptance requires authenticated WordPress or server access, which was not supplied. No API credential was requested or exposed.

## Files changed

- `includes/class-gemini-client.php`: shared generation path, saved provider model, REST payload corrections, redacted upstream diagnostics and error classification.
- `includes/class-chat-service.php`: stored conversation history passed as generateContent contents.
- `includes/class-assets.php`: CSS version includes file modification time to refresh cached typography.
- `templates/admin/settings.php`: escaped HTTP status, Google error code/status/message and endpoint fields.
- `public/css/chat.css`: scoped title/welcome typography and header/card spacing.
- `tests/test-gemini-client.php`: corrected REST contract expectations and added failure/success regression coverage.
- `ARCHITECTURE.md`, `DB_SHAPES.md`, `SECURITY.md`, `TESTING.md`, `CHANGELOG.md`: corresponding behavior and validation notes.
- `NODE24-REPORT.md`: this report.

Backups were made before changes in `C:\Users\SkyFish\AppData\Local\Temp\gca-node24-20260910-154338`. Existing unrelated `REVIEW.md` was left untouched.

## Root cause and fixes

The old admin test discarded Google's error payload and reduced all generation HTTP 5xx responses to a generic temporary-service message. It also accepted any HTTP 2xx without validating generated text and used a separate one-token ping payload. Those are confirmed code defects; they do not establish the precise upstream cause shown in the screenshot.

Frontend generation still sent `previous_interaction_id`, a legacy Interactions field, to generateContent. That field is removed; stored conversation turns now travel in `contents`. The endpoint uses the saved provider model exactly, with the legacy model alias as fallback through SettingsService. The payload uses `systemInstruction` and `generationConfig`; the model is specified in the endpoint. See [Google's generateContent reference](https://ai.google.dev/api/generate-content).

Admin Test Connection now calls the same generation method with `Reply only with OK`. A response must contain generated text to pass. HTTP 400, 401/403, 404, 429, 500/503 and transport failures are distinguishable. A temporary service error preserves earlier successful authentication/model discovery and invites retry. Existing retry controls remain usable. Keys are sent only in server-side headers and redacted from recorded error messages.

The latest admin report stores and displays safe upstream metadata. `gca_gemini_last_generation` separately records the most recent generation transport metadata, including frontend requests. No raw prompt, request header or response body is added to these diagnostic options.

## Gemini HTTP response

- Google upstream status: **unknown**; no authenticated diagnostic or server log access.
- Google upstream error code/message: **unknown**.
- Live WordPress public endpoint: `POST https://templates.skyfish.in/wp-json/gca/v1/chat`, prompt `Hello`.
- Observed public HTTP status: **503**.
- Public error code: `GEMINI_UNAVAILABLE`.
- Public message: `The assistant is temporarily unavailable. Please try again shortly.`
- Request ID: `5583e0b0-bfd6-4a1d-86c3-a7507219d5ef`.

The WordPress status is mapped by the existing deployed plugin and must not be reported as Google's actual HTTP status. The live request did not use the local patch.

## UI changes and checks

Desktop title: 21px, weight 650, line-height 1.2. Welcome: 30px, weight 700, line-height 1.15, bottom margin 9px. Mobile: 19px title and 27px welcome. Header gaps/padding leave room for the avatar and both actions. Suggestion titles can wrap. No colors, card styling or launcher behavior were changed.

Headless Edge rendered local fixtures made from the live widget markup and patched stylesheet at a 370px widget in an 800px viewport and a 320px mobile viewport. Both showed the full AI Assistant title, aligned emoji, visible refresh/close controls, and no title/heading/card overflow. These are local static rendering checks, not post-deployment checks of all theme assets.

## Tests

| Requested check | Result |
| --- | --- |
| API authentication | PASS in supplied screenshot and mocks; live admin retest BLOCKED |
| Model discovery | PASS in supplied screenshot and mocks; live refresh BLOCKED |
| Model persistence | PASS in local reload/model tests; live save/reload BLOCKED |
| Generation request | PASS in mocked success/error cases; actual patched Google generation BLOCKED |
| Frontend chatbot response | FAIL on currently deployed site: public HTTP 503 |
| Desktop UI | PASS, local static browser rendering |
| Mobile UI | PASS, local static browser rendering |
| PHP/JS errors | PASS, syntax checks and targeted Gemini tests; live logs BLOCKED |

- Gemini client: **55 passed, 0 failed**.
- Provider credentials: **68 passed, 0 failed**.
- Provider/model selection: **33 passed, 0 failed** (includes existing source-based checks, not live API checks).
- Conversation memory: **22 passed, 0 failed** (mocked).
- Assets: **13 passed, 0 failed**.
- Chat service: **10 passed, 4 failed**; identical on the unchanged baseline (model metadata and three historical public error expectations).
- Settings: **10 passed, 2 failed**; identical on the unchanged baseline (old gemini-3.8-flash default expectations).
- PHP lint passed for modified PHP files; `node --check public/js/chat.js` and `git diff --check` passed.

## Exact remaining issue

Install the prepared Node 24 ZIP on the supplied site, then use an authenticated WordPress session to save the dashboard key, refresh models, confirm the selection after reload and run Test Connection. Inspect its new HTTP/Google fields and send Hello through the frontend. Server logs and post-deployment desktop/mobile verification remain unavailable. NODE 24 cannot pass until actual generation succeeds or the exact upstream error is captured and displayed accurately.
