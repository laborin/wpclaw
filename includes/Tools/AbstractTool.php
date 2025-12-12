<?php

declare(strict_types=1);

namespace WPClaw\Tools;

/**
 * Thin base class with schema validation helpers for built in tools.
 */
abstract class AbstractTool implements ToolInterface
{
    private SchemaValidator $schemaValidator;

    /**
     * @var callable
     */
    private $capabilityChecker;

    public function __construct(?SchemaValidator $schemaValidator = null, ?callable $capabilityChecker = null)
    {
        $this->schemaValidator = $schemaValidator ?? new SchemaValidator();
        $this->capabilityChecker = $capabilityChecker ?? static fn (string $capability): bool => current_user_can($capability);
    }

    protected function validate_arguments(array $args): ?ExecutionResult
    {
        $errors = $this->schemaValidator->validate($this->get_schema(), $args);
        if ($errors === []) {
            return null;
        }

        return ExecutionResult::error(
            'Invalid tool arguments: ' . implode('; ', $errors),
            'invalid_arguments'
        );
    }

    protected function user_can(string $capability): bool
    {
        return (bool) call_user_func($this->capabilityChecker, $capability);
    }

    protected function schema_validator(): SchemaValidator
    {
        return $this->schemaValidator;
    }
}
