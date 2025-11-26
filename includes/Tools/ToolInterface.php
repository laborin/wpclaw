<?php

declare(strict_types=1);

namespace WPNativeAgent\Tools;

use WPNativeAgent\Agent\Context;

/**
 * Tool interface contract consumed by the server side loop.
 */
interface ToolInterface
{
    public function get_name(): string;

    public function get_description(): string;

    public function get_schema(): array;

    public function get_required_capability(): string;

    public function execute(array $args, Context $context): ExecutionResult;
}
