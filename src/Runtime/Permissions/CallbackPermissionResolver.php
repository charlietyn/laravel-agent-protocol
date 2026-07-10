<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Permissions;

final readonly class CallbackPermissionResolver implements PermissionResolver
{
    public function __construct(
        private mixed $callback,
    ) {}

    /**
     * @return array<int, string>
     */
    public function permissionsFor(mixed $user): array
    {
        if (! is_callable($this->callback)) {
            return [];
        }

        $permissions = ($this->callback)($user);
        if (! is_array($permissions)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $permission): mixed => is_string($permission) ? trim($permission) : $permission, $permissions),
            static fn (mixed $permission): bool => is_string($permission) && $permission !== '',
        ));
    }
}
