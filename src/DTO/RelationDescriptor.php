<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class RelationDescriptor implements JsonSerializable
{
    use SerializesDescriptor;

    public function __construct(
        public string $name,
        public string $type,
        public ?string $relatedModel = null,
        public ?string $relatedResource = null,
        public ?string $foreignKey = null,
        public ?string $ownerKey = null,
        public ?string $localKey = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeValues([
            'name' => $this->name,
            'type' => $this->type,
            'related_model' => $this->relatedModel,
            'related_resource' => $this->relatedResource,
            'foreign_key' => $this->foreignKey,
            'owner_key' => $this->ownerKey,
            'local_key' => $this->localKey,
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
