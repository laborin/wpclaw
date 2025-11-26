<?php

declare(strict_types=1);

namespace WPNativeAgent\Provider;

use WPNativeAgent\Security\CancelSignal;

/**
 * Interface for providers that stream normalized completion events.
 */
interface ProviderInterface
{
    public function get_id(): string;

    public function stream_completion(
        array $messages,
        array $tool_schemas,
        string $model,
        ?CancelSignal $cancel = null
    ): iterable;

    public function estimate_tokens(string $text): int;

    public function get_context_window(string $model): int;
}
