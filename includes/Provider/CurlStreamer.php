<?php

declare(strict_types=1);

namespace WPNativeAgent\Provider;

use RuntimeException;

/**
 * cURL based streaming transport used by providers for SSE responses.
 */
class CurlStreamer
{
    /**
     * @param array<int, string> $headers
     * @return array<int, string>
     */
    public function stream(string $url, array $headers, string $body, ?callable $shouldCancel = null): array
    {
        if (! function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required for streaming requests.');
        }

        $chunks = [];
        $cancelled = false;

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Could not initialize cURL request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_WRITEFUNCTION => static function ($resource, string $chunk) use (&$chunks, $shouldCancel, &$cancelled): int {
                if ($shouldCancel !== null && (bool) call_user_func($shouldCancel) === true) {
                    $cancelled = true;

                    return 0;
                }

                $chunks[] = $chunk;

                return strlen($chunk);
            },
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $ok = curl_exec($handle);
        $error = curl_error($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        if ($cancelled) {
            return $chunks;
        }

        if ($ok === false) {
            throw new RuntimeException('cURL streaming failed: ' . $error);
        }

        if ($code >= 400) {
            throw new RuntimeException('Provider returned HTTP ' . $code . '.');
        }

        return $chunks;
    }
}
