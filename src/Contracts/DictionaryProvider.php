<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Contracts;

interface DictionaryProvider
{
    /**
     * @return array<string, mixed>
     */
    public function dictionary(): array;
}
