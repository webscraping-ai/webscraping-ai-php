<?php

declare(strict_types=1);

namespace WebScrapingAI\Exception;

use RuntimeException;

/**
 * Raised when the underlying PSR-18 HTTP client couldn't reach the API
 * (DNS failure, refused connection, TLS handshake error, etc.).
 */
final class ApiConnectionException extends RuntimeException implements WebScrapingAIException
{
}
