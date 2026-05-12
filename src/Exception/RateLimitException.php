<?php

declare(strict_types=1);

namespace WebScrapingAI\Exception;

/** HTTP 429 — too many concurrent requests. */
final class RateLimitException extends ApiException
{
}
