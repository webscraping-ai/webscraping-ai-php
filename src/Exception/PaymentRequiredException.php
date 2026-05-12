<?php

declare(strict_types=1);

namespace WebScrapingAI\Exception;

/** HTTP 402 — billing issue, usually out of credits. */
final class PaymentRequiredException extends ApiException
{
}
