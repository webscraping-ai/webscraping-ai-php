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
