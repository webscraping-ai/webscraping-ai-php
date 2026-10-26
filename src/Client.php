<?php

declare(strict_types=1);

namespace WebScrapingAI;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriFactoryInterface;
use WebScrapingAI\Exception\ApiConnectionException;
use WebScrapingAI\Exception\ApiException;
use WebScrapingAI\Exception\ApiTimeoutException;
use WebScrapingAI\Exception\AuthenticationException;
use WebScrapingAI\Exception\BadRequestException;
use WebScrapingAI\Exception\GatewayTimeoutException;
use WebScrapingAI\Exception\PaymentRequiredException;
use WebScrapingAI\Exception\RateLimitException;
use WebScrapingAI\Exception\ServerException;
use WebScrapingAI\Internal\QueryEncoder;

/**
 * Synchronous client for the WebScraping.AI API.
 *
 * Backed by any PSR-18 HTTP client. By default `php-http/discovery` resolves
 * one at runtime from whatever's installed (Guzzle, Symfony HttpClient, etc.);
 * pass your own client to the constructor to bypass discovery.
 */
final class Client
{
    public const VERSION = '4.3.0';

    public const DEFAULT_BASE_URL = 'https://api.webscraping.ai';

    /** Default total request timeout (seconds) applied to the auto-built HTTP client. */
    public const DEFAULT_TIMEOUT = 60.0;

    /** Default TCP connect timeout (seconds) applied to the auto-built HTTP client. */
    public const DEFAULT_CONNECT_TIMEOUT = 10.0;

    /** @var array<int, class-string<ApiException>> */
    private const STATUS_TO_EXCEPTION = [
        400 => BadRequestException::class,
        402 => PaymentRequiredException::class,
        403 => AuthenticationException::class,
        429 => RateLimitException::class,
        500 => ServerException::class,
        504 => GatewayTimeoutException::class,
    ];

    /** /data query parameters that have a named argument (wire name => PHP argument name). */
    private const DATA_NAMED_PARAMS = [
        'country' => 'country',
        'transcript' => 'transcript',
        'transcript_language' => 'transcriptLanguage',
    ];

    private readonly ClientInterface $httpClient;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly UriFactoryInterface $uriFactory;
    private readonly string $baseUrl;

    /**
     * @param ClientInterface|null $httpClient     PSR-18 client. Inject one to take full control of
     *                                             transport timeouts — when you do, no deadline is
     *                                             imposed by this client (it's your responsibility).
     *                                             When omitted, a default client is built with a total
     *                                             request deadline of `$timeout` seconds and a TCP
     *                                             connect deadline of `$connectTimeout` seconds, so
     *                                             requests can't hang forever on a stalled connection
     *                                             or body read.
     * @param float|null           $timeout        Total request timeout (seconds) for the auto-built
     *                                             client. Only applied when no `$httpClient` is
     *                                             injected and a known concrete client (Guzzle) is
     *                                             available.
     * @param float|null           $connectTimeout TCP connect timeout (seconds), same caveats.
     */
    public function __construct(
        private readonly string $apiKey,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?UriFactoryInterface $uriFactory = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        ?float $timeout = self::DEFAULT_TIMEOUT,
        ?float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('apiKey must be a non-empty string');
        }

        $this->httpClient = $httpClient ?? self::defaultHttpClient(
            $timeout ?? self::DEFAULT_TIMEOUT,
            $connectTimeout ?? self::DEFAULT_CONNECT_TIMEOUT,
        );
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->uriFactory = $uriFactory ?? Psr17FactoryDiscovery::findUriFactory();
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Build the default PSR-18 client used when the caller doesn't inject one.
     *
     * Prefers Guzzle (if present) so a real request deadline can be applied:
     * `timeout` covers the whole transfer including the body read, and
     * `connect_timeout` covers the TCP connect — addressing both stalled-connect
     * and stalled-body-read hangs. Without Guzzle there is no portable way to set
     * a timeout on an unknown PSR-18 client, so discovery is used best-effort and
     * callers should inject a pre-configured client to get a deadline.
     */
    private static function defaultHttpClient(float $timeout, float $connectTimeout): ClientInterface
    {
        if (class_exists(\GuzzleHttp\Client::class)) {
            return new \GuzzleHttp\Client([
                'timeout' => $timeout,
                'connect_timeout' => $connectTimeout,
            ]);
        }

        return Psr18ClientDiscovery::find();
    }

