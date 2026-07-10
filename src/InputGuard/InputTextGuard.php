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

        $preNormalizationViolations = $this->preNormalizationViolations($input);
        if ($this->hasMalformedUtf8Violation($preNormalizationViolations)) {
            return InputTextValidationResult::rejected(
                input: $input,
                normalizedInput: '',
                violations: $preNormalizationViolations,
                metadata: [
                    ...$this->metrics($input, ''),
                    'normalization' => ['skipped' => 'malformed_utf8'],
                    'mode' => $this->policy->mode,
                ],
            );
        }

        $normalized = $this->normalizer->normalize($input, $this->policy);
        $normalizedInput = $normalized['input'];
        $violations = $preNormalizationViolations;
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

        foreach ($this->postNormalizationStructuralViolations($normalizedInput) as $violation) {
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

    /**
     * Raw input limits are enforced before trim/control-collapse normalization.
     * This prevents oversized whitespace/control payloads from being normalized
     * into a small string before audit or LLM handling.
     *
     * @return array<int, InputTextViolation>
     */
    private function preNormalizationViolations(string $input): array
    {
        $violations = [];

        $bytes = strlen($input);
        if ($bytes > $this->policy->maxBytes) {
            $violations[] = new InputTextViolation(
                code: 'ADP_INPUT_TOO_MANY_BYTES',
                message: 'The raw input text exceeds the configured maximum byte limit.',
                details: ['max_bytes' => $this->policy->maxBytes, 'actual_bytes' => $bytes],
            );
        }

        $lines = $input === '' ? 0 : substr_count($input, "\n") + 1;
        if ($lines > $this->policy->maxLines) {
            $violations[] = new InputTextViolation(
                code: 'ADP_INPUT_TOO_MANY_LINES',
                message: 'The raw input text exceeds the configured maximum line limit.',
                details: ['max_lines' => $this->policy->maxLines, 'actual_lines' => $lines],
            );
        }

        if (! mb_check_encoding($input, 'UTF-8')) {
            $violations[] = new InputTextViolation(
                code: 'ADP_INPUT_MALFORMED_UTF8',
                message: 'The raw input text is not valid UTF-8 and is treated as malformed or binary content.',
            );

            return $violations;
        }

        if ($this->policy->denyBinaryContent && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $input) === 1) {
            $violations[] = new InputTextViolation(
                code: 'ADP_INPUT_BINARY_CONTENT_DETECTED',
                message: 'The raw input text contains binary or unsafe control characters.',
            );
        }

        return $violations;
    }

    private function maxCharsViolation(string $input): ?InputTextViolation
    {
        $chars = $this->charLength($input);
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
     * Post-normalization checks still run because normalization can transform
     * the text that will actually be sent to downstream prompt/agent code.
     * Raw byte and line limits are intentionally not repeated here.
     *
     * @return array<int, InputTextViolation>
     */
    private function postNormalizationStructuralViolations(string $input): array
    {
        $violations = [];

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
    private function hasMalformedUtf8Violation(array $violations): bool
    {
        foreach ($violations as $violation) {
            if ($violation->code === 'ADP_INPUT_MALFORMED_UTF8') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, InputTextViolation> $violations
     */
    private function hasRejectableViolations(array $violations): bool
    {
        if ($violations === []) {
            return false;
        }

        foreach ($violations as $violation) {
            if (in_array($violation->code, ['ADP_INPUT_MALFORMED_UTF8', 'ADP_INPUT_BINARY_CONTENT_DETECTED'], true)) {
                return true;
            }
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

        return preg_match('/(.)\1{'.$this->policy->maxRepeatedCharRun.',}/us', $input) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(string $input, string $normalizedInput): array
    {
        return [
            'input_hash' => hash('sha256', $input),
            'normalized_hash' => hash('sha256', $normalizedInput),
            'input_valid_utf8' => mb_check_encoding($input, 'UTF-8'),
            'input_length_chars' => $this->charLength($input),
            'input_length_bytes' => strlen($input),
            'raw_line_count' => $input === '' ? 0 : substr_count($input, "\n") + 1,
            'normalized_length_chars' => $this->charLength($normalizedInput),
            'normalized_length_bytes' => strlen($normalizedInput),
            'normalized_line_count' => $normalizedInput === '' ? 0 : substr_count($normalizedInput, "\n") + 1,
        ];
    }

    private function charLength(string $input): ?int
    {
        return mb_check_encoding($input, 'UTF-8') ? mb_strlen($input) : null;
    }
}
