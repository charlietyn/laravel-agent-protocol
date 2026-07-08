<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Security\AgentGuard;

final readonly class BusinessDomainPolicy
{
    /**
     * @param array<int, string> $allowedModules
     * @param array<int, string> $allowedResources
     * @param array<int, string> $blockedResources
     * @param array<int, string> $blockedTopics
     * @param array<int, string> $allowedOperations
     */
    public function __construct(
        public bool $enabled = true,
        public string $mode = 'closed',
        public array $allowedModules = [],
        public array $allowedResources = [],
        public array $blockedResources = [],
        public array $blockedTopics = [],
        public array $allowedOperations = [],
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            enabled: (bool) ($config['enabled'] ?? true),
            mode: self::stringOrDefault($config['mode'] ?? null, 'closed'),
            allowedModules: self::stringList($config['allowed_modules'] ?? []),
            allowedResources: self::stringList($config['allowed_resources'] ?? []),
            blockedResources: self::stringList($config['blocked_resources'] ?? []),
            blockedTopics: self::stringList($config['blocked_topics'] ?? []),
            allowedOperations: self::stringList($config['allowed_operations'] ?? []),
        );
    }

    public function isClosedWorld(): bool
    {
        return $this->enabled && $this->mode === 'closed';
    }

    public function allowsModule(string $module): bool
    {
        return $this->allowedModules === [] || in_array($module, $this->allowedModules, true);
    }

    public function allowsResource(string $resource): bool
    {
        if (in_array($resource, $this->blockedResources, true)) {
            return false;
        }

        return $this->allowedResources === [] || in_array($resource, $this->allowedResources, true);
    }

    public function allowsOperation(string $operation): bool
    {
        return $this->allowedOperations === [] || in_array($operation, $this->allowedOperations, true);
    }

    public function blockedTopicIn(?string $text): ?string
    {
        if (! is_string($text) || $text === '') {
            return null;
        }

        $normalized = mb_strtolower($text);

        foreach ($this->blockedTopics as $topic) {
            if ($topic !== '' && str_contains($normalized, mb_strtolower($topic))) {
                return $topic;
            }
        }

        return null;
    }

    private static function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }
}
