<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\InputGuard;

final readonly class InputTextPolicy
{
    /**
     * @param array<int, string> $promptInjectionPatterns
     * @param array<int, string> $sensitivePatterns
     */
    public function __construct(
        public bool $enabled = true,
        public string $mode = 'reject',
        public int $maxChars = 12000,
        public int $maxBytes = 48000,
        public int $maxLines = 300,
        public int $maxRepeatedCharRun = 120,
        public bool $trim = true,
        public bool $collapseControlChars = true,
        public bool $detectPromptInjection = true,
        public bool $detectSecrets = true,
        public bool $denyBinaryContent = true,
        public array $promptInjectionPatterns = [],
        public array $sensitivePatterns = [],
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        $limits = isset($config['limits']) && is_array($config['limits']) ? $config['limits'] : [];
        $normalize = isset($config['normalize']) && is_array($config['normalize']) ? $config['normalize'] : [];
        $security = isset($config['security']) && is_array($config['security']) ? $config['security'] : [];

        return new self(
            enabled: (bool) ($config['enabled'] ?? true),
            mode: self::mode((string) ($config['mode'] ?? 'reject')),
            maxChars: self::positiveInt($limits['max_chars'] ?? 12000, 12000),
            maxBytes: self::positiveInt($limits['max_bytes'] ?? 48000, 48000),
            maxLines: self::positiveInt($limits['max_lines'] ?? 300, 300),
            maxRepeatedCharRun: self::positiveInt($limits['max_repeated_char_run'] ?? 120, 120),
            trim: (bool) ($normalize['trim'] ?? true),
            collapseControlChars: (bool) ($normalize['collapse_control_chars'] ?? true),
            detectPromptInjection: (bool) ($security['detect_prompt_injection'] ?? true),
            detectSecrets: (bool) ($security['detect_secrets'] ?? true),
            denyBinaryContent: (bool) ($security['deny_binary_content'] ?? true),
            promptInjectionPatterns: self::stringList($security['prompt_injection_patterns'] ?? []),
            sensitivePatterns: self::stringList($security['sensitive_patterns'] ?? []),
        );
    }

    public function shouldReject(): bool
    {
        return $this->mode === 'reject';
    }

    public function shouldWarn(): bool
    {
        return $this->mode === 'warn';
    }

    public function shouldTruncate(): bool
    {
        return $this->mode === 'truncate';
    }

    private static function mode(string $mode): string
    {
        return in_array($mode, ['reject', 'warn', 'truncate'], true) ? $mode : 'reject';
    }

    private static function positiveInt(mixed $value, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : $default;
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
