<?php

declare(strict_types=1);

namespace WPClaw\Security;

/**
 * Role based access gate for chat access and tool access.
 */
final class RoleGate
{
    /**
     * @var callable
     */
    private $currentUserResolver;

    /**
     * @var callable
     */
    private $userRolesResolver;

    /**
     * @var callable
     */
    private $optionGetter;

    public function __construct(
        ?callable $currentUserResolver = null,
        ?callable $userRolesResolver = null,
        ?callable $optionGetter = null
    ) {
        $this->currentUserResolver = $currentUserResolver ?? static fn (): int => (int) get_current_user_id();
        $this->userRolesResolver = $userRolesResolver ?? static function (int $userId): array {
            $user = get_userdata($userId);
            if (! is_object($user) || ! isset($user->roles) || ! is_array($user->roles)) {
                return [];
            }

            return array_values(array_filter($user->roles, static fn (mixed $role): bool => is_string($role) && $role !== ''));
        };
        $this->optionGetter = $optionGetter ?? static fn (string $name, mixed $default = null): mixed => get_option($name, $default);
    }

    public function can_chat_current_user(): bool
    {
        $userId = (int) call_user_func($this->currentUserResolver);
        if ($userId < 1) {
            return false;
        }

        return $this->can_chat_roles(call_user_func($this->userRolesResolver, $userId));
    }

    public function can_use_tools_current_user(): bool
    {
        $userId = (int) call_user_func($this->currentUserResolver);
        if ($userId < 1) {
            return false;
        }

        return $this->can_use_tools_roles(call_user_func($this->userRolesResolver, $userId));
    }

    /**
     * @param array<int, string> $roles
     */
    public function can_chat_roles(array $roles): bool
    {
        return $this->roles_intersect($roles, $this->allowed_chat_roles());
    }

    /**
     * @param array<int, string> $roles
     */
    public function can_use_tools_roles(array $roles): bool
    {
        return $this->roles_intersect($roles, $this->allowed_tool_roles());
    }

    /**
     * @return array<int, string>
     */
    public function allowed_chat_roles(): array
    {
        return $this->normalize_roles((call_user_func($this->optionGetter, 'wpclaw_allowed_chat_roles', ['administrator'])));
    }

    /**
     * @return array<int, string>
     */
    public function allowed_tool_roles(): array
    {
        return $this->normalize_roles((call_user_func($this->optionGetter, 'wpclaw_allowed_tool_roles', ['administrator'])));
    }

    /**
     * @param array<int, string> $roles
     * @param array<int, string> $allowed
     */
    private function roles_intersect(array $roles, array $allowed): bool
    {
        if ($allowed === []) {
            return false;
        }

        return array_intersect($roles, $allowed) !== [];
    }

    /**
     * @param mixed $roles
     * @return array<int, string>
     */
    private function normalize_roles(mixed $roles): array
    {
        if (! is_array($roles)) {
            return [];
        }

        $normalized = [];
        foreach ($roles as $role) {
            if (! is_string($role)) {
                continue;
            }

            $role = trim($role);
            if ($role === '') {
                continue;
            }

            $normalized[] = $role;
        }

        return array_values(array_unique($normalized));
    }
}
