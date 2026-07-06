<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Security;

final class OperationRiskClassifier
{
    public const LOW = 'low';

    public const MEDIUM = 'medium';

    public const HIGH = 'high';

    public const CRITICAL = 'critical';

    public const VALID = [
        self::LOW,
        self::MEDIUM,
        self::HIGH,
        self::CRITICAL,
    ];

    public function classify(string $scenario, string $method = 'GET'): string
    {
        $scenario = strtolower($scenario);
        $method = strtoupper($method);

        if (str_contains($scenario, 'force_delete')) {
            return self::CRITICAL;
        }

        if (str_contains($scenario, 'password') || str_contains($scenario, 'permission_global')) {
            return self::CRITICAL;
        }

        if (
            str_contains($scenario, 'bulk')
            || str_contains($scenario, 'multiple')
            || str_contains($scenario, 'assign_role')
            || str_contains($scenario, 'assign_user')
            || in_array($scenario, ['delete', 'destroy', 'delete_by_id', 'restore', 'restore_multiple'], true)
        ) {
            return self::HIGH;
        }

        if (
            str_contains($scenario, 'create')
            || str_contains($scenario, 'update')
            || str_starts_with($scenario, 'export_')
            || in_array($method, ['POST', 'PUT', 'PATCH'], true)
        ) {
            return self::MEDIUM;
        }

        return self::LOW;
    }

    public function normalize(mixed $risk, string $fallback = self::MEDIUM): string
    {
        if (is_string($risk) && in_array($risk, self::VALID, true)) {
            return $risk;
        }

        return in_array($fallback, self::VALID, true) ? $fallback : self::MEDIUM;
    }

    /**
     * @param  array<int, string>  $confirmationRequiredFor
     */
    public function requiresConfirmation(string $risk, array $confirmationRequiredFor = [self::HIGH, self::CRITICAL]): bool
    {
        return in_array($risk, $confirmationRequiredFor, true);
    }
}
