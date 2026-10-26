<?php

declare(strict_types=1);

/**
 * Hand-run smoke test against the live API. Not part of the test suite.
 *
 * Uses real credits (see https://webscraping.ai/docs for pricing): account
 * and serp_locations (free), 4 page calls (html/text/selected/selected_multiple)
 * and question + fields with js=false + datacenter proxy to keep the cost down,
 * one one-page SERP search, one two-page located SERP search (billed per page
 * returned) and one /data call. A second /data call on example.com (a site
 * without a dedicated parser) is not charged when learning its structure
 * outlasts the request (503), when nothing is found (422) or when extracting
 * the data or loading the page runs out of time (a 500 timeout); otherwise it
 * is a normal, charged success.
 *
 * Each case asserts on the shape of the result, not just the absence of an
 * exception; any failure prints a FAIL line and the script exits 1.
 *
 * Usage:
 *   WEBSCRAPING_AI_KEY=... php bin/smoke.php
 */

require __DIR__ . '/../vendor/autoload.php';

use WebScrapingAI\Client;
use WebScrapingAI\Exception\ApiException;

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
    'serp_location' => function () use ($client) {
        $r = $client->serp(q: 'coffee machines', location: 'Austin, TX', pages: 2);
        if (!nonEmpty($r['organic_results'] ?? null)) {
            return [$r, 'organic_results is missing or empty'];
        }
        $used = $r['search_parameters']['location_used'] ?? null;
        if (!is_string($used) || !str_contains($used, 'Austin')) {
            return [$r, 'search_parameters.location_used is ' . var_export($used, true) . ', expected it to contain "Austin"'];
        }

        return [$r, null];
    },
    'serp_locations' => function () use ($client) {
        $r = $client->serpLocations(q: 'austin', limit: 3);
        if (!nonEmpty($r)) {
            return [$r, 'empty location list'];
        }
        $first = $r[0] ?? null;
        $name = is_array($first) ? ($first['canonical_name'] ?? null) : null;
        if (!is_string($name) || !str_contains($name, 'Austin')) {
            return [$r, 'first canonical_name is ' . var_export($name, true) . ', expected it to contain "Austin"'];
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
    // /data works on any public page: accept data, a 503 structure_processing when
    // learning the structure outlasts the request, a 422 structure_unavailable, or a
    // 500 timeout when extracting the data or loading the page runs out of time.
    'data_any_site' => function () use ($client) {
        try {
            $r = $client->data(url: 'https://example.com/');
        } catch (ApiException $e) {
            $decoded = json_decode($e->responseBody ?? '', true);
            $errorCode = is_array($decoded) ? ($decoded['error_code'] ?? null) : null;
            $expected = [503 => 'structure_processing', 422 => 'structure_unavailable', 500 => 'timeout'][$e->status] ?? null;
            if ($expected === null) {
                return ['HTTP ' . $e->status . ' ' . $e->getMessage(), "status {$e->status}, expected data, 503, 422 or 500"];
            }
            if ($errorCode !== $expected) {
                return ["{$e->status} " . $e->getMessage(), 'error_code is ' . var_export($errorCode, true) . ", expected \"{$expected}\""];
            }

            return ["{$e->status} {$errorCode}: " . $e->getMessage(), null];
        }
        if (!is_array($r['request_parameters'] ?? null)) {
            return [$r, 'expected request_parameters, a 503 structure_processing, a 422 structure_unavailable or a 500 timeout'];
        }

        return [$r, null];
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
