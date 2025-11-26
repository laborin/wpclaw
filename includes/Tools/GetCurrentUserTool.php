<?php

declare(strict_types=1);

namespace WPNativeAgent\Tools;

use WPNativeAgent\Agent\Context;

/**
 * Built in tool that returns the authenticated user profile summary.
 */
final class GetCurrentUserTool extends AbstractTool
{
    /**
     * @var callable
     */
    private $getCurrentUser;

    public function __construct(
        ?SchemaValidator $schemaValidator = null,
        ?callable $capabilityChecker = null,
        ?callable $getCurrentUser = null
    ) {
        parent::__construct($schemaValidator, $capabilityChecker);
        $this->getCurrentUser = $getCurrentUser ?? static fn (): object => wp_get_current_user();
    }

    public function get_name(): string
    {
        return 'get_current_user';
    }

    public function get_description(): string
    {
        return 'Returns details about the currently authenticated user.';
    }

    public function get_schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => false,
        ];
    }

    public function get_required_capability(): string
    {
        return 'read';
    }

    public function execute(array $args, Context $context): ExecutionResult
    {
        if (! $this->user_can($this->get_required_capability())) {
            return ExecutionResult::denied('You do not have permission to access current user details.');
        }

        $validationError = $this->validate_arguments($args);
        if ($validationError !== null) {
            return $validationError;
        }

        try {
            $user = call_user_func($this->getCurrentUser);
        } catch (\Throwable $exception) {
            return ExecutionResult::error('Could not fetch current user: ' . $exception->getMessage(), 'tool_runtime_error');
        }

        $userId = (int) ($user->ID ?? 0);
        if ($userId < 1) {
            return ExecutionResult::error('Current user is not valid.', 'invalid_user');
        }

        return ExecutionResult::success([
            'id' => $userId,
            'login' => (string) ($user->user_login ?? ''),
            'display_name' => (string) ($user->display_name ?? ''),
            'email' => (string) ($user->user_email ?? ''),
            'roles' => is_array($user->roles ?? null) ? array_values($user->roles) : [],
            'context_user_id' => $context->user_id(),
        ]);
    }
}
