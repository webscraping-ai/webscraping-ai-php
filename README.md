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

[Sign up](https://webscraping.ai/auth/sign_up) to get an API key — a free
trial, no credit card required. Your key lives in the
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

// Structured data for any public web page
$page = $client->data(url: 'https://books.toscrape.com/catalogue/a-light-in-the-attic_1000/index.html');

// Account quota
$account = $client->account();
```

All optional parameters (`headers`, `timeout`, `js`, `js_timeout`, `wait_for`, `proxy`, `country`, `custom_proxy`, `device`, `error_on_404`, `error_on_redirect`, `js_script`, …) are PHP named arguments. See [the API docs](https://webscraping.ai/docs) for the full parameter reference.

## Search engine results (SERP)

`serp()` returns parsed Google results for a query. It is query-shaped rather than URL-shaped, so none of the page-scraping parameters above apply. The options are `q` (required), `engine` (`"google"`, the default), `gl` (country), `hl` (language, default `"en"`), `page` (1–100, 10 results per page; the server rejects values above 100 with a 400), `pages`, `location`, `uule`, `lat`, `lon` and `radius`. Billed per results page returned (see [pricing](https://webscraping.ai/docs#serp)); failed searches are not charged.

Every search runs from one fixed location: `location`, `lat` + `lon`, or `uule`, otherwise the capital of the `gl` country. So repeats and later pages stay consistent. `gl` defaults to the location's country (`"us"` when there is none). `pages` (1–5) fetches consecutive pages starting at `page` as one de-duplicated list, billed per page returned: `position` counts through the combined list and each result gets a `page` key.

- `location` — a name such as `"Austin,Texas,United States"` or `"Austin, TX"` (large cities also match local names, e.g. `"Москва"`), or an `id` from `serpLocations()`. An unknown name returns a 400 (not charged).
- `uule` — a Google-encoded location (`w+CAIQICI…` or `a+…`).
- `lat` / `lon` — the search origin in decimal degrees (both required together). Sent as a plain decimal rounded to 7 fractional digits.
- `radius` — meters around `location` or `lat`/`lon`, 1–1000.

The client validates before sending: an empty or whitespace-only `q`, a `page`, `pages` or `radius` below 1, or a NAN/infinite `lat`/`lon` throws `\InvalidArgumentException` and no request (or charge) is made. Ranges and combinations (`location` with `uule`, `lat` without `lon`, …) are checked by the server, which rejects them with a 400 (not billed).

```php
$serp = $client->serp(q: 'coffee machines', location: 'Austin, TX', pages: 2);

echo $serp['search_parameters']['location_used'], "\n"; // "Austin,Texas,United States"

foreach ($serp['organic_results'] as $result) {
    printf("%d. [p%d] %s — %s\n", $result['position'], $result['page'], $result['title'], $result['link']);
}

$nextPage = $serp['pagination']['next'] ?? null; // absent on the last page
```

The decoded array has `search_parameters` (with `location_requested`, `location_used`, `lat`, `lon`, `radius` and `pages` when they apply), `search_information`, `organic_results` (`position`, `title`, `link`, `domain`, `displayed_link`, and optionally `snippet` / `date` / `page`), `related_searches` (optional) and `pagination` (with `pages_returned` when `pages` > 1). Optional keys are omitted rather than set to `null`.

### Search locations

`serpLocations()` looks up names for `location`. It is free and needs no API key on the server. It returns up to 10 locations (`limit`, 1–10), most populous first, each with `id`, `google_id`, `google_parent_id` (absent or `null` for countries), `name`, `canonical_name`, `country_code`, `target_type` (e.g. `City`, `State`, `Country`), `reach` (approximate population) and `gps` (`[longitude, latitude]`). It matches English names (e.g. `petersburg`). Pass `canonical_name` (or `id`) to `serp()` as `location`:

```php
$locations = $client->serpLocations(q: 'austin', limit: 3);

$serp = $client->serp(q: 'coffee machines', location: $locations[0]['canonical_name']);
```

## Structured data for any web page

`data()` returns structured JSON for any public web page — pass the page's normal URL. The site the page belongs to (`provider`, e.g. `toscrape.com`) and the kind of page (`type`, e.g. `product`) are detected server-side. Some popular sites have dedicated parsers with fixed fields; for other sites an AI model learns the structure of each new kind of page once, and from then on pages of that kind on that site return data straight away for every user. Pricing: see [the API docs](https://webscraping.ai/docs#data). Failed fetches are not charged.

The client only checks that `url` is non-blank (a blank `url` throws `\InvalidArgumentException` before any request).

The named options are:

- `country` — two-letter country code of the proxy used to fetch the page, `us` by default. The server rejects unknown codes with a 400.
- `transcript` — video pages on sites with a dedicated parser only. Also fetch the video's transcript into `data.transcript`. It's null when no matching captions are available. If the transcript fetch itself fails, the whole request fails with a 500 and is not charged.
- `transcriptLanguage` — with `transcript: true`. Caption language to pick, e.g. `en` or `de`. Without it, English is preferred, then the first available track. If the video has no captions in that language, `data.transcript` is null.

```php
$result = $client->data(url: 'https://books.toscrape.com/catalogue/a-light-in-the-attic_1000/index.html');

if ($result['parse_status'] === 'ok') {
    echo $result['request_parameters']['provider'], ': ', $result['data']['title'], "\n";
    // toscrape.com: A Light in the Attic
}
```

The decoded array has `request_parameters` (`url`, `provider`, `type`), `parse_status` and `data`, e.g.:

```php
[
    'request_parameters' => ['url' => 'https://books.toscrape.com/catalogue/a-light-in-the-attic_1000/index.html', 'provider' => 'toscrape.com', 'type' => 'product'],
    'parse_status' => 'ok',
    'data' => ['title' => 'A Light in the Attic', 'price' => 51.77, 'currency' => 'GBP', /* ... */],
]
```

`provider` and `type` are open sets — expect new values. `parse_status` is `ok`, `parse_failed` (fetched but not parsed; `data` may be `null` or partial) or `not_found`; all three are charged successes. The shape of `data` depends on `provider` and `type`.

### Structure still being learned (503) and no structured data (422)

On a site without a dedicated parser, the first request for a new kind of page also learns its structure, so it can take up to about 45 seconds; keep the transport timeout (see [Timeouts](#timeouts), 60s by default) above that. When learning takes longer, or another request is already learning that site, the API answers **HTTP 503** with the body `{"message": "...", "error_code": "structure_processing", "retry_after": 30}` (plus a `Retry-After` header). It is not charged. 503 has no dedicated exception class, so it's raised as the base `WebScrapingAI\Exception\ApiException` (`$status === 503`; the raw JSON, including `error_code` and `retry_after`, is in `$responseBody`). Retry after `retry_after` seconds; the retry can return data (if learning failed meanwhile, it may learn again or answer the 422 below):

```php
use WebScrapingAI\Exception\ApiException;

try {
    $result = $client->data(url: $url);
} catch (ApiException $e) {
    if ($e->status !== 503) {
        throw $e;
    }
    sleep(json_decode($e->responseBody ?? '', true)['retry_after'] ?? 30);
    $result = $client->data(url: $url);
}
```

If no structured data can be found on the page (e.g. it's empty or behind a login wall), the API answers **HTTP 422** with `error_code: "structure_unavailable"`, not charged. 422 has no dedicated exception class, so it's raised as the base `WebScrapingAI\Exception\ApiException` (`$status === 422`; the raw JSON, including `error_code`, is in `$responseBody`). Use `fields()` for such pages.

Redirects within the same site (http→https, `www`, trailing slash) are followed; a redirect to another site raises a `ServerException` (HTTP 500, `error_code` `target_redirect`, the target in the message), not charged. Starting too many new kinds of pages in an hour raises a `RateLimitException` (HTTP 429, `error_code` `rate_limited`).

Extra parameters (e.g. ones added server-side after this release) can be sent through `params`, as-is (scalar values; `null` is dropped). `api_key`, `url`, `country`, `transcript` and `transcript_language` are rejected with `\InvalidArgumentException` — use the named arguments for those, even when you haven't set them:

```php
$client->data(url: 'https://books.toscrape.com/catalogue/a-light-in-the-attic_1000/index.html', params: ['some_new_option' => 'value']);
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

Other error statuses (e.g. 503 `structure_processing` and 422 `structure_unavailable` from `data()`) raise the base `WebScrapingAI\Exception\ApiException`.

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
