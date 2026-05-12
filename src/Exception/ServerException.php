<?php

declare(strict_types=1);

namespace WebScrapingAI\Exception;

/**
 * HTTP 500 — non-2xx HTTP status on the target page or an unexpected server error.
 *
 * When the upstream page failed, the envelope's status_code/status_message/body
 * fields carry the target page's response details.
 */
final class ServerException extends ApiException
{
}
