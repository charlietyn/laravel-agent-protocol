<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Support;

use Illuminate\Support\Str;

final class ResourceKey
{
    public static function fromModel(?string $module, ?string $modelClass, ?string $fallback = null): string
    {
        $model = $fallback;

        if ($modelClass && class_exists($modelClass)) {
            if (defined($modelClass.'::MODEL') && constant($modelClass.'::MODEL') !== '') {
                $model = self::constantString($modelClass.'::MODEL');
            } else {
                $model = Str::of(class_basename($modelClass))->snake()->replace('_', '-')->toString();
            }
        }

        $model ??= 'resource';
        $module = $module ?: '--site--';

        return $module === '--site--' ? $model : "{$module}.{$model}";
    }

    public static function nameFromModel(?string $modelClass, ?string $fallback = null): string
    {
        if ($modelClass && class_exists($modelClass)) {
            if (defined($modelClass.'::MODEL') && constant($modelClass.'::MODEL') !== '') {
                return self::constantString($modelClass.'::MODEL') ?? ($fallback ?? 'resource');
            }

            return Str::of(class_basename($modelClass))->snake()->replace('_', '-')->toString();
        }

        return $fallback ?? 'resource';
    }

    private static function constantString(string $constant): ?string
    {
        $value = constant($constant);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
