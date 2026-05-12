<?php

declare(strict_types=1);

namespace WebScrapingAI\Exception;

use RuntimeException;

/**
 * Raised when the API returns a non-2xx HTTP status.
 *
 * Exposes the parsed error envelope when the API supplies one (target-page
 * errors arrive as 500s with status_code/status_message/body populated).
 */
class ApiException extends RuntimeException implements WebScrapingAIException
{
    /**
     * @param string|null $statusMessage Target page HTTP status text (populated for 500s when present)
     * @param string|null $body          Target page response body (populated for 500s when present)
     * @param string|null $responseBody  Raw response body from the WebScraping.AI API
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?int $statusCode = null,
        public readonly ?string $statusMessage = null,
        public readonly ?string $body = null,
        public readonly ?string $responseBody = null,
    ) {
        parent::__construct($message, $status);
    }
}
