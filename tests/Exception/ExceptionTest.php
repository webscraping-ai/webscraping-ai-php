<?php

declare(strict_types=1);

namespace WebScrapingAI\Tests\Exception;

use PHPUnit\Framework\TestCase;
use WebScrapingAI\Exception\ApiConnectionException;
use WebScrapingAI\Exception\ApiException;
use WebScrapingAI\Exception\ApiTimeoutException;
use WebScrapingAI\Exception\AuthenticationException;
use WebScrapingAI\Exception\BadRequestException;
use WebScrapingAI\Exception\GatewayTimeoutException;
use WebScrapingAI\Exception\PaymentRequiredException;
use WebScrapingAI\Exception\RateLimitException;
use WebScrapingAI\Exception\ServerException;
use WebScrapingAI\Exception\WebScrapingAIException;

final class ExceptionTest extends TestCase
{
    public function testApiExceptionExposesFullEnvelope(): void
    {
        $exception = new ApiException(
            message: 'Unexpected HTTP code on the target page',
            status: 500,
            statusCode: 502,
            statusMessage: 'Bad Gateway',
            body: '<html>boom</html>',
            responseBody: '{"message":"Unexpected HTTP code on the target page","status_code":502}',
        );

        self::assertSame('Unexpected HTTP code on the target page', $exception->getMessage());
        self::assertSame(500, $exception->status);
        self::assertSame(502, $exception->statusCode);
        self::assertSame('Bad Gateway', $exception->statusMessage);
        self::assertSame('<html>boom</html>', $exception->body);
        self::assertNotNull($exception->responseBody);
    }

    /**
     * @return iterable<string, array{int, class-string<ApiException>}>
     */
    public static function documentedStatusProvider(): iterable
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
    #[\PHPUnit\Framework\Attributes\DataProvider('documentedStatusProvider')]
    public function testEachDocumentedStatusHasOwnSubclass(int $status, string $expectedClass): void
    {
        $exception = new $expectedClass(message: 'msg', status: $status);

        self::assertInstanceOf(ApiException::class, $exception);
        self::assertInstanceOf(WebScrapingAIException::class, $exception);
        self::assertSame($status, $exception->status);
    }

    public function testTransportExceptionsImplementMarkerInterface(): void
    {
        self::assertInstanceOf(WebScrapingAIException::class, new ApiTimeoutException('timeout'));
        self::assertInstanceOf(WebScrapingAIException::class, new ApiConnectionException('connection refused'));
    }
}
