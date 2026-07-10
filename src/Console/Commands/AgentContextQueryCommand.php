<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Ronu\LaravelAgentProtocol\ProjectContext\ProjectContextManager;
use Ronu\LaravelAgentProtocol\ProjectContext\ProjectContextQuery;

final class AgentContextQueryCommand extends Command
{
    protected $signature = 'agent:context:query
        {question* : Technical question to ask against the project context}
        {--resource= : Optional ADP resource related to the question}
        {--operation= : Optional ADP operation related to the question}
        {--max-nodes= : Maximum nodes to return}
        {--max-edges= : Maximum edges to return}
        {--max-chars= : Maximum serialized characters to return}
        {--json : Output result as JSON}';

    protected $description = 'Query optional project context such as Graphify graph.json.';

    public function handle(ProjectContextManager $manager): int
    {
        $question = implode(' ', (array) $this->argument('question'));

        $query = new ProjectContextQuery(
            question: $question,
            resource: is_string($this->option('resource')) ? $this->option('resource') : null,
            operation: is_string($this->option('operation')) ? $this->option('operation') : null,
            maxNodes: $this->positiveIntOption('max-nodes', 40),
            maxEdges: $this->positiveIntOption('max-edges', 80),
            maxChars: $this->positiveIntOption('max-chars', 12000),
        );

        $result = $manager->query($query);

        if ($this->option('json')) {
            $this->line((string) json_encode($result->toUntrustedPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('Project Context Query');
        $this->line('---------------------');
        $this->line('Provider: '.$result->provider);
        $this->line('Source: '.$result->source);
        $this->line('Trusted: no');
        $this->line('Nodes: '.count($result->nodes));
        $this->line('Edges: '.count($result->edges));

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        foreach ($result->summaries as $summary) {
            $this->line('- '.$summary);
        }

        return self::SUCCESS;
    }

    private function positiveIntOption(string $key, int $default): int
    {
        $value = $this->option($key);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }
}
