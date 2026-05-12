<?php

declare(strict_types=1);

namespace WebScrapingAI\Exception;

/** HTTP 504 — target page took too long. Increase the `timeout` parameter and retry. */
final class GatewayTimeoutException extends ApiException
{
}
