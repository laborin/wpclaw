<?php

declare(strict_types=1);

namespace WPNativeAgent\Provider;

use InvalidArgumentException;

/**
 * Factory that creates provider implementations from plugin options.
 */
final class ProviderFactory
{
    /**
     * @param array<string, mixed> $options
     */
    public function make(string $providerId, array $options): ProviderInterface
    {
        if ($providerId !== 'openrouter') {
            throw new InvalidArgumentException("Unsupported provider '{$providerId}'.");
        }

        $apiKey = isset($options['api_key']) ? (string) $options['api_key'] : '';
        $endpoint = isset($options['endpoint']) && is_string($options['endpoint'])
            ? $options['endpoint']
            : 'https://openrouter.ai/api/v1/chat/completions';

        $contextWindows = [];
        if (isset($options['context_windows']) && is_array($options['context_windows'])) {
            foreach ($options['context_windows'] as $model => $value) {
                if (! is_string($model) || ! is_numeric($value)) {
                    continue;
                }

                $window = (int) $value;
                if ($window > 0) {
                    $contextWindows[$model] = $window;
                }
            }
        }

        return new OpenRouterProvider($apiKey, $endpoint, null, null, $contextWindows);
    }
}
