<?php

declare(strict_types=1);

namespace WPClaw\Block;

/**
 * Registers the supported public block.
 */
final class Registration
{
    public function __construct(
        private readonly ReactRenderer $reactRenderer
    ) {
    }

    public function register(): void
    {
        $reactBlockPath = dirname(__DIR__, 2) . '/blocks/chat-react';

        register_block_type($reactBlockPath, [
            'render_callback' => fn (array $attributes = []): string => $this->reactRenderer->render($attributes),
        ]);
    }
}
