# WebScraping.AI PHP Client

[![Packagist Version](https://img.shields.io/packagist/v/webscraping-ai/webscraping-ai-php.svg)](https://packagist.org/packages/webscraping-ai/webscraping-ai-php)
[![CI](https://github.com/webscraping-ai/webscraping-ai-php/actions/workflows/ci.yml/badge.svg)](https://github.com/webscraping-ai/webscraping-ai-php/actions/workflows/ci.yml)

Official PHP client for the [WebScraping.AI](https://webscraping.ai) API.

The API gives you LLM-powered scraping tools with Chromium JavaScript rendering, rotating proxies, and built-in HTML parsing — full HTML, visible text, selected page areas, AI-extracted fields, and free-form question answering over any URL.

## Requirements

- PHP 8.2 or newer
- A [PSR-18 HTTP client](https://packagist.org/providers/psr/http-client-implementation) — Guzzle, Symfony HttpClient, or any other implementation
- A [PSR-17 message factory](https://packagist.org/providers/psr/http-factory-implementation)

If you don't already have these installed, the simplest pair is:

```bash
composer require guzzlehttp/guzzle nyholm/psr7
```

`php-http/discovery` (a transitive dependency) will pick them up automatically.

## Installation

```bash
composer require webscraping-ai/webscraping-ai-php
```

## Quick start

```php
use WebScrapingAI\Client;

$client = new Client(apiKey: getenv('WEBSCRAPING_AI_KEY'));

// Full HTML
$html = $client->html(url: 'https://example.com');

// Visible text
$text = $client->text(url: 'https://example.com');

// HTML for one selector
$h1 = $client->selected(url: 'https://example.com', selector: 'h1');

// HTML for multiple selectors (returns array)
$chunks = $client->selectedMultiple(
    url: 'https://example.com',
    selectors: ['h1', 'p', 'a'],
);

// LLM question over a page
$answer = $client->question(
    url: 'https://example.com',
    question: 'What is the main topic?',
);

// LLM-extracted structured fields
$fields = $client->fields(
    url: 'https://example.com',
    fields: [
        'title' => 'Main product title',
        'price' => 'Current price',
    ],
);

// Account quota
$account = $client->account();
```

All optional parameters (`headers`, `timeout`, `js`, `js_timeout`, `wait_for`, `proxy`, `country`, `custom_proxy`, `device`, `error_on_404`, `error_on_redirect`, `js_script`, …) are PHP named arguments. See [the API docs](https://webscraping.ai/docs) for the full parameter reference.

## Bring your own HTTP client

By default, `php-http/discovery` resolves a PSR-18 client at runtime from whatever's installed. To pin a specific client, pass it explicitly:

```php
use GuzzleHttp\Client as Guzzle;
use Nyholm\Psr7\Factory\Psr17Factory;
use WebScrapingAI\Client;

$factory = new Psr17Factory();
$client = new Client(
    apiKey: getenv('WEBSCRAPING_AI_KEY'),
    httpClient: new Guzzle(['timeout' => 30.0]),
    requestFactory: $factory,
    uriFactory: $factory,
);
```

Configure transport-level timeouts on your HTTP client. The `timeout` parameter accepted by each endpoint method controls server-side page retrieval timeout, not the HTTP transport.

## Errors

The client raises typed exceptions for every documented status code:

| Status | Exception |
| --- | --- |
| 400 | `WebScrapingAI\Exception\BadRequestException` |
| 402 | `WebScrapingAI\Exception\PaymentRequiredException` |
| 403 | `WebScrapingAI\Exception\AuthenticationException` |
| 429 | `WebScrapingAI\Exception\RateLimitException` |
| 500 | `WebScrapingAI\Exception\ServerException` |
| 504 | `WebScrapingAI\Exception\GatewayTimeoutException` |

All inherit from `WebScrapingAI\Exception\ApiException`, which exposes `$message`, `$status`, `$statusCode`, `$statusMessage`, `$body`, and `$responseBody`. The latter three are populated when the API surfaces target-page errors as 500s.

Transport-level failures raise `WebScrapingAI\Exception\ApiTimeoutException` (the PSR-18 client timed out) or `WebScrapingAI\Exception\ApiConnectionException` (DNS / connection refused / TLS).

All SDK-originated exceptions implement the marker interface `WebScrapingAI\Exception\WebScrapingAIException`, so a single `catch (WebScrapingAIException $e)` block catches everything.

## Response shapes

The client returns whatever the API returns — it does not normalise or unwrap. A couple of current quirks worth knowing:

- `fields()` returns `['result' => [...fields...]]` (the live API wraps the extracted fields under a `result` key).
- `selectedMultiple()` returns `array<int, array<int, string>>` — an outer wrapper containing all matched chunks concatenated.

These are upstream spec/server drifts; the official Ruby and Python clients return the same shapes.

## Migration from 3.x

3.x was generated from the OpenAPI spec under the namespace `OpenAPI\Client\` and used per-tag classes (`AIApi`, `HTMLApi`, etc.). 4.0 is a hand-authored rewrite with a single `WebScrapingAI\Client` entry point. There are no deprecation shims — pin to `^3.2` if you need the old surface.

## Development

```bash
composer install
composer test       # PHPUnit
composer lint       # php-cs-fixer (dry-run)
composer analyse    # PHPStan
```

## License

MIT — see [LICENSE](LICENSE).