    /**
     * Ask an LLM a question about the target page.
     *
     * @param array<string, string>|null $headers HTTP headers forwarded to the target page.
     * @return string|array<int|string, mixed> Plain string when `format` is `"text"`, decoded JSON otherwise.
     */
    public function question(
        string $url,
        string $question,
        ?array $headers = null,
        ?int $timeout = null,
        ?bool $js = null,
        ?int $jsTimeout = null,
        ?string $waitFor = null,
        ?string $proxy = null,
        ?string $country = null,
        ?string $customProxy = null,
        ?string $device = null,
        ?bool $errorOn404 = null,
        ?bool $errorOnRedirect = null,
        ?string $jsScript = null,
        ?string $format = null,
    ): string|array {
        return $this->get('/ai/question', [
            'url' => $url,
            'question' => $question,
            'headers' => $headers,
            'timeout' => $timeout,
            'js' => $js,
            'js_timeout' => $jsTimeout,
            'wait_for' => $waitFor,
            'proxy' => $proxy,
            'country' => $country,
            'custom_proxy' => $customProxy,
            'device' => $device,
            'error_on_404' => $errorOn404,
            'error_on_redirect' => $errorOnRedirect,
            'js_script' => $jsScript,
            'format' => $format,
        ]);
    }

    /**
     * Extract structured fields from the target page using an LLM.
     *
     * Note: the live API currently wraps the extracted fields under a `result` key
     * (`{"result": {...fields...}}`), which differs from the OpenAPI example. The
     * client returns whatever the API returns — unwrap on your side if needed.
     *
     * @param array<string, string>      $fields  Field name → description.
     * @param array<string, string>|null $headers HTTP headers forwarded to the target page.
     * @return array<int|string, mixed>
     */
    public function fields(
        string $url,
        array $fields,
        ?array $headers = null,
        ?int $timeout = null,
        ?bool $js = null,
        ?int $jsTimeout = null,
        ?string $waitFor = null,
        ?string $proxy = null,
        ?string $country = null,
        ?string $customProxy = null,
        ?string $device = null,
        ?bool $errorOn404 = null,
        ?bool $errorOnRedirect = null,
        ?string $jsScript = null,
    ): array {
        return $this->getArray('/ai/fields', [
            'url' => $url,
            'fields' => $fields,
            'headers' => $headers,
            'timeout' => $timeout,
            'js' => $js,
            'js_timeout' => $jsTimeout,
            'wait_for' => $waitFor,
            'proxy' => $proxy,
            'country' => $country,
            'custom_proxy' => $customProxy,
            'device' => $device,
            'error_on_404' => $errorOn404,
            'error_on_redirect' => $errorOnRedirect,
            'js_script' => $jsScript,
        ]);
    }

    /**
     * Fetch the full HTML of the target page.
     *
     * @param array<string, string>|null $headers HTTP headers forwarded to the target page.
     * @return string|array<int|string, mixed> Plain HTML string by default; decoded JSON when `format` is `"json"`.
     */
    public function html(
        string $url,
        ?array $headers = null,
        ?int $timeout = null,
        ?bool $js = null,
        ?int $jsTimeout = null,
        ?string $waitFor = null,
        ?string $proxy = null,
        ?string $country = null,
        ?string $customProxy = null,
        ?string $device = null,
        ?bool $errorOn404 = null,
        ?bool $errorOnRedirect = null,
        ?string $jsScript = null,
        ?bool $returnScriptResult = null,
        ?string $format = null,
    ): string|array {
        return $this->get('/html', [
            'url' => $url,
            'headers' => $headers,
            'timeout' => $timeout,
            'js' => $js,
            'js_timeout' => $jsTimeout,
            'wait_for' => $waitFor,
            'proxy' => $proxy,
            'country' => $country,
            'custom_proxy' => $customProxy,
            'device' => $device,
            'error_on_404' => $errorOn404,
            'error_on_redirect' => $errorOnRedirect,
            'js_script' => $jsScript,
            'return_script_result' => $returnScriptResult,
            'format' => $format,
        ]);
    }

