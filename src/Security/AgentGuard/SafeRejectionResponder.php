<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Security\AgentGuard;

final readonly class SafeRejectionResponder
{
    /**
     * @param  array<string, mixed>  $messages
     */
    public function __construct(
        private bool $includeDebug = false,
        private string $defaultLocale = 'es',
        private array $messages = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(IntentValidationResult $result, ?string $locale = null): array
    {
        $payload = $result->toArray();
        $payload['message'] = $this->safeMessage($result, $locale ?? $this->defaultLocale);

        if (! $this->includeDebug) {
            unset($payload['violations']);
            unset($payload['meta']);
        }

        return $payload;
    }

    private function safeMessage(IntentValidationResult $result, string $locale): ?string
    {
        $code = $result->code();
        if ($code === null) {
            return $result->message();
        }

        $localeMessages = $this->messages[$locale] ?? [];
        $defaultMessages = $this->messages[$this->defaultLocale] ?? [];
        $localized = (is_array($localeMessages) ? ($localeMessages[$code] ?? null) : null)
            ?? (is_array($defaultMessages) ? ($defaultMessages[$code] ?? null) : null);
        if (is_string($localized) && $localized !== '') {
            return $localized;
        }

        return match ($code) {
            'ADP_INTENT_OUT_OF_DOMAIN' => 'No puedo ejecutar esa solicitud porque no pertenece al dominio de negocio publicado por esta API.',
            'ADP_UNTRUSTED_INSTRUCTION_DETECTED' => 'No puedo ejecutar esa solicitud porque intenta modificar la política de ejecución del agente.',
            'ADP_FORBIDDEN_FIELD' => 'No puedo ejecutar esa solicitud porque intenta acceder a campos no publicados o protegidos por ADP.',
            'ADP_CONFIRMATION_REQUIRED' => 'La operación solicitada requiere confirmación humana antes de ejecutarse.',
            default => $result->message(),
        };
    }
}
