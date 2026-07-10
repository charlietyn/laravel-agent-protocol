<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Permissions;

interface PermissionResolver
{
    /**
     * @return array<int, string>
     */
    public function permissionsFor(mixed $user): array;
}
