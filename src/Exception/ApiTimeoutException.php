<?php

declare(strict_types=1);

namespace WebScrapingAI\Exception;

use RuntimeException;

/**
 * Raised when the underlying PSR-18 HTTP client times out before the API responded.
 *
 * Prefixed `Api*` so the name doesn't collide with hypothetical global TimeoutException.
 */
final class ApiTimeoutException extends RuntimeException implements WebScrapingAIException
{
}
