# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Changed

- Docs: stop stating credit prices (they're set server-side and change); link to https://webscraping.ai/docs pricing instead.

## [4.2.0] — 2026-09-25
### Added

- `Client::data()` for the new `/data` endpoint: structured JSON for a page on a supported site (e.g. YouTube, TikTok, X, LinkedIn, Instagram, Reddit), with optional `country`, `transcript` and `transcriptLanguage`. Returns the decoded `DataResult` JSON (`request_parameters`, `parse_status`, `data`). 15 credits per request.
- The URL is not checked against a list of sites client-side (only non-blank; a blank `url` throws `\InvalidArgumentException`), so sites added on the server work without a client upgrade. An unsupported URL or page type returns a 400 that is not charged (`BadRequestException`). Its message lists what is supported.
- `data()` accepts `array $params` for provider-specific query parameters added server-side later, sent as-is. Numeric keys are sent as strings. `api_key`, `url`, `country`, `transcript`, `transcript_language` (use the named arguments, even when unset) or a non-scalar value throws `\InvalidArgumentException`.
- `bin/smoke.php` adds a YouTube `/data` call (asserts `parse_status` `ok`, provider `youtube` and a non-empty `data.title`) and an `https://example.com/` call that must come back as a server 400 whose message contains `Unsupported URL` (~46 credits per sweep).

### Security

- Transport errors no longer leak the API key. HTTP clients embed the request URL (which carries `api_key`) in their error messages — Guzzle ends connect/timeout errors with `for https://...&api_key=KEY` — and that text was copied into `ApiConnectionException`/`ApiTimeoutException` on every endpoint, with the raw PSR-18 exception (whose `getRequest()->getUri()` also holds the key) chained as `previous`. Messages are now redacted (`api_key=[REDACTED]`), the original exception is no longer chained (its class name is kept in the message), and non-PSR `\RuntimeException`s thrown by an HTTP client are wrapped and redacted too.

## [4.1.0] — 2026-09-25

### Added

- `Client::serp()` for the new `/serp` endpoint: parsed search engine results for a query (`q`, optional `engine`, `gl`, `hl`, `page`). Returns the decoded `SerpResult` JSON. Flat 15 credits per search; failed searches are not charged.
- `serp()` validates its input before sending: whitespace-only `q` and `page` < 1 throw `\InvalidArgumentException` (the server also rejects an invalid page with a 400, not billed; checking client-side saves the round trip). Pages are 1–100: the server rejects a `page` above 100 with a 400.
- `bin/smoke.php` now asserts on result shape (non-empty results, SERP `organic_results` and echoed `q`, a non-empty `selected_multiple` match, `fields` `result` key), runs page tools with `js=false` + datacenter proxy (~31 credits), catches every exception per case, redacts the API key from failure output and prints single-line previews.

### Fixed

- `fields()`, `selectedMultiple()`, `serp()` and `account()` no longer fail with an `assert`/`TypeError` when a 2xx response isn't JSON; they now throw `ApiException` (status 200, raw body in `$responseBody`), keeping every failure inside the `WebScrapingAIException` hierarchy.

## [4.0.2] — 2026-07-17

### Changed

- Documentation: expanded README — API docs, signup/dashboard links, badges, and links to the other official clients.

## [4.0.1] — 2026-06-21

### Fixed

- The default HTTP client now applies a request deadline. When no PSR-18 client is injected and Guzzle is available, the client is built with `timeout` (60s) and `connect_timeout` (10s) so requests can no longer hang indefinitely on connect or body reads. Both are configurable via new constructor parameters; injecting your own client opts out.

## [4.0.0] — 2026-05-12

### Changed (breaking)

- **Complete rewrite.** The package is no longer generated from the OpenAPI spec; it is a hand-authored, idiomatic PHP client. There are no deprecation shims — pin to `^3.2` if you need the old surface.
- **Namespace moved** from `OpenAPI\Client\` to `WebScrapingAI\`.
- **Public surface simplified** to a single `WebScrapingAI\Client` with one method per endpoint. The old per-tag classes (`AIApi`, `HTMLApi`, `TextApi`, `SelectedHTMLApi`, `AccountApi`) are gone.
- **HTTP client is now PSR-18.** Guzzle is no longer a hard dependency — bring any PSR-18 / PSR-17 implementation you like. `php-http/discovery` is used to auto-resolve one if none is supplied.
- **Minimum PHP raised to 8.2.**
- **License changed** from Unlicense to MIT.

### Added

- Typed exception hierarchy for every documented status code: `BadRequestException` (400), `PaymentRequiredException` (402), `AuthenticationException` (403), `RateLimitException` (429), `ServerException` (500), `GatewayTimeoutException` (504), all inheriting from `ApiException`.
- Transport-level exceptions: `ApiTimeoutException`, `ApiConnectionException`.
- Marker interface `WebScrapingAIException` implemented by every SDK exception.
- Custom query encoder correctly handling the three encoding styles the API mixes (`deepObject` for `headers`/`fields`, `form` for `selectors`, flat for everything else).
- PHPUnit test suite, PHPStan level-8 analysis, php-cs-fixer config, GitHub Actions CI matrix on PHP 8.2 / 8.3 / 8.4.
