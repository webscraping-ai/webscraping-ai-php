# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0] — Unreleased

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
