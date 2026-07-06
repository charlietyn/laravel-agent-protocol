<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class FilterDescriptor implements JsonSerializable
{
    use SerializesDescriptor;

    /**
     * @param  array<int, string>  $operators
     * @param  array<string, mixed>  $parameters
     * @param  array<int, array<string, mixed>>  $examples
     * @param  array<string, mixed>  $limits
     */
    public function __construct(
        public array $operators,
        public array $parameters,
        public string $conditionFormat,
        public array $examples = [],
        public array $limits = [],
        public bool $strictRelations = true,
        public bool $validateColumns = true,
        public bool $strictColumnValidation = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeValues([
            'operators' => $this->operators,
            'parameters' => $this->parameters,
            'condition_format' => $this->conditionFormat,
            'examples' => $this->examples,
            'limits' => $this->limits,
            'strict_relations' => $this->strictRelations,
            'validate_columns' => $this->validateColumns,
            'strict_column_validation' => $this->strictColumnValidation,
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
