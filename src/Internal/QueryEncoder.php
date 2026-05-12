<?php

declare(strict_types=1);

namespace WebScrapingAI\Internal;

/**
 * Encodes query parameters for the WebScraping.AI API.
 *
 * The OpenAPI spec mixes three encoding styles that no off-the-shelf encoder
 * gets right in combination:
 *
 *   1. Associative arrays (`headers`, `fields`)  →  deepObject + explode
 *      Output: `headers[Cookie]=foo&fields[title]=bar`
 *   2. List arrays         (`selectors`)         →  form + explode, **no `[]`**
 *      Output: `selectors=h1&selectors=.price`
 *   3. Everything else                            →  flat `key=value`
 *      Booleans serialize to the strings `"true"` / `"false"`.
 *
 * `null` values are dropped at every level. Spaces encode as `%20` (not `+`)
 * to match RFC 3986 — what most servers and proxies expect on the wire.
 *
 * @internal Public only so unit tests can reach it.
 */
final class QueryEncoder
{
    /**
     * @param array<string, mixed> $params Parameter map. List arrays expand without brackets;
     *                                     associative arrays expand as deepObject; scalars are flat.
     * @return string URL-encoded query string with no leading `?`. Empty when all params are null.
     */
    public static function encode(array $params): string
    {
        $pairs = [];

        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                if (self::isList($value)) {
                    foreach ($value as $item) {
                        if ($item === null) {
                            continue;
                        }
                        $pairs[] = self::pair($key, $item);
                    }
                } else {
                    foreach ($value as $subKey => $subValue) {
                        if ($subValue === null) {
                            continue;
                        }
                        $pairs[] = self::pair("{$key}[{$subKey}]", $subValue);
                    }
                }

                continue;
            }

            $pairs[] = self::pair($key, $value);
        }

        return implode('&', $pairs);
    }

    private static function pair(string $key, mixed $value): string
    {
        return rawurlencode($key) . '=' . rawurlencode(self::scalar($value));
    }

    private static function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        throw new \InvalidArgumentException(
            'Cannot encode value of type ' . get_debug_type($value) . ' as a query string scalar'
        );
    }

    /**
     * @param array<int|string, mixed> $array
     */
    private static function isList(array $array): bool
    {
        return array_is_list($array);
    }
}
