<?php

declare(strict_types=1);

namespace WebScrapingAI\Tests\Internal;

use PHPUnit\Framework\TestCase;
use WebScrapingAI\Internal\QueryEncoder;

final class QueryEncoderTest extends TestCase
{
    public function testEncodesFlatScalars(): void
    {
        $encoded = QueryEncoder::encode([
            'url' => 'https://example.com',
            'timeout' => 5000,
        ]);

        self::assertSame('url=https%3A%2F%2Fexample.com&timeout=5000', $encoded);
    }

    public function testSerializesBooleansAsTrueAndFalseStrings(): void
    {
        $encoded = QueryEncoder::encode([
            'js' => true,
            'error_on_404' => false,
        ]);

        self::assertSame('js=true&error_on_404=false', $encoded);
    }

    public function testEncodesSpacesAsPercent20(): void
    {
        $encoded = QueryEncoder::encode(['question' => 'What is this?']);

        self::assertSame('question=What%20is%20this%3F', $encoded);
    }

    public function testEncodesAssociativeArrayAsDeepObject(): void
    {
        $encoded = QueryEncoder::encode([
            'headers' => ['Cookie' => 'session=abc', 'X-Custom' => 'v'],
        ]);

        self::assertSame('headers%5BCookie%5D=session%3Dabc&headers%5BX-Custom%5D=v', $encoded);
    }

    public function testEncodesListArrayAsFormExplodeWithoutBrackets(): void
    {
        $encoded = QueryEncoder::encode([
            'selectors' => ['h1', '.price', 'div > a'],
        ]);

        self::assertSame('selectors=h1&selectors=.price&selectors=div%20%3E%20a', $encoded);
    }

    public function testDropsTopLevelNullValues(): void
    {
        $encoded = QueryEncoder::encode([
            'url' => 'https://example.com',
            'js' => null,
            'timeout' => null,
        ]);

        self::assertSame('url=https%3A%2F%2Fexample.com', $encoded);
    }

    public function testDropsNullValuesInsideAssociativeArrays(): void
    {
        $encoded = QueryEncoder::encode([
            'headers' => ['Cookie' => 'session=abc', 'X-Empty' => null],
        ]);

        self::assertSame('headers%5BCookie%5D=session%3Dabc', $encoded);
    }

    public function testDropsNullValuesInsideListArrays(): void
    {
        $encoded = QueryEncoder::encode([
            'selectors' => ['h1', null, '.price'],
        ]);

        self::assertSame('selectors=h1&selectors=.price', $encoded);
    }

    public function testEmptyArrayProducesEmptyString(): void
    {
        self::assertSame('', QueryEncoder::encode([]));
        self::assertSame('', QueryEncoder::encode(['x' => null]));
    }

    public function testThrowsOnUnsupportedScalarType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        QueryEncoder::encode(['x' => new \stdClass()]);
    }
}