    /**
     * Fetch the visible text of the target page.
     *
     * @param array<string, string>|null $headers HTTP headers forwarded to the target page.
     * @return string|array<int|string, mixed> Plain text by default; decoded JSON when `text_format` is `"json"`;
     *                                         XML string when `text_format` is `"xml"`.
     */
    public function text(
        string $url,
        ?string $textFormat = null,
        ?bool $returnLinks = null,
        ?array $headers = null,
        ?int $timeout = null,
        ?bool $js = null,
        ?int $jsTimeout = null,
        ?string $waitFor = null,
        ?string $proxy = null,
        ?string $country = null,
        ?string $customProxy = null,
        ?string $device = null,
        ?bool $errorOn404 = null,
        ?bool $errorOnRedirect = null,
        ?string $jsScript = null,
    ): string|array {
        return $this->get('/text', [
            'text_format' => $textFormat,
            'return_links' => $returnLinks,
            'url' => $url,
            'headers' => $headers,
            'timeout' => $timeout,
            'js' => $js,
            'js_timeout' => $jsTimeout,
            'wait_for' => $waitFor,
            'proxy' => $proxy,
            'country' => $country,
            'custom_proxy' => $customProxy,
            'device' => $device,
            'error_on_404' => $errorOn404,
            'error_on_redirect' => $errorOnRedirect,
            'js_script' => $jsScript,
        ]);
    }

    /**
     * Fetch HTML of a single page area selected by CSS selector.
     *
     * @param array<string, string>|null $headers HTTP headers forwarded to the target page.
     * @return string|array<int|string, mixed> HTML fragment, or decoded JSON when `format` is `"json"`.
     */
    public function selected(
        string $url,
        ?string $selector = null,
        ?array $headers = null,
        ?int $timeout = null,
        ?bool $js = null,
        ?int $jsTimeout = null,
        ?string $waitFor = null,
        ?string $proxy = null,
        ?string $country = null,
        ?string $customProxy = null,
        ?string $device = null,
        ?bool $errorOn404 = null,
        ?bool $errorOnRedirect = null,
        ?string $jsScript = null,
        ?string $format = null,
    ): string|array {
        return $this->get('/selected', [
            'selector' => $selector,
            'url' => $url,
            'headers' => $headers,
            'timeout' => $timeout,
            'js' => $js,
            'js_timeout' => $jsTimeout,
            'wait_for' => $waitFor,
            'proxy' => $proxy,
            'country' => $country,
            'custom_proxy' => $customProxy,
            'device' => $device,
            'error_on_404' => $errorOn404,
            'error_on_redirect' => $errorOnRedirect,
            'js_script' => $jsScript,
            'format' => $format,
        ]);
    }

    /**
     * Fetch HTML of multiple page areas selected by CSS selectors.
     *
     * Note: the live API returns `Array<Array<string>>` (a single outer wrapper
     * containing all matches concatenated), which differs from the OpenAPI
     * `SelectedAreas` schema. The client returns whatever the API returns.
     *
     * @param list<string>|null          $selectors CSS selectors. Empty/null returns the whole page HTML.
     * @param array<string, string>|null $headers   HTTP headers forwarded to the target page.
     * @return array<int|string, mixed>
     */
    public function selectedMultiple(
        string $url,
        ?array $selectors = null,
        ?array $headers = null,
        ?int $timeout = null,
        ?bool $js = null,
        ?int $jsTimeout = null,
        ?string $waitFor = null,
        ?string $proxy = null,
        ?string $country = null,
        ?string $customProxy = null,
        ?string $device = null,
        ?bool $errorOn404 = null,
        ?bool $errorOnRedirect = null,
        ?string $jsScript = null,
    ): array {
        return $this->getArray('/selected-multiple', [
            'selectors' => $selectors,
            'url' => $url,
            'headers' => $headers,
            'timeout' => $timeout,
            'js' => $js,
            'js_timeout' => $jsTimeout,
            'wait_for' => $waitFor,
            'proxy' => $proxy,
            'country' => $country,
            'custom_proxy' => $customProxy,
            'device' => $device,
            'error_on_404' => $errorOn404,
            'error_on_redirect' => $errorOnRedirect,
            'js_script' => $jsScript,
        ]);
    }

