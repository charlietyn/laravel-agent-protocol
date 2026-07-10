<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Permissions;

use Illuminate\Support\Collection;

final readonly class SpatiePermissionResolver implements PermissionResolver
{
    /**
     * @return array<int, string>
     */
    public function permissionsFor(mixed $user): array
    {
        if (! is_object($user) || ! method_exists($user, 'getAllPermissions')) {
            return [];
        }

        $permissions = $user->getAllPermissions();

        if ($permissions instanceof Collection) {
            return $permissions
                ->pluck('name')
                ->filter(static fn (mixed $permission): bool => is_string($permission) && $permission !== '')
                ->values()
                ->all();
        }

        if (! is_iterable($permissions)) {
            return [];
        }

        $resolved = [];
        foreach ($permissions as $permission) {
            $name = is_object($permission) && isset($permission->name) ? $permission->name : $permission;
            if (is_string($name) && $name !== '') {
                $resolved[] = $name;
            }
        }

        return array_values(array_unique($resolved));
    }
}
