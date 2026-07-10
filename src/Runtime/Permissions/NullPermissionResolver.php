<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Permissions;

final readonly class NullPermissionResolver implements PermissionResolver
{
    /**
     * @return array<int, string>
     */
    public function permissionsFor(mixed $user): array
    {
        return [];
    }
}
