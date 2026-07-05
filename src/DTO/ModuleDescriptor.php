<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class ModuleDescriptor implements JsonSerializable
{
    use SerializesDescriptor;

    /**
     * @param  array<int, string>  $resources
     */
    public function __construct(
        public string $key,
        public string $name,
        public ?string $description = null,
        public array $resources = [],
    ) {}

    public function withResource(string $resource): self
    {
        return new self(
            key: $this->key,
            name: $this->name,
            description: $this->description,
            resources: array_values(array_unique([...$this->resources, $resource])),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeValues([
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'resources' => $this->resources,
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