    /**
     * Search engine results (SERP) for a query.
     *
     * Query-shaped, not URL-shaped: none of the page-scraping options (js, proxy,
     * country, headers, timeout, ...) apply. Billed per results page returned (see
     * https://webscraping.ai/docs#serp); failed searches are not charged. Returns the decoded `SerpResult` JSON
     * (`search_parameters`, `search_information`, `organic_results`,
     * `related_searches`, `pagination`); optional keys may be absent.
     *
     * Every search runs from one fixed location (`location`, `lat` + `lon` or `uule`,
     * otherwise the capital of the `gl` country), so repeats and later pages stay
     * consistent. With `pages` > 1, `organic_results` is one de-duplicated list whose
     * `position` counts through it and whose results carry a `page` key.
     *
     * Combinations (`location` vs `uule` vs `lat` + `lon`, `radius` without a location)
     * and ranges are checked by the server, which rejects them with a 400 (not billed).
     *
     * @param string      $q        Search query (required, not empty or whitespace-only; sent as given).
     * @param string|null $engine   Search engine; currently only `"google"` (the API default).
     * @param string|null $gl       Two-letter country code. Defaults to the country of `location`,
     *                              `lat`/`lon` or `uule`, otherwise `"us"`.
     * @param string|null $hl       Two-letter language code (API default `"en"`).
     * @param int|null    $page     Results page number, 1-based (API default 1). Must be >= 1; the
     *                              server rejects > 100 with a 400 (not billed).
     * @param int|null    $pages    Number of consecutive pages to fetch from `page` on, as one
     *                              de-duplicated list (server: 1 to 5). Must be >= 1.
     * @param string|null $location Where the search runs from: a name such as `"Austin,Texas,United States"`
     *                              or `"Austin, TX"`, or an id from serpLocations(). Sent as given; an
     *                              unknown name is a 400 (not billed).
     * @param string|null $uule     Google-encoded location (`w+CAIQICI…` or `a+…`).
     * @param float|null  $lat      Latitude of the search origin in decimal degrees (requires `lon`).
     * @param float|null  $lon      Longitude of the search origin in decimal degrees (requires `lat`).
     * @param int|null    $radius   Radius in meters around `location` or `lat`/`lon` (server: 1 to 1000).
     *                              Must be >= 1.
     * @return array<int|string, mixed>
     *
     * @throws \InvalidArgumentException When `q` is empty/whitespace-only, `page`, `pages` or `radius`
     *                                   is < 1, or `lat`/`lon` is NAN or infinite (no request is sent).
     */
    public function serp(
        string $q,
        ?string $engine = null,
        ?string $gl = null,
        ?string $hl = null,
        ?int $page = null,
        ?int $pages = null,
        ?string $location = null,
        ?string $uule = null,
        ?float $lat = null,
        ?float $lon = null,
        ?int $radius = null,
    ): array {
        if (trim($q) === '') {
            throw new \InvalidArgumentException('q must be a non-empty, non-whitespace string');
        }
        if ($page !== null && $page < 1) {
            throw new \InvalidArgumentException('page must be an integer >= 1');
        }
        if ($pages !== null && $pages < 1) {
            throw new \InvalidArgumentException('pages must be an integer >= 1');
        }
        if ($radius !== null && $radius < 1) {
            throw new \InvalidArgumentException('radius must be an integer >= 1');
        }

        return $this->getArray('/serp', [
            'q' => $q,
            'engine' => $engine,
            'gl' => $gl,
            'hl' => $hl,
            'page' => $page,
            'pages' => $pages,
            'location' => $location,
            'uule' => $uule,
            'lat' => self::coordinate('lat', $lat),
            'lon' => self::coordinate('lon', $lon),
            'radius' => $radius,
        ]);
    }

    /**
     * Look up names for serp()'s `location` argument.
     *
     * Free and needs no API key on the server (the key is still sent, like on every
     * call). Returns the decoded JSON list of `SerpLocation` objects, most populous
     * first: `id` (string), `google_id`, `google_parent_id` (absent or null for
     * countries), `name`, `canonical_name` (pass it, or `id`, as `location`),
     * `country_code`, `target_type` (an open set, e.g. `City`, `State`, `Country`),
     * `reach` (approximate population) and `gps` (`[longitude, latitude]`).
     * Matches English names, e.g. `petersburg`.
     *
     * @param string|null $q     Part of a location name, e.g. `"austin"`. Without it, the most
     *                           populous locations are returned.
     * @param int|null    $limit Maximum number of locations to return (server: 1 to 10, 10 by
     *                           default). Must be >= 1.
     * @return array<int|string, mixed>
     *
     * @throws \InvalidArgumentException When `limit` is < 1 (no request is sent).
     */
    public function serpLocations(?string $q = null, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 1) {
            throw new \InvalidArgumentException('limit must be an integer >= 1');
        }

