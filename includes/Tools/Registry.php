<?php

declare(strict_types=1);

namespace WPClaw\Tools;

use InvalidArgumentException;

/**
 * In memory registry for tool objects keyed by unique tool name.
 */
final class Registry
{
    /**
     * @var array<string, ToolInterface>
     */
    private array $tools = [];

    /**
     * @param array<int, ToolInterface> $tools
     */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(ToolInterface $tool): void
    {
        $name = $tool->get_name();
        if ($name === '') {
            throw new InvalidArgumentException('Tool name can not be empty.');
        }

        if (isset($this->tools[$name])) {
            throw new InvalidArgumentException("Tool '{$name}' is already registered.");
        }

        $this->tools[$name] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<int, ToolInterface>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * @param array<int, string>|null $enabledToolNames
     * @return array<int, array<string, mixed>>
     */
    public function provider_tool_schemas(?array $enabledToolNames = null): array
    {
        $enabledIndex = null;
        if ($enabledToolNames !== null) {
            $enabledIndex = array_fill_keys($enabledToolNames, true);
        }

        $schemas = [];
        foreach ($this->tools as $name => $tool) {
            if ($enabledIndex !== null && ! isset($enabledIndex[$name])) {
                continue;
            }

            $schemas[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool->get_name(),
                    'description' => $tool->get_description(),
                    'parameters' => $tool->get_schema(),
                ],
            ];
        }

        return $schemas;
    }
}
