<?php

declare(strict_types=1);

namespace WPClaw\Provider;

use RuntimeException;

/**
 * cURL based streaming transport used by providers for SSE responses.
 */
class CurlStreamer
{
    /**
     * @param array<int, string> $headers
     * @return iterable<int, string>
     */
    public function stream(string $url, array $headers, string $body, ?callable $shouldCancel = null): iterable
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

        $multi = curl_multi_init();
        if ($multi === false) {
            curl_close($handle);
            throw new RuntimeException('Could not initialize cURL multi request.');
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

        curl_multi_add_handle($multi, $handle);

        $active = null;
        $status = CURLM_OK;

        do {
            if ($shouldCancel !== null && (bool) call_user_func($shouldCancel) === true) {
                $cancelled = true;
                break;
            }

            do {
                $status = curl_multi_exec($multi, $active);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            while ($chunks !== []) {
                yield array_shift($chunks);
            }

            if ($active && $status === CURLM_OK) {
                $selected = curl_multi_select($multi, 0.1);
                if ($selected === -1) {
                    usleep(100000);
                }
            }
        } while ($active && $status === CURLM_OK);

        while ($chunks !== []) {
            yield array_shift($chunks);
        }

        $error = curl_error($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_multi_remove_handle($multi, $handle);
        curl_multi_close($multi);
        curl_close($handle);

        if ($cancelled) {
            return;
        }

        if ($status !== CURLM_OK || $error !== '') {
            throw new RuntimeException('cURL streaming failed: ' . $error);
        }

        if ($code >= 400) {
            throw new RuntimeException('Provider returned HTTP ' . $code . '.');
        }
    }
}
