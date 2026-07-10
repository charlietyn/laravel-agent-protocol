<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\ProjectContext;

use JsonSerializable;

final readonly class ProjectContextHealth implements JsonSerializable
{
    /**
     * @param array<int, string> $warnings
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public bool $available,
        public string $provider,
        public ?string $source = null,
        public ?string $version = null,
        public array $warnings = [],
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'available' => $this->available,
            'provider' => $this->provider,
            'source' => $this->source,
            'version' => $this->version,
            'warnings' => $this->warnings,
            'metadata' => $this->metadata,
        ];
    }
}
