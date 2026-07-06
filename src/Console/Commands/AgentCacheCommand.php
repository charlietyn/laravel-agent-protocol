<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\Validation\ProtocolValidator;

final class AgentCacheCommand extends Command
{
    protected $signature = 'agent:cache {--tenant=} {--locale=} {--only=}';

    protected $description = 'Compile and cache the Agent Discovery Protocol metadata graph.';

    public function handle(MetadataRepositoryContract $repository, ProtocolValidator $validator): int
    {
        $graph = $repository->refresh($this->variation());
        $errors = $validator->validate($graph);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Agent metadata cached successfully.');
        $this->line('Resources: '.count($graph->resources));
        $this->line('Modules: '.count($graph->modules));

        if ($this->option('only') === 'references') {
            $this->line('Reference metadata is compiled as part of the ADP graph.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function variation(): array
    {
        $headers = [];
        $tenant = $this->option('tenant');
        $locale = $this->option('locale');
        $tenantHeader = config('agent-protocol.security.tenant_header', 'X-Tenant-Id');
        $localeHeader = config('agent-protocol.security.locale_header', 'Accept-Language');
        $tenantHeader = is_string($tenantHeader) && $tenantHeader !== '' ? $tenantHeader : 'X-Tenant-Id';
        $localeHeader = is_string($localeHeader) && $localeHeader !== '' ? $localeHeader : 'Accept-Language';

        if (is_scalar($tenant) && (string) $tenant !== '') {
            $headers[$tenantHeader] = (string) $tenant;
        }

        if (is_scalar($locale) && (string) $locale !== '') {
            $headers[$localeHeader] = (string) $locale;
        }

        return $headers === [] ? [] : ['headers' => $headers];
    }
}
