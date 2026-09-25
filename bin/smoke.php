<?php

declare(strict_types=1);

/**
 * Hand-run smoke test against the live API. Not part of the test suite.
 *
 * Costs ~46 credits per full sweep: account (free), 4 page calls
 * (html/text/selected/selected_multiple) with js=false + datacenter proxy at
 * 1 credit each, question + fields at 6 each (datacenter, no JS), one SERP
 * search at 15, one /data call at 15, and one /data call on an unsupported
 * URL that the server must reject with a free 400 → 4 + 12 + 15 + 15 = 46.
 *
 * Each case asserts on the shape of the result, not just the absence of an
 * exception; any failure prints a FAIL line and the script exits 1.
 *
 * Usage:
 *   WEBSCRAPING_AI_KEY=... php bin/smoke.php
 */

require __DIR__ . '/../vendor/autoload.php';

use WebScrapingAI\Client;
use WebScrapingAI\Exception\BadRequestException;

$apiKey = getenv('WEBSCRAPING_AI_KEY');
if (!is_string($apiKey) || $apiKey === '') {
    fwrite(STDERR, "WEBSCRAPING_AI_KEY env var is required\n");
    exit(2);
}

$client = new Client(apiKey: $apiKey);

$target = 'https://example.com';

/**
 * @param mixed $value
 */
function nonEmpty(mixed $value): bool
{
    if (is_string($value)) {
        return trim($value) !== '';
    }

    return is_array($value) && $value !== [];
}

// Each case returns [result, failure reason or null].
$cases = [
    'account' => function () use ($client) {
        $r = $client->account();

        return [$r, nonEmpty($r) ? null : 'empty account response'];
    },
    'html' => function () use ($client, $target) {
        $r = $client->html(url: $target, js: false, proxy: 'datacenter');

        return [$r, nonEmpty($r) ? null : 'empty result'];
    },
    'text' => function () use ($client, $target) {
        $r = $client->text(url: $target, js: false, proxy: 'datacenter');

        return [$r, nonEmpty($r) ? null : 'empty result'];
    },
    'selected' => function () use ($client, $target) {
        $r = $client->selected(url: $target, selector: 'h1', js: false, proxy: 'datacenter');

        return [$r, nonEmpty($r) ? null : 'empty result'];
    },
    'selected_multiple' => function () use ($client, $target) {
        $r = $client->selectedMultiple(url: $target, selectors: ['h1', 'p'], js: false, proxy: 'datacenter');
        $anyMatch = false;
        foreach ($r as $inner) {
            if (nonEmpty($inner)) {
                $anyMatch = true;
                break;
            }
        }

        return [$r, $anyMatch ? null : 'every inner array is empty (selectors mis-encoded?)'];
    },
    'question' => function () use ($client, $target) {
        $r = $client->question(url: $target, question: 'What is this page about? Answer in one sentence.', js: false, proxy: 'datacenter');

        return [$r, nonEmpty($r) ? null : 'empty result'];
    },
    'fields' => function () use ($client, $target) {
        $r = $client->fields(url: $target, fields: ['title' => 'Page title', 'description' => 'Short description'], js: false, proxy: 'datacenter');

        return [$r, array_key_exists('result', $r) ? null : 'missing "result" key'];
    },
    'serp' => function () use ($client) {
        $r = $client->serp(q: 'coffee machines');
        if (!nonEmpty($r['organic_results'] ?? null)) {
            return [$r, 'organic_results is missing or empty'];
        }
        $q = $r['search_parameters']['q'] ?? null;
        if ($q !== 'coffee machines') {
            return [$r, 'search_parameters.q is ' . var_export($q, true) . ', expected "coffee machines"'];
        }

        return [$r, null];
    },
    'data' => function () use ($client) {
        $r = $client->data(url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        if (($r['parse_status'] ?? null) !== 'ok') {
            return [$r, 'parse_status is ' . var_export($r['parse_status'] ?? null, true) . ', expected "ok"'];
        }
        $provider = $r['request_parameters']['provider'] ?? null;
        if ($provider !== 'youtube') {
            return [$r, 'request_parameters.provider is ' . var_export($provider, true) . ', expected "youtube"'];
        }
        if (!is_array($r['data'] ?? null) || !nonEmpty($r['data']['title'] ?? null)) {
            return [$r, 'data is null or data.title is missing/empty'];
        }

        return [$r, null];
    },
    // Proves there is no client-side site filter: the *server* must reject it (400, not charged).
    'data_unsupported' => function () use ($client) {
        try {
            $r = $client->data(url: 'https://example.com/');
        } catch (BadRequestException $e) {
            if ($e->status !== 400) {
                return ['HTTP ' . $e->status . ' ' . $e->getMessage(), "status {$e->status}, expected 400"];
            }
            if (!str_contains($e->getMessage(), 'Unsupported URL')) {
                return ['400 ' . $e->getMessage(), 'message does not contain "Unsupported URL"'];
            }

            return ['400 ' . $e->getMessage(), null];
        }

        return [$r, 'expected a 400 BadRequestException from the server, got a 200'];
    },
];

$redact = static function (string $message) use ($apiKey): string {
    $message = str_replace($apiKey, '[REDACTED]', $message);

    return (string) preg_replace('/api_key=[^&\s"\']*/i', 'api_key=[REDACTED]', $message);
};

$preview = static function (mixed $result): string {
    $text = is_string($result) ? $result : (string) json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));

    return mb_substr($text, 0, 120);
};

$failures = 0;
foreach ($cases as $name => $call) {
    try {
        [$result, $reason] = $call();
        if ($reason === null) {
            printf("  ok   %-18s  %s\n", $name, $preview($result));
        } else {
            ++$failures;
            printf("  FAIL %-18s  %s: %s\n", $name, $redact($reason), $redact($preview($result)));
        }
    } catch (\Throwable $e) {
        ++$failures;
        printf("  FAIL %-18s  %s: %s\n", $name, $e::class, $redact($e->getMessage()));
    }
}

exit($failures === 0 ? 0 : 1);
