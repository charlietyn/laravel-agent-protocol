<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class FieldDescriptor implements JsonSerializable
{
    use SerializesDescriptor;

    /**
     * @param  array<int, string>  $validationRules
     */
    public function __construct(
        public string $name,
        public string $type = 'mixed',
        public ?bool $nullable = null,
        public bool $fillable = false,
        public ?string $cast = null,
        public array $validationRules = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeValues([
            'name' => $this->name,
            'type' => $this->type,
            'nullable' => $this->nullable,
            'fillable' => $this->fillable,
            'cast' => $this->cast,
            'validation_rules' => $this->validationRules,
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
