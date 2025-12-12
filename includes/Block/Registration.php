<?php

declare(strict_types=1);

namespace WPClaw\Block;

/**
 * Registers both block variants and bind render callbacks.
 */
final class Registration
{
    public function __construct(
        private readonly ReactRenderer $reactRenderer,
        private readonly InteractivityRenderer $interactivityRenderer
    ) {
    }

    public function register(): void
    {
        $reactBlockPath = dirname(__DIR__, 2) . '/blocks/chat-react';
        $interactivityBlockPath = dirname(__DIR__, 2) . '/blocks/chat-interactivity';

        register_block_type($reactBlockPath, [
            'render_callback' => fn (array $attributes = []): string => $this->reactRenderer->render($attributes),
        ]);

        register_block_type($interactivityBlockPath, [
            'render_callback' => fn (array $attributes = []): string => $this->interactivityRenderer->render($attributes),
        ]);
    }
}
