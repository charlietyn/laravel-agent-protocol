<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\InputGuard;

final readonly class SensitiveTextDetector
{
    private const DEFAULT_PATTERNS = [
        '/\b(?:password|passwd|pwd|secret|api[_-]?key|access[_-]?token|refresh[_-]?token)\s*[:=]/i' => 'credential_assignment',
        '/-----BEGIN (?:RSA |EC |OPENSSH |PGP )?PRIVATE KEY-----/i' => 'private_key',
        '/\bsk-[A-Za-z0-9_\-]{16,}\b/' => 'api_token_like_value',
        '/\bgh[pousr]_[A-Za-z0-9_]{20,}\b/' => 'github_token_like_value',
    ];

    /**
     * @param array<int, string> $extraPatterns
     * @return array<int, string>
     */
    public function detect(string $input, array $extraPatterns = []): array
    {
        $hits = [];

        foreach (self::DEFAULT_PATTERNS as $pattern => $label) {
            if (preg_match($pattern, $input) === 1) {
                $hits[] = $label;
            }
        }

        foreach ($extraPatterns as $pattern) {
            if ($pattern !== '' && @preg_match($pattern, $input) === 1) {
                $hits[] = 'custom_sensitive_pattern';
            }
        }

        return array_values(array_unique($hits));
    }
}
