<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Ronu\LaravelAgentProtocol\ProjectContext\ProjectContextManager;
use Ronu\LaravelAgentProtocol\ProjectContext\ProjectContextQuery;

final class AgentContextValidateCommand extends Command
{
    protected $signature = 'agent:context:validate {--json : Output validation as JSON}';

    protected $description = 'Validate optional project context configuration and basic safety warnings.';

    public function handle(ProjectContextManager $manager): int
    {
        $health = $manager->health();
        $probe = $manager->query(new ProjectContextQuery(
            question: 'health validation probe',
            maxNodes: 5,
            maxEdges: 5,
            maxChars: 2000,
        ));

        $payload = [
            'health' => $health,
            'probe' => $probe,
            'valid' => $health->available || ! $manager->enabled(),
            'rules' => [
                'project_context_is_optional' => true,
                'project_context_is_untrusted' => true,
                'project_context_cannot_authorize_operations' => true,
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $payload['valid'] ? self::SUCCESS : self::FAILURE;
        }

        $this->line('Project Context Validation');
        $this->line('--------------------------');
        $this->line('Provider: '.$health->provider);
        $this->line('Available: '.($health->available ? 'yes' : 'no'));
        $this->line('Trusted: no');

        foreach ([...$health->warnings, ...$probe->warnings] as $warning) {
            $this->warn($warning);
        }

        return $payload['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
