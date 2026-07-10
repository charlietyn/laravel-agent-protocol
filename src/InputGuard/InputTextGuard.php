<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\InputGuard;

use Ronu\LaravelAgentProtocol\Security\AgentGuard\PromptInjectionSignalDetector;

final readonly class InputTextGuard
{
    public function __construct(
        private InputTextPolicy $policy,
        private InputTextNormalizer $normalizer = new InputTextNormalizer,
        private SensitiveTextDetector $sensitiveTextDetector = new SensitiveTextDetector,
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(InputTextPolicy::fromConfig($config));
    }

    public function validate(string $input): InputTextValidationResult
    {
        if (! $this->policy->enabled) {
            return InputTextValidationResult::allowed($input, $input, metadata: $this->metrics($input, $input));
        }

        $normalized = $this->normalizer->normalize($input, $this->policy);
        $normalizedInput = $normalized['input'];
        $violations = [];
        $truncated = false;

        $maxCharsViolation = $this->maxCharsViolation($normalizedInput);
        if ($maxCharsViolation instanceof InputTextViolation && $this->policy->shouldTruncate()) {
            $normalizedInput = mb_substr($normalizedInput, 0, $this->policy->maxChars);
            $truncated = true;
            $violations[] = new InputTextViolation(
                code: 'ADP_INPUT_TRUNCATED',
                message: 'The input text was truncated to the configured maximum character limit.',
                severity: 'warning',
                details: $maxCharsViolation->details,
            );
        } elseif ($maxCharsViolation instanceof InputTextViolation) {
            $violations[] = $maxCharsViolation;
        }

        foreach ($this->structuralViolations($normalizedInput) as $violation) {
            $violations[] = $violation;
        }

        foreach ($this->securityViolations($normalizedInput) as $violation) {
            $violations[] = $violation;
        }

        $metadata = [
            ...$this->metrics($input, $normalizedInput),
            'normalization' => $normalized['metadata'],
            'mode' => $this->policy->mode,
        ];

        if ($this->hasRejectableViolations($violations)) {
            return InputTextValidationResult::rejected($input, $normalizedInput, $violations, $metadata);
        }

        return InputTextValidationResult::allowed(
            input: $input,
            normalizedInput: $normalizedInput,
            truncated: $truncated,
            metadata: [
                ...$metadata,
                'warnings' => array_map(static fn (InputTextViolation $violation): array => $violation->jsonSerialize(), $violations),
            ],
        );
    }

    private function maxCharsViolation(string $input): ?InputTextViolation
    {
        $chars = mb_strlen($input);
        if ($chars <= $this->policy->maxChars) {
            return null;
        }

        return new InputTextViolation(
            code: 'ADP_INPUT_TOO_LARGE',
            message: 'The input text exceeds the configured maximum character limit.',
            details: [
                'max_chars' => $this->policy->maxChars,
                'actual_chars' => $chars,
            ],
        );
    }

    /**
     * @return array<int, InputTextViolation>
     */
    private function structuralViolations(string $input): array
    {
        $violations = [];
        $bytes = strlen($input);
        if ($bytes > $this->policy->maxBytes) {
            $violations[] = new InputTextViolation(
                code: 'ADP_INPUT_TOO_MANY_BYTES',
                message: 'The input text exceeds the configured maximum byte limit.',
                details: ['max_bytes' => $this->policy->maxBytes, 'actual_bytes' => $bytes],
            );
        }

        $lines = $input === '' ? 0 : substr_count($input, "\n") + 1;
        if ($lines > $this->policy->maxLines) {
            $violations[] = new InputTextViolation(
                code: 'ADP_INPUT_TOO_MANY_LINES',
                message: 'The input text exceeds the configured maximum line limit.',
                details: ['max_lines' => $this->policy->maxLines, 'actual_lines' => $lines],
            );
        }

        if ($this->policy->denyBinaryContent && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $input) === 1) {
            $violations[] = new InputTextViolation(
                code: 'ADP_INPUT_BINARY_CONTENT_DETECTED',
                message: 'The input text contains binary or unsafe control characters.',
            );
        }

        if ($this->hasRepeatedCharRun($input)) {
            $violations[] = new InputTextViolation(
                code: 'ADP_INPUT_REPEATED_CHAR_RUN',
                message: 'The input text contains an excessive run of repeated characters.',
                details: ['max_repeated_char_run' => $this->policy->maxRepeatedCharRun],
            );
        }

        return $violations;
    }

    /**
     * @return array<int, InputTextViolation>
     */
    private function securityViolations(string $input): array
    {
        $violations = [];

        if ($this->policy->detectPromptInjection) {
            $hits = (new PromptInjectionSignalDetector($this->policy->promptInjectionPatterns))->detect($input);
            if ($hits !== []) {
                $violations[] = new InputTextViolation(
                    code: 'ADP_INPUT_PROMPT_INJECTION_DETECTED',
                    message: 'The input text contains instructions that attempt to override the ADP execution policy.',
                    details: ['signals' => $hits],
                );
            }
        }

        if ($this->policy->detectSecrets) {
            $hits = $this->sensitiveTextDetector->detect($input, $this->policy->sensitivePatterns);
            if ($hits !== []) {
                $violations[] = new InputTextViolation(
                    code: 'ADP_INPUT_POSSIBLE_SECRET_DETECTED',
                    message: 'The input text appears to contain sensitive data or credentials.',
                    details: ['signals' => $hits],
                );
            }
        }

        return $violations;
    }

    /**
     * @param array<int, InputTextViolation> $violations
     */
    private function hasRejectableViolations(array $violations): bool
    {
        if ($violations === []) {
            return false;
        }

        if ($this->policy->shouldWarn()) {
            return false;
        }

        foreach ($violations as $violation) {
            if ($violation->severity === 'error') {
                return true;
            }
        }

        return false;
    }

    private function hasRepeatedCharRun(string $input): bool
    {
        if ($this->policy->maxRepeatedCharRun <= 0) {
            return false;
        }

        $threshold = $this->policy->maxRepeatedCharRun + 1;

        return preg_match('/(.)\1{'.$threshold.',}/us', $input) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(string $input, string $normalizedInput): array
    {
        return [
            'input_hash' => hash('sha256', $input),
            'normalized_hash' => hash('sha256', $normalizedInput),
            'input_length_chars' => mb_strlen($input),
            'input_length_bytes' => strlen($input),
            'normalized_length_chars' => mb_strlen($normalizedInput),
            'normalized_length_bytes' => strlen($normalizedInput),
            'line_count' => $normalizedInput === '' ? 0 : substr_count($normalizedInput, "\n") + 1,
        ];
    }
}
