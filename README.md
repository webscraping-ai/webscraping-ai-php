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

[Sign up](https://webscraping.ai/auth/sign_up) to get an API key — the free
trial includes 2,000 credits, no credit card required. Your key lives in the
[dashboard](https://webscraping.ai/dashboard).

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

// Structured data for a page on a supported site (YouTube, TikTok, X, ...)
$video = $client->data(url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

// Account quota
$account = $client->account();
```

All optional parameters (`headers`, `timeout`, `js`, `js_timeout`, `wait_for`, `proxy`, `country`, `custom_proxy`, `device`, `error_on_404`, `error_on_redirect`, `js_script`, …) are PHP named arguments. See [the API docs](https://webscraping.ai/docs) for the full parameter reference.

## Search engine results (SERP)

`serp()` returns parsed Google results for a query. It is query-shaped rather than URL-shaped, so none of the page-scraping parameters above apply — only `q` (required), `engine` (`"google"`, the default), `gl` (country, default `"us"`), `hl` (language, default `"en"`) and `page` (1–100, 10 results per page; the server rejects values above 100 with a 400). Flat 15 credits per search; failed searches are not charged.

The client validates before sending: an empty or whitespace-only `q`, or a `page` below 1, throws `\InvalidArgumentException` and no request (or charge) is made. The server also rejects a bad `page` with a 400 (not billed); checking client-side saves the round trip.

```php
$serp = $client->serp(q: 'coffee machines', gl: 'us', hl: 'en', page: 1);

foreach ($serp['organic_results'] as $result) {
    printf("%d. %s — %s\n", $result['position'], $result['title'], $result['link']);
}

$nextPage = $serp['pagination']['next'] ?? null; // absent on the last page
```

The decoded array has `search_parameters`, `search_information`, `organic_results` (`position`, `title`, `link`, `domain`, `displayed_link`, and optionally `snippet` / `date`), `related_searches` (optional) and `pagination`. Optional keys are omitted rather than set to `null`.

## Structured data for supported sites

`data()` returns structured JSON for a public page on a supported site — pass the page's normal URL, e.g. a YouTube video/channel/playlist, TikTok video/profile, X (Twitter) post/profile, LinkedIn company/job/profile, Instagram post/reel/profile or Reddit post/subreddit/user. The site (`provider`) and page kind (`type`) are detected from the URL. 15 credits per request (including results that parse empty or no longer exist); failed fetches are not charged.

More sites and page types are added on the server over time and work without upgrading this package, so the client does **not** check which sites are supported — only that `url` is non-blank (a blank `url` throws `\InvalidArgumentException` before any request). An unsupported URL or page type returns a 400 that is not charged (`BadRequestException`). Its message lists what is supported. For other sites use `fields()`.

None of the page-scraping parameters (`js`, `proxy`, `headers`, `timeout`, …) apply. The options are:

- `country` — two-letter country code of the proxy used to fetch the page, `us` by default. The server rejects unknown codes with a 400.
- `transcript` — YouTube videos only. Also fetch the video's transcript into `data.transcript`. It's null when no matching captions are available. If the transcript fetch itself fails, the whole request fails with a 500 and is not charged.
- `transcriptLanguage` — caption language to pick, e.g. `en` or `de`. Without it, English is preferred, then the first available track. If the video has no captions in that language, `data.transcript` is null.

```php
use WebScrapingAI\Exception\BadRequestException;

$result = $client->data(url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', transcript: true);

if ($result['parse_status'] === 'ok') {
    echo $result['request_parameters']['provider'], ': ', $result['data']['title'], "\n";
}

try {
    $client->data(url: 'https://example.com/');
} catch (BadRequestException $e) {
    echo $e->getMessage(); // "Unsupported URL for /data. ..." — lists what is supported
}
```

The decoded array has `request_parameters` (`url`, `provider`, `type`), `parse_status` and `data`. `provider` and `type` are open sets — expect new values. `parse_status` is `ok`, `parse_failed` (fetched but not parsed; `data` may be `null` or partial) or `not_found`; all three are charged successes. The shape of `data` depends on `provider` and `type`.

Provider-specific parameters added server-side after this release can be sent through `params`, as-is (scalar values; `null` is dropped). `api_key`, `url`, `country`, `transcript` and `transcript_language` are rejected with `\InvalidArgumentException` — use the named arguments for those, even when you haven't set them:

```php
$client->data(url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', params: ['some_new_option' => 'value']);
```

## Bring your own HTTP client

By default, the client builds its own transport. If Guzzle is installed it is used with a request deadline applied (see [Timeouts](#timeouts)); otherwise `php-http/discovery` resolves whatever PSR-18 client is installed. To pin a specific client, pass it explicitly:

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

Injecting your own client opts out of the default deadline — configure transport-level timeouts on the client you pass.

## Timeouts

Two different timeouts are in play, and they're easy to confuse:

- The `timeout` parameter accepted by each endpoint method (`html()`, `text()`, …) controls **server-side page retrieval** — how long the API waits for the target page. It does not bound how long your HTTP client waits.
- The **transport timeout** bounds how long the PSR-18 client itself will wait on TCP connect and on reading the response body, so a stalled connection can't hang your process forever.

By default the client applies a transport deadline when it builds its own client and Guzzle is available: a total request timeout of `Client::DEFAULT_TIMEOUT` (60s) and a TCP connect timeout of `Client::DEFAULT_CONNECT_TIMEOUT` (10s). Override them via the constructor:

```php
$client = new Client(
    apiKey: getenv('WEBSCRAPING_AI_KEY'),
    timeout: 120.0,        // total request deadline, seconds
    connectTimeout: 5.0,   // TCP connect deadline, seconds
);
```

These constructor timeouts apply **only** to the auto-built default client. If you inject your own `httpClient`, or no concrete client (Guzzle) is available and discovery falls back to an unknown PSR-18 implementation, no deadline is imposed — in that case inject a client with timeouts configured to get one.

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

## Links

- [WebScraping.AI](https://webscraping.ai) — features, pricing, signup
- [API documentation](https://webscraping.ai/docs)
- [Dashboard](https://webscraping.ai/dashboard) — API key, usage, request builder
- Other official clients: [Python](https://github.com/webscraping-ai/webscraping-ai-python) · [JavaScript](https://github.com/webscraping-ai/webscraping-ai-js) · [Ruby](https://github.com/webscraping-ai/webscraping-ai-ruby) · [Go](https://github.com/webscraping-ai/webscraping-ai-go) · [Java](https://github.com/webscraping-ai/webscraping-ai-java) · [.NET](https://github.com/webscraping-ai/webscraping-ai-dotnet) · [CLI](https://github.com/webscraping-ai/webscraping-ai-cli) · [MCP server](https://github.com/webscraping-ai/webscraping-ai-mcp-server) · [n8n node](https://github.com/webscraping-ai/webscraping-ai-n8n)
- Support: [support@webscraping.ai](mailto:support@webscraping.ai)

## License

MIT — see [LICENSE](LICENSE).