        return $this->getArray('/serp/locations', [
            'q' => $q,
            'limit' => $limit,
        ]);
    }

    /**
     * Format a latitude/longitude as the plain decimal the API accepts (`^-?\d+(\.\d+)?$`).
     *
     * `(string)` on a float depends on the `precision` ini setting and switches to
     * exponent form for small values (`1.0E-7`), which the server rejects. This
     * rounds to 7 fractional digits (about 1 cm) and trims trailing zeros, so
     * `1e-7` → `"0.0000001"`, `48.0` → `"48"` and `1e-9` → `"0"`.
     *
     * @throws \InvalidArgumentException When the value is NAN or infinite.
     */
    private static function coordinate(string $name, ?float $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_finite($value)) {
            throw new \InvalidArgumentException("{$name} must be a finite number");
        }

        $text = rtrim(rtrim(number_format($value, 7, '.', ''), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    /**
     * Structured data for any public web page.
     *
     * Pass the page's normal URL; the site the page belongs to (`provider`, e.g.
     * `toscrape.com`) and the kind of page (`type`, e.g. `product`) are detected
     * server-side. Some popular sites have dedicated parsers with fixed fields; for
     * other sites an AI model learns each new kind of page once. The client does not
     * validate the URL beyond "non-blank".
     *
     * Pricing: see https://webscraping.ai/docs#data. `parse_failed` / `not_found`
     * results are charged; failed fetches are not.
     *
     * Returns the decoded `DataResult` JSON: `request_parameters` (`url`, `provider`,
     * `type` — open sets of strings), `parse_status` (`ok`, `parse_failed` or
     * `not_found`) and `data` (provider/type-specific array, or `null`).
     *
     * The first request for a new kind of page also learns its structure, so it can
     * take up to about 45 seconds (keep the transport timeout above that). When
     * learning takes longer, or another request is already learning that site,
     * the API answers HTTP 503 with `error_code` `structure_processing` and
     * `retry_after` (seconds), not charged; it is raised as the base `ApiException`
     * (`$status === 503`, raw JSON in `$responseBody`). Retry after `retry_after`
     * seconds, which can return data. When no structured data can be found
     * (empty page, login wall), the API answers 422 `structure_unavailable` (not
     * charged), raised as the base `ApiException`; use fields() instead.
     *
     * @param string      $url                Page URL (required, not empty or whitespace-only; sent as given).
     * @param string|null $country            Two-letter country code of the proxy used to fetch the page, `us` by
     *                                        default. The server checks it and rejects unknown ones with a 400.
     * @param bool|null   $transcript         Video pages on sites with a dedicated parser only. Also fetch the
     *                                        video's transcript into `data.transcript`. It's null when no matching
     *                                        captions are available.
     *                                        If the transcript fetch itself fails, the whole request fails with a
     *                                        500 and is not charged.
     * @param string|null $transcriptLanguage With transcript=true. Caption language to pick, e.g. `en` or `de`.
     *                                        Without it, English is preferred, then the first available track. If
     *                                        the video has no captions in that language, `data.transcript` is null.
     * @param array<int|string, string|int|float|bool|null> $params Extra query parameters sent as-is, e.g.
     *                                        options added server-side after this release.
     *                                        Scalar values only; `null` values are dropped. May not contain
     *                                        `api_key`, `url`, `country`, `transcript` or `transcript_language`
     *                                        (use the named arguments for those).
     * @return array<int|string, mixed>
     *
     * @throws \InvalidArgumentException When `url` is blank or `$params` is invalid (no request is sent).
     */
    public function data(
        string $url,
        ?string $country = null,
        ?bool $transcript = null,
        ?string $transcriptLanguage = null,
        array $params = [],
    ): array {
        if (trim($url) === '') {
            throw new \InvalidArgumentException('url must be a non-empty, non-whitespace string');
        }

        $query = [
            'url' => $url,
            'country' => $country,
            'transcript' => $transcript,
            'transcript_language' => $transcriptLanguage,
        ];

        foreach ($params as $key => $value) {
            // PHP turns numeric-string keys like '123' into ints; send them as strings.
            $key = (string) $key;
            if ($key === '') {
                throw new \InvalidArgumentException('params keys must be non-empty strings');
            }
            if ($key === 'api_key' || $key === 'url') {
                throw new \InvalidArgumentException("params may not contain \"{$key}\"");
            }
            if (isset(self::DATA_NAMED_PARAMS[$key])) {
                $named = self::DATA_NAMED_PARAMS[$key];
                throw new \InvalidArgumentException(
                    "params may not contain \"{$key}\"; use the named argument \${$named} instead"
                );
            }
            if ($value !== null && !is_scalar($value)) {
                throw new \InvalidArgumentException("params[\"{$key}\"] must be a string, int, float, bool or null");
            }
            $query[$key] = $value;
        }

        return $this->getArray('/data', $query);
    }

    /**
     * Account quota and limits.
     *
     * @return array<int|string, mixed>
     */
    public function account(): array
    {
        return $this->getArray('/account', []);
    }

    /**
     * Like get(), for endpoints that always answer with JSON. A 2xx response whose
     * body isn't a JSON object/array raises ApiException instead of leaking a
     * TypeError out of the SDK's exception hierarchy.
     *
     * @param array<string, mixed> $params
     * @return array<int|string, mixed>
     */
    private function getArray(string $path, array $params): array
    {
        $result = $this->get($path, $params);
        if (is_array($result)) {
            return $result;
        }

        throw new ApiException(
            message: "Expected a JSON response from {$path}, got a non-JSON body",
            status: 200,
            responseBody: $result,
        );
    }

    /**
     * @param array<string, mixed> $params Raw parameter map; `null` values are dropped by the encoder.
     * @return string|array<int|string, mixed>
     */
    private function get(string $path, array $params): string|array
    {
        $params['api_key'] = $this->apiKey;
        $queryString = QueryEncoder::encode($params);

        $uri = $this->uriFactory->createUri($this->baseUrl . $path);
        if ($queryString !== '') {
            $uri = $uri->withQuery($queryString);
        }

        $request = $this->requestFactory
            ->createRequest('GET', $uri)
            ->withHeader('Accept', 'application/json, text/html, text/plain, text/xml')
            ->withHeader('User-Agent', 'webscraping-ai-php/' . self::VERSION);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface|\RuntimeException $exception) {
            // \RuntimeException: some PSR-18 adapters leak non-PSR exceptions; wrap (and redact) them too.
            throw $this->wrapTransportException($exception);
        }

        $this->raiseForStatus($response);

        return $this->parseResponse($response);
    }

    private function raiseForStatus(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return;
        }

        $body = (string) $response->getBody();
        $envelope = $this->tryDecodeEnvelope($body);

        $exceptionClass = self::STATUS_TO_EXCEPTION[$status] ?? ApiException::class;
        $message = is_string($envelope['message'] ?? null) && $envelope['message'] !== ''
            ? $envelope['message']
            : "HTTP {$status}";
        $statusCode = isset($envelope['status_code']) && is_int($envelope['status_code'])
            ? $envelope['status_code']
            : null;
        $statusMessage = isset($envelope['status_message']) && is_string($envelope['status_message'])
            ? $envelope['status_message']
            : null;
        $envelopeBody = isset($envelope['body']) && is_string($envelope['body'])
            ? $envelope['body']
            : null;

        throw new $exceptionClass(
            message: $message,
            status: $status,
            statusCode: $statusCode,
            statusMessage: $statusMessage,
            body: $envelopeBody,
            responseBody: $body,
        );
    }

    /**
     * @return string|array<int|string, mixed>
     */
    private function parseResponse(ResponseInterface $response): string|array
    {
        $body = (string) $response->getBody();
        $contentType = $response->getHeaderLine('Content-Type');

        if ($this->isJsonContentType($contentType)) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $body;
    }

    private function isJsonContentType(string $contentType): bool
    {
        return $contentType !== '' && str_contains(strtolower($contentType), 'application/json');
    }

    /**
     * @return array<string, mixed>
     */
    private function tryDecodeEnvelope(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Map a transport failure to the SDK's exception hierarchy without leaking the API key.
     *
     * The key travels in the query string, and HTTP clients embed the request URL in
     * their error messages (Guzzle: "cURL error 6: ... for https://...&api_key=KEY"),
     * so the message is redacted and the original exception is deliberately NOT chained
     * as `previous`: its message and `getRequest()->getUri()` both carry the key. The
     * original class name is kept in the message for debugging.
     */
    private function wrapTransportException(\Throwable $exception): \Throwable
    {
        $message = $this->redact($exception->getMessage()) . ' (' . $exception::class . ')';

        $original = $exception->getMessage();
        $isTimeout = stripos($original, 'timed out') !== false || stripos($original, 'timeout') !== false;
        if ($exception instanceof NetworkExceptionInterface && $isTimeout) {
            return new ApiTimeoutException($message);
        }

        return new ApiConnectionException($message);
    }

    private function redact(string $text): string
    {
        $text = str_replace([$this->apiKey, rawurlencode($this->apiKey), urlencode($this->apiKey)], '[REDACTED]', $text);

        return (string) preg_replace('/(api_key|api%5Fkey)=[^&\s"\'<>]*/i', '$1=[REDACTED]', $text);
    }
}
