<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class DocumentationDescriptor implements JsonSerializable
{
    use SerializesDescriptor;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $slug,
        public string $title,
        public array $payload,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeValues([
            'slug' => $this->slug,
            'title' => $this->title,
            'payload' => $this->payload,
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
