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
     * @param  array<int, string>  $operators
     */
    public function __construct(
        public string $name,
        public string $type = 'mixed',
        public ?bool $nullable = null,
        public bool $fillable = false,
        public ?string $cast = null,
        public array $validationRules = [],
        public bool $filterable = true,
        public bool $selectable = true,
        public bool $sensitive = false,
        public bool $visible = true,
        public array $operators = [],
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
            'filterable' => $this->filterable,
            'selectable' => $this->selectable,
            'sensitive' => $this->sensitive,
            'visible' => $this->visible,
            'operators' => $this->operators,
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
