<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Introspection;

use Illuminate\Contracts\Container\Container;
use Ronu\LaravelAgentProtocol\DTO\ValidationDescriptor;

final readonly class ValidationInspector
{
    public function __construct(
        private Container $container,
        private RuleNormalizer $ruleNormalizer,
    ) {}

    /**
     * @return array<string, ValidationDescriptor>
     */
    public function inspect(?string $requestClass): array
    {
        if (! $requestClass || ! class_exists($requestClass)) {
            return [];
        }

        $request = $this->requestInstance($requestClass);

        try {
            if (method_exists($request, 'getAvailableScenarios') && method_exists($request, 'getRulesForScenario')) {
                $scenarios = $request->getAvailableScenarios();
                if ($scenarios === true) {
                    $scenarios = ['default'];
                }

                $descriptors = [];
                foreach ((array) $scenarios as $scenario) {
                    $scenario = (string) $scenario;
                    $descriptors[$scenario] = new ValidationDescriptor(
                        scenario: $scenario,
                        rules: $this->normalizeRules($request->getRulesForScenario($scenario)),
                        messages: $this->messages($request),
                        authorization: $this->authorization($request),
                    );
                }

                return $descriptors;
            }

            if (method_exists($request, 'rules')) {
                return [
                    'default' => new ValidationDescriptor(
                        scenario: 'default',
                        rules: $this->normalizeRules($request->rules()),
                        messages: $this->messages($request),
                        authorization: $this->authorization($request),
                    ),
                ];
            }
        } catch (\Throwable) {
            return [];
        }

        return [];
    }

    private function requestInstance(string $requestClass): object
    {
        try {
            $request = $this->container->make($requestClass);
            if (is_object($request)) {
                return $request;
            }
        } catch (\Throwable) {
            // Fall back to direct construction below.
        }

        return new $requestClass;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, array<int, string>>
     */
    private function normalizeRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $field => $fieldRules) {
            $normalized[(string) $field] = $this->ruleNormalizer->normalize($fieldRules);
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function messages(object $request): array
    {
        if (! method_exists($request, 'messages')) {
            return [];
        }

        try {
            $messages = $request->messages();
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($messages)) {
            return [];
        }

        $normalized = [];
        foreach ($messages as $key => $message) {
            if (is_string($key) && is_scalar($message)) {
                $normalized[$key] = (string) $message;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function authorization(object $request): array
    {
        if (! method_exists($request, 'authorize')) {
            return ['declared' => false];
        }

        return [
            'declared' => true,
            'runtime_evaluated' => false,
        ];
    }
}
