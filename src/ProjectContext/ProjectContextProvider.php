<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\ProjectContext;

interface ProjectContextProvider
{
    public function enabled(): bool;

    public function health(): ProjectContextHealth;

    public function query(ProjectContextQuery $query): ProjectContextResult;
}
