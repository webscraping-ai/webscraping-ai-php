<?php

declare(strict_types=1);

namespace WebScrapingAI;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
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
    public const VERSION = '4.0.2';

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
        $result = $this->get('/ai/fields', [
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

        \assert(is_array($result));

        return $result;
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
        $result = $this->get('/selected-multiple', [
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

        \assert(is_array($result));

        return $result;
    }

    /**
     * Account quota and limits.
     *
     * @return array<int|string, mixed>
     */
    public function account(): array
    {
        $result = $this->get('/account', []);
        \assert(is_array($result));

        return $result;
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
        } catch (ClientExceptionInterface $exception) {
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

    private function wrapTransportException(ClientExceptionInterface $exception): \Throwable
    {
        $message = $exception->getMessage();
        $isTimeout = $exception instanceof NetworkExceptionInterface
            && stripos($message, 'timed out') !== false;

        if ($exception instanceof NetworkExceptionInterface) {
            if ($isTimeout || stripos($message, 'timeout') !== false) {
                return new ApiTimeoutException($message, 0, $exception);
            }

            return new ApiConnectionException($message, 0, $exception);
        }

        if ($exception instanceof RequestExceptionInterface) {
            return new ApiConnectionException($message, 0, $exception);
        }

        return new ApiConnectionException($message, 0, $exception);
    }
}
