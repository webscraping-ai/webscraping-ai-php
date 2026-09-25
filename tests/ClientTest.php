<?php

declare(strict_types=1);

namespace WebScrapingAI\Tests;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use WebScrapingAI\Client;
use WebScrapingAI\Exception\ApiConnectionException;
use WebScrapingAI\Exception\ApiException;
use WebScrapingAI\Exception\ApiTimeoutException;
use WebScrapingAI\Exception\AuthenticationException;
use WebScrapingAI\Exception\BadRequestException;
use WebScrapingAI\Exception\GatewayTimeoutException;
use WebScrapingAI\Exception\PaymentRequiredException;
use WebScrapingAI\Exception\RateLimitException;
use WebScrapingAI\Exception\ServerException;

final class ClientTest extends TestCase
{
    private MockClient $http;

    private Client $client;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->http = new MockClient($factory);
        $this->client = new Client(
            apiKey: 'test-key',
            httpClient: $this->http,
            requestFactory: $factory,
            uriFactory: $factory,
        );
    }

    public function testConstructorRejectsEmptyApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Client(apiKey: '');
    }

    public function testInjectedHttpClientIsPreservedUnchanged(): void
    {
        $factory = new Psr17Factory();
        $injected = new MockClient($factory);

        $client = new Client(
            apiKey: 'test-key',
            httpClient: $injected,
            requestFactory: $factory,
            uriFactory: $factory,
        );

        self::assertSame($injected, $this->httpClientOf($client));
    }

    public function testDefaultHttpClientIsGuzzleWhenAvailable(): void
    {
        $client = new Client(apiKey: 'test-key');

        self::assertInstanceOf(\GuzzleHttp\Client::class, $this->httpClientOf($client));
    }

    private function httpClientOf(Client $client): object
    {
        $property = new \ReflectionProperty(Client::class, 'httpClient');

        $value = $property->getValue($client);
        self::assertIsObject($value);

        return $value;
    }

    public function testQuestionSendsAllParameters(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'text/html'], 'an answer'));

        $result = $this->client->question(
            url: 'https://example.com',
            question: 'What is this?',
            headers: ['Cookie' => 'session=abc'],
            timeout: 5000,
            js: true,
            jsTimeout: 2000,
            waitFor: '.ready',
            proxy: 'residential',
            country: 'us',
            errorOn404: false,
            format: 'text',
        );

        self::assertSame('an answer', $result);

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/ai/question', $request->getUri()->getPath());

        $params = $this->parseQuery($request);
        self::assertSame('https://example.com', $params['url']);
        self::assertSame('What is this?', $params['question']);
        self::assertSame('session=abc', $params['headers[Cookie]']);
        self::assertSame('5000', $params['timeout']);
        self::assertSame('true', $params['js']);
        self::assertSame('false', $params['error_on_404']);
        self::assertSame('residential', $params['proxy']);
        self::assertSame('test-key', $params['api_key']);
    }

    public function testQuestionReturnsDecodedJsonWhenContentTypeIsJson(): void
    {
        $payload = json_encode(['answer' => '42'], JSON_THROW_ON_ERROR);
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], $payload));

        $result = $this->client->question(url: 'https://example.com', question: 'q', format: 'json');

        self::assertSame(['answer' => '42'], $result);
    }

    public function testFieldsSerializesDeepObject(): void
    {
        $payload = json_encode(['result' => ['title' => 'Example', 'price' => '$10']], JSON_THROW_ON_ERROR);
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], $payload));

        $result = $this->client->fields(
            url: 'https://example.com',
            fields: ['title' => 'Main title', 'price' => 'Current price'],
        );

        self::assertSame(['result' => ['title' => 'Example', 'price' => '$10']], $result);

        $params = $this->parseQuery($this->lastRequest());
        self::assertSame('Main title', $params['fields[title]']);
        self::assertSame('Current price', $params['fields[price]']);
    }

    public function testHtmlReturnsRawBodyByDefault(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'text/html'], '<html></html>'));

        $result = $this->client->html(url: 'https://example.com', returnScriptResult: true);

        self::assertSame('<html></html>', $result);
        self::assertSame('true', $this->parseQuery($this->lastRequest())['return_script_result']);
    }

    public function testTextSerializesTextFormatAndReturnLinks(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'text/plain'], 'body text'));

        $result = $this->client->text(
            url: 'https://example.com',
            textFormat: 'plain',
            returnLinks: false,
        );

        self::assertSame('body text', $result);

        $params = $this->parseQuery($this->lastRequest());
        self::assertSame('plain', $params['text_format']);
        self::assertSame('false', $params['return_links']);
    }

    public function testSelectedPassesSelector(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'text/html'], '<h1>x</h1>'));

        $result = $this->client->selected(url: 'https://example.com', selector: 'h1');

        self::assertSame('<h1>x</h1>', $result);
        self::assertSame('h1', $this->parseQuery($this->lastRequest())['selector']);
    }

    public function testSelectedMultipleSerializesFormExplodeWithoutBrackets(): void
    {
        $payload = json_encode([['<h1>x</h1>', '<p>y</p>']], JSON_THROW_ON_ERROR);
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], $payload));

        $result = $this->client->selectedMultiple(
            url: 'https://example.com',
            selectors: ['h1', 'p'],
        );

        self::assertSame([['<h1>x</h1>', '<p>y</p>']], $result);

        $rawQuery = $this->lastRequest()->getUri()->getQuery();
        self::assertStringContainsString('selectors=h1', $rawQuery);
        self::assertStringContainsString('selectors=p', $rawQuery);
        self::assertStringNotContainsString('selectors%5B', $rawQuery);
    }

    public function testAccountSendsOnlyApiKey(): void
    {
        $payload = json_encode(['remaining_api_calls' => 100], JSON_THROW_ON_ERROR);
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], $payload));

        $result = $this->client->account();

        self::assertSame(['remaining_api_calls' => 100], $result);

        $params = $this->parseQuery($this->lastRequest());
        self::assertSame('/account', $this->lastRequest()->getUri()->getPath());
        self::assertSame(['api_key' => 'test-key'], $params);
    }

    public function testSerpSendsQueryParametersAndReturnsDecodedJson(): void
    {
        $serp = [
            'search_parameters' => ['engine' => 'google', 'q' => 'coffee machines', 'gl' => 'de', 'hl' => 'de', 'page' => 2],
            'search_information' => ['query_displayed' => 'coffee machines', 'organic_results_state' => 'Results for exact spelling'],
            'organic_results' => [
                ['position' => 1, 'title' => 'Best', 'link' => 'https://example.com/', 'domain' => 'example.com', 'displayed_link' => 'example.com'],
            ],
            'pagination' => ['current' => 2, 'next' => 3],
        ];
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], json_encode($serp, JSON_THROW_ON_ERROR)));

        $result = $this->client->serp(q: 'coffee machines', engine: 'google', gl: 'de', hl: 'de', page: 2);

        self::assertSame($serp, $result);

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/serp', $request->getUri()->getPath());
        self::assertStringContainsString('q=coffee%20machines', $request->getUri()->getQuery());
        self::assertSame([
            'q' => 'coffee machines',
            'engine' => 'google',
            'gl' => 'de',
            'hl' => 'de',
            'page' => '2',
            'api_key' => 'test-key',
        ], $this->parseQuery($request));
    }

    public function testSerpOmitsUnsetOptionalParameters(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], '{"organic_results":[]}'));

        $this->client->serp(q: 'coffee');

        self::assertSame(['q' => 'coffee', 'api_key' => 'test-key'], $this->parseQuery($this->lastRequest()));
    }

    public function testSerpRejectsEmptyQuery(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        try {
            $this->client->serp(q: '');
        } finally {
            self::assertEmpty($this->http->getRequests());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankQueryProvider(): iterable
    {
        yield 'space' => [' '];
        yield 'mixed whitespace' => [" \t\n "];
    }

    #[DataProvider('blankQueryProvider')]
    public function testSerpRejectsWhitespaceOnlyQuery(string $q): void
    {
        try {
            $this->client->serp(q: $q);
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('q must be', $exception->getMessage());
        }
        self::assertEmpty($this->http->getRequests());
    }

    public function testSerpSendsQueryUntrimmed(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], '{"organic_results":[]}'));

        $this->client->serp(q: '  coffee ');

        self::assertSame('  coffee ', $this->parseQuery($this->lastRequest())['q']);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidPageProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'very negative' => [-3];
    }

    #[DataProvider('invalidPageProvider')]
    public function testSerpRejectsPageBelowOne(int $page): void
    {
        try {
            $this->client->serp(q: 'coffee', page: $page);
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('page must be', $exception->getMessage());
        }
        self::assertEmpty($this->http->getRequests());
    }

    public function testSerpRejectsNonIntegerPage(): void
    {
        // The `?int` parameter type is the non-integer guard: under strict_types a float
        // never reaches the client (so no round trip to a server that would 400 it).
        $this->expectException(\TypeError::class);

        try {
            $this->client->serp(q: 'coffee', page: 1.5);
        } finally {
            self::assertEmpty($this->http->getRequests());
        }
    }

    public function testSerpAcceptsPageOne(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], '{"organic_results":[]}'));

        $this->client->serp(q: 'coffee', page: 1);

        self::assertSame('1', $this->parseQuery($this->lastRequest())['page']);
    }

    public function testDataSendsQueryParametersAndReturnsDecodedJson(): void
    {
        $payload = [
            'request_parameters' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'provider' => 'youtube', 'type' => 'video'],
            'parse_status' => 'ok',
            'data' => ['video_id' => 'dQw4w9WgXcQ', 'title' => 'Never Gonna Give You Up', 'transcript' => null],
        ];
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)));

        $result = $this->client->data(
            url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            country: 'de',
            transcript: true,
            transcriptLanguage: 'en',
        );

        self::assertSame($payload, $result);

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/data', $request->getUri()->getPath());
        self::assertStringContainsString('url=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3DdQw4w9WgXcQ', $request->getUri()->getQuery());
        self::assertSame([
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'country' => 'de',
            'transcript' => 'true',
            'transcript_language' => 'en',
            'api_key' => 'test-key',
        ], $this->parseQuery($request));
    }

    public function testDataOmitsUnsetOptionalParametersAndSendsFalse(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], '{"parse_status":"ok","data":{}}'));
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], '{"parse_status":"ok","data":{}}'));

        $this->client->data(url: 'https://www.tiktok.com/@user');
        self::assertSame(['url' => 'https://www.tiktok.com/@user', 'api_key' => 'test-key'], $this->parseQuery($this->lastRequest()));

        $this->client->data(url: 'https://www.youtube.com/watch?v=x', transcript: false);
        self::assertSame('false', $this->parseQuery($this->lastRequest())['transcript']);
    }

    public function testDataSendsUnknownSiteUrlUnmodifiedWithoutClientSideError(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], '{"parse_status":"ok","data":{}}'));

        // Mixed case, %2F, non-ASCII, space, fragment and surrounding spaces: any lower-casing,
        // trimming, fragment dropping or double-encoding would change the bytes below.
        $url = '  https://Example.COM/A%2Fb/ünï?x=1&y=a b#Frag  ';
        $this->client->data(url: $url);

        self::assertCount(1, $this->http->getRequests());
        $rawQuery = $this->lastRequest()->getUri()->getQuery();
        self::assertStringStartsWith('url=' . rawurlencode($url) . '&', $rawQuery);
        self::assertSame($url, $this->parseQuery($this->lastRequest())['url']);
    }

    public function testDataSendsExtraParamsEncoded(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], '{"parse_status":"ok","data":{}}'));

        $this->client->data(url: 'https://www.youtube.com/watch?v=x', params: [
            'comments' => true,
            'limit' => 20,
            'a&b=c' => 'x&y=z',
            'dropped' => null,
        ]);

        $request = $this->lastRequest();
        $rawQuery = $request->getUri()->getQuery();
        self::assertStringContainsString('a%26b%3Dc=x%26y%3Dz', $rawQuery);
        self::assertSame([
            'url' => 'https://www.youtube.com/watch?v=x',
            'comments' => 'true',
            'limit' => '20',
            'a&b=c' => 'x&y=z',
            'api_key' => 'test-key',
        ], $this->parseQuery($request));
    }

    public function testDataAcceptsNumericStringParamKeys(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], '{"parse_status":"ok","data":{}}'));

        // PHP stores the key '123' as int 123.
        $this->client->data(url: 'https://www.youtube.com/watch?v=x', params: ['123' => 'v', 'x']);

        $params = $this->parseQuery($this->lastRequest());
        self::assertSame('v', $params['123']);
        self::assertSame('x', $params['124']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function namedDataParamProvider(): iterable
    {
        yield 'country' => ['country', '$country'];
        yield 'transcript' => ['transcript', '$transcript'];
        yield 'transcript_language' => ['transcript_language', '$transcriptLanguage'];
    }

    #[DataProvider('namedDataParamProvider')]
    public function testDataRejectsExtraParamThatRepeatsNamedArgumentEvenWhenUnset(string $key, string $named): void
    {
        try {
            $this->client->data(url: 'https://www.youtube.com/watch?v=x', params: [$key => 'x']);
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString("\"{$key}\"", $exception->getMessage());
            self::assertStringContainsString("use the named argument {$named}", $exception->getMessage());
        }
        self::assertEmpty($this->http->getRequests());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankUrlProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => [' '];
        yield 'mixed whitespace' => [" \t\n "];
    }

    #[DataProvider('blankUrlProvider')]
    public function testDataRejectsBlankUrl(string $url): void
    {
        try {
            $this->client->data(url: $url);
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('url must be', $exception->getMessage());
        }
        self::assertEmpty($this->http->getRequests());
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidDataParamsProvider(): iterable
    {
        yield 'api_key' => [['api_key' => 'other-key'], 'api_key'];
        yield 'url' => [['url' => 'https://evil.example/'], 'url'];
        yield 'duplicate of set named argument' => [['country' => 'gb'], 'country'];
        yield 'empty key' => [['' => 'x'], 'keys'];
        yield 'array value' => [['nested' => ['a' => 'b']], 'nested'];
        yield 'object value' => [['obj' => new \stdClass()], 'obj'];
    }

    /**
     * @param array<mixed> $params
     */
    #[DataProvider('invalidDataParamsProvider')]
    public function testDataRejectsInvalidExtraParams(array $params, string $mention): void
    {
        try {
            $this->client->data(url: 'https://www.youtube.com/watch?v=x', country: 'us', params: $params);
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString($mention, $exception->getMessage());
            self::assertStringNotContainsString('other-key', $exception->getMessage());
        }
        self::assertEmpty($this->http->getRequests());
    }

    public function testDataParsesUnknownProviderAndNullData(): void
    {
        $payload = '{"request_parameters":{"url":"https://new.example/p/1","provider":"brand_new_site","type":"widget"},"parse_status":"parse_failed","data":null}';
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], $payload));

        $result = $this->client->data(url: 'https://new.example/p/1');

        self::assertSame('brand_new_site', $result['request_parameters']['provider']);
        self::assertSame('widget', $result['request_parameters']['type']);
        self::assertSame('parse_failed', $result['parse_status']);
        self::assertArrayHasKey('data', $result);
        self::assertNull($result['data']);
    }

    public function testDataUnsupportedUrlMapsToBadRequestWithoutLeakingApiKey(): void
    {
        $body = '{"message":"Unsupported URL for /data. Supported sites: youtube, tiktok, twitter, linkedin, instagram, reddit. For other sites, use /ai/fields"}';
        $this->http->addResponse(new Response(400, ['Content-Type' => 'application/json'], $body));

        try {
            $this->client->data(url: 'https://example.com/anything');
            self::fail('Expected BadRequestException');
        } catch (BadRequestException $exception) {
            self::assertSame(400, $exception->status);
            self::assertStringStartsWith('Unsupported URL for /data.', $exception->getMessage());
            self::assertNull($exception->statusCode);
            $this->assertNoApiKeyInChain($exception);
        }
    }

    /**
     * A Guzzle ConnectException exactly as Guzzle builds it: the message ends with
     * "for <full request URL>", and getRequest() carries the same URL.
     */
    private function guzzleConnectException(string $curlError, string $path): \GuzzleHttp\Exception\ConnectException
    {
        $url = 'https://api.webscraping.ai' . $path . '?url=https%3A%2F%2Fexample.com&api_key=test-key';
        $request = new \GuzzleHttp\Psr7\Request('GET', $url);

        return new \GuzzleHttp\Exception\ConnectException(
            "{$curlError} (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for {$url}",
            $request,
        );
    }

    public function testDataTransportErrorDoesNotLeakApiKey(): void
    {
        $this->http->addException($this->guzzleConnectException('cURL error 6: Could not resolve host: api.webscraping.ai', '/data'));

        try {
            $this->client->data(url: 'https://www.youtube.com/watch?v=x');
            self::fail('Expected ApiConnectionException');
        } catch (ApiConnectionException $exception) {
            self::assertStringContainsString('Could not resolve host', $exception->getMessage());
            self::assertStringContainsString('api_key=[REDACTED]', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            $this->assertNoApiKeyInChain($exception);
        }
    }

    /**
     * @return iterable<string, array{\Closure(Client): mixed, string}>
     */
    public static function everyEndpointProvider(): iterable
    {
        yield 'html' => [static fn (Client $c) => $c->html(url: 'https://example.com'), '/html'];
        yield 'text' => [static fn (Client $c) => $c->text(url: 'https://example.com'), '/text'];
        yield 'selected' => [static fn (Client $c) => $c->selected(url: 'https://example.com', selector: 'h1'), '/selected'];
        yield 'selectedMultiple' => [static fn (Client $c) => $c->selectedMultiple(url: 'https://example.com', selectors: ['h1']), '/selected-multiple'];
        yield 'question' => [static fn (Client $c) => $c->question(url: 'https://example.com', question: 'q'), '/ai/question'];
        yield 'fields' => [static fn (Client $c) => $c->fields(url: 'https://example.com', fields: ['t' => 'T']), '/ai/fields'];
        yield 'serp' => [static fn (Client $c) => $c->serp(q: 'coffee'), '/serp'];
        yield 'data' => [static fn (Client $c) => $c->data(url: 'https://example.com'), '/data'];
        yield 'account' => [static fn (Client $c) => $c->account(), '/account'];
    }

    /**
     * @param \Closure(Client): mixed $call
     */
    #[DataProvider('everyEndpointProvider')]
    public function testTransportErrorsNeverLeakApiKey(\Closure $call, string $path): void
    {
        $this->http->addException($this->guzzleConnectException('cURL error 6: Could not resolve host: api.webscraping.ai', $path));
        $this->http->addException($this->guzzleConnectException('cURL error 28: Operation timed out after 5001 milliseconds with 0 bytes received', $path));
        $this->http->addException(new \RuntimeException("stream_socket_client(): unable to connect for https://api.webscraping.ai{$path}?api_key=test-key"));

        foreach ([ApiConnectionException::class, ApiTimeoutException::class, ApiConnectionException::class] as $expected) {
            try {
                $call($this->client);
                self::fail("Expected {$expected}");
            } catch (\Throwable $exception) {
                self::assertInstanceOf($expected, $exception);
                self::assertStringContainsString('api_key=[REDACTED]', $exception->getMessage());
                $this->assertNoApiKeyInChain($exception);
            }
        }
    }

    public function testRealGuzzleUnresolvableHostDoesNotLeakApiKey(): void
    {
        $client = new Client(
            apiKey: 'test-key-real-guzzle',
            baseUrl: 'https://nonexistent-host-zzz.invalid',
            timeout: 5.0,
            connectTimeout: 5.0,
        );

        try {
            $client->data(url: 'https://www.youtube.com/watch?v=x');
            self::fail('Expected a transport exception');
        } catch (ApiConnectionException|ApiTimeoutException $exception) {
            self::assertStringContainsString('nonexistent-host-zzz.invalid', $exception->getMessage());
            for ($e = $exception; $e !== null; $e = $e->getPrevious()) {
                self::assertStringNotContainsString('test-key-real-guzzle', $e->getMessage());
                self::assertStringNotContainsString('test-key-real-guzzle', (string) $e);
            }
        }
    }

    private function assertNoApiKeyInChain(\Throwable $exception): void
    {
        for ($e = $exception; $e !== null; $e = $e->getPrevious()) {
            self::assertStringNotContainsString('test-key', $e->getMessage());
            self::assertStringNotContainsString('test-key', (string) $e);
        }
    }

    /**
     * @return iterable<string, array{\Closure(Client): mixed}>
     */
    public static function jsonEndpointProvider(): iterable
    {
        yield 'data' => [static fn (Client $c) => $c->data(url: 'https://www.youtube.com/watch?v=x')];
        yield 'serp' => [static fn (Client $c) => $c->serp(q: 'coffee')];
        yield 'fields' => [static fn (Client $c) => $c->fields(url: 'https://example.com', fields: ['title' => 'Title'])];
        yield 'account' => [static fn (Client $c) => $c->account()];
        yield 'selectedMultiple' => [static fn (Client $c) => $c->selectedMultiple(url: 'https://example.com', selectors: ['h1'])];
    }

    /**
     * @param \Closure(Client): mixed $call
     */
    #[DataProvider('jsonEndpointProvider')]
    public function testJsonEndpointsRaiseApiExceptionOnNonJsonSuccessBody(\Closure $call): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'text/html'], '<html>oops</html>'));

        try {
            $call($this->client);
            self::fail('Expected ApiException');
        } catch (ApiException $exception) {
            self::assertInstanceOf(\WebScrapingAI\Exception\WebScrapingAIException::class, $exception);
            self::assertSame(200, $exception->status);
            self::assertSame('<html>oops</html>', $exception->responseBody);
        }
    }

    public function testSerpErrorWithoutScrapingEnvelopeMapsToTypedException(): void
    {
        $this->http->addResponse(new Response(402, ['Content-Type' => 'application/json'], '{"message":"Not enough credits"}'));

        try {
            $this->client->serp(q: 'coffee');
            self::fail('Expected PaymentRequiredException');
        } catch (PaymentRequiredException $exception) {
            self::assertSame(402, $exception->status);
            self::assertSame('Not enough credits', $exception->getMessage());
            self::assertNull($exception->statusCode);
        }
    }

    public function testUserAgentHeaderIsSet(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'text/plain'], ''));

        $this->client->text(url: 'https://example.com');

        self::assertSame(
            'webscraping-ai-php/' . Client::VERSION,
            $this->lastRequest()->getHeaderLine('User-Agent'),
        );
    }

    public function testApiKeyTravelsInQueryString(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'text/plain'], ''));

        $this->client->html(url: 'https://example.com');

        self::assertSame('test-key', $this->parseQuery($this->lastRequest())['api_key']);
    }

    /**
     * @return iterable<string, array{int, class-string<ApiException>}>
     */
    public static function statusToExceptionProvider(): iterable
    {
        yield '400' => [400, BadRequestException::class];
        yield '402' => [402, PaymentRequiredException::class];
        yield '403' => [403, AuthenticationException::class];
        yield '429' => [429, RateLimitException::class];
        yield '500' => [500, ServerException::class];
        yield '504' => [504, GatewayTimeoutException::class];
    }

    /**
     * @param class-string<ApiException> $expectedClass
     */
    #[DataProvider('statusToExceptionProvider')]
    public function testErrorStatusesMapToTypedExceptions(int $status, string $expectedClass): void
    {
        $body = json_encode(['message' => 'Some error'], JSON_THROW_ON_ERROR);
        $this->http->addResponse(new Response($status, ['Content-Type' => 'application/json'], $body));

        try {
            $this->client->html(url: 'https://example.com');
            self::fail("Expected {$expectedClass} to be raised");
        } catch (ApiException $exception) {
            self::assertInstanceOf($expectedClass, $exception);
            self::assertSame($status, $exception->status);
            self::assertSame('Some error', $exception->getMessage());
        }
    }

    public function testServerExceptionExposesNestedEnvelope(): void
    {
        $body = json_encode([
            'message' => 'Unexpected HTTP code on the target page',
            'status_code' => 502,
            'status_message' => 'Bad Gateway',
            'body' => '<html>upstream</html>',
        ], JSON_THROW_ON_ERROR);
        $this->http->addResponse(new Response(500, ['Content-Type' => 'application/json'], $body));

        try {
            $this->client->html(url: 'https://example.com');
            self::fail('Expected ServerException');
        } catch (ServerException $exception) {
            self::assertSame(502, $exception->statusCode);
            self::assertSame('Bad Gateway', $exception->statusMessage);
            self::assertSame('<html>upstream</html>', $exception->body);
            self::assertNotNull($exception->responseBody);
        }
    }

    public function testNetworkTimeoutIsWrappedAsApiTimeoutException(): void
    {
        $request = (new Psr17Factory())->createRequest('GET', 'https://api.webscraping.ai/html');
        $this->http->addException(new class ('Connection timed out after 5 seconds', $request) extends \RuntimeException implements NetworkExceptionInterface {
            public function __construct(string $message, private RequestInterface $request)
            {
                parent::__construct($message);
            }

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        });

        $this->expectException(ApiTimeoutException::class);

        $this->client->html(url: 'https://example.com');
    }

    public function testNetworkErrorWithoutTimeoutWordingIsWrappedAsApiConnectionException(): void
    {
        $request = (new Psr17Factory())->createRequest('GET', 'https://api.webscraping.ai/html');
        $this->http->addException(new class ('Could not resolve host', $request) extends \RuntimeException implements NetworkExceptionInterface {
            public function __construct(string $message, private RequestInterface $request)
            {
                parent::__construct($message);
            }

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        });

        $this->expectException(ApiConnectionException::class);

        $this->client->html(url: 'https://example.com');
    }

    private function lastRequest(): RequestInterface
    {
        $requests = $this->http->getRequests();
        self::assertNotEmpty($requests, 'No request was captured by mock client');

        return $requests[array_key_last($requests)];
    }

    /**
     * @return array<string, string>
     */
    private function parseQuery(RequestInterface $request): array
    {
        $raw = $request->getUri()->getQuery();
        $params = [];
        foreach (explode('&', $raw) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $params[rawurldecode($k)] = rawurldecode($v);
        }

        return $params;
    }
}
