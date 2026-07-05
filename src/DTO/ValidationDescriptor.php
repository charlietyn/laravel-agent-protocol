<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class ValidationDescriptor implements JsonSerializable
{
    use SerializesDescriptor;

    /**
     * @param  array<string, array<int, string>>  $rules
     */
    public function __construct(
        public string $scenario,
        public array $rules = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeValues([
            'scenario' => $this->scenario,
            'rules' => $this->rules,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
