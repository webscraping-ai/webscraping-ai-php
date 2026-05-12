<?php

declare(strict_types=1);

/**
 * Hand-run smoke test against the live API. Not part of the test suite —
 * costs ~17 credits per full sweep.
 *
 * Usage:
 *   WEBSCRAPING_AI_KEY=... php bin/smoke.php
 */

require __DIR__ . '/../vendor/autoload.php';

use WebScrapingAI\Client;
use WebScrapingAI\Exception\WebScrapingAIException;

$apiKey = getenv('WEBSCRAPING_AI_KEY');
if (!is_string($apiKey) || $apiKey === '') {
    fwrite(STDERR, "WEBSCRAPING_AI_KEY env var is required\n");
    exit(2);
}

$client = new Client(apiKey: $apiKey);

$target = 'https://example.com';

$cases = [
    'account'           => fn () => $client->account(),
    'html'              => fn () => $client->html(url: $target),
    'text'              => fn () => $client->text(url: $target),
    'selected'          => fn () => $client->selected(url: $target, selector: 'h1'),
    'selected_multiple' => fn () => $client->selectedMultiple(url: $target, selectors: ['h1', 'p']),
    'question'          => fn () => $client->question(url: $target, question: 'What is this page about? Answer in one sentence.'),
    'fields'            => fn () => $client->fields(url: $target, fields: ['title' => 'Page title', 'description' => 'Short description']),
];

$failures = 0;
foreach ($cases as $name => $call) {
    try {
        $result = $call();
        $preview = is_array($result) ? json_encode($result, JSON_UNESCAPED_SLASHES) : substr($result, 0, 120);
        printf("  ok  %-18s  %s%s\n", $name, is_string($preview) ? substr($preview, 0, 120) : '<encoded>', "\n");
    } catch (WebScrapingAIException $e) {
        ++$failures;
        printf("  FAIL %-18s  %s: %s\n", $name, $e::class, $e->getMessage());
    }
}

exit($failures === 0 ? 0 : 1);
