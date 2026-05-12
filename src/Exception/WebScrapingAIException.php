<?php

declare(strict_types=1);

namespace WebScrapingAI\Exception;

/**
 * Marker interface implemented by every exception thrown by the SDK.
 *
 * Catch this to handle any SDK-originated failure regardless of whether it
 * came from the transport layer or the API.
 */
interface WebScrapingAIException
{
}
