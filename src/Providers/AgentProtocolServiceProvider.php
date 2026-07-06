<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Providers;

use Illuminate\Support\ServiceProvider;
use Ronu\LaravelAgentProtocol\Cache\MetadataRepository;
use Ronu\LaravelAgentProtocol\Console\Commands\AgentCacheCommand;
use Ronu\LaravelAgentProtocol\Console\Commands\AgentClearCommand;
use Ronu\LaravelAgentProtocol\Console\Commands\AgentDiscoverCommand;
use Ronu\LaravelAgentProtocol\Console\Commands\AgentDocsCommand;
use Ronu\LaravelAgentProtocol\Console\Commands\AgentExportCommand;
use Ronu\LaravelAgentProtocol\Console\Commands\AgentSchemaDiscoverCommand;
use Ronu\LaravelAgentProtocol\Console\Commands\AgentSchemaExportCommand;
use Ronu\LaravelAgentProtocol\Console\Commands\AgentSchemaValidateCommand;
use Ronu\LaravelAgentProtocol\Console\Commands\AgentValidateCommand;
use Ronu\LaravelAgentProtocol\Contracts\MetadataCompilerContract;
use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\Metadata\MetadataBuildContext;
use Ronu\LaravelAgentProtocol\Metadata\MetadataCompiler;
use Ronu\LaravelAgentProtocol\Metadata\Passes\ConfiguredResourcePass;
use Ronu\LaravelAgentProtocol\Metadata\Passes\DictionaryPass;
use Ronu\LaravelAgentProtocol\Metadata\Passes\DocumentationPass;
use Ronu\LaravelAgentProtocol\Metadata\Passes\FilterDocumentationPass;
use Ronu\LaravelAgentProtocol\Metadata\Passes\RouteResourcePass;
use Ronu\LaravelAgentProtocol\Metadata\Passes\SemanticMetadataPass;
use Ronu\LaravelAgentProtocol\Metadata\Providers\ConfigDictionaryProvider;
use Ronu\LaravelAgentProtocol\Metadata\Providers\ConfigDocumentationProvider;
use Ronu\LaravelAgentProtocol\Metadata\Providers\ConfigResourceProvider;

final class AgentProtocolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->packagePath('config/agent-protocol.php'), 'agent-protocol');

        $this->app->singleton(MetadataBuildContext::class, fn ($app): MetadataBuildContext => new MetadataBuildContext($app));

        $this->app->tag([ConfigResourceProvider::class], 'agent-protocol.resource_providers');
        $this->app->tag([ConfigDictionaryProvider::class], 'agent-protocol.dictionary_providers');
        $this->app->tag([ConfigDocumentationProvider::class], 'agent-protocol.documentation_providers');

        $this->app->when(ConfiguredResourcePass::class)
            ->needs('$providers')
            ->giveTagged('agent-protocol.resource_providers');

        $this->app->when(DictionaryPass::class)
            ->needs('$providers')
            ->giveTagged('agent-protocol.dictionary_providers');

        $this->app->when(DocumentationPass::class)
            ->needs('$providers')
            ->giveTagged('agent-protocol.documentation_providers');

        $this->app->tag([
            ConfiguredResourcePass::class,
            RouteResourcePass::class,
            SemanticMetadataPass::class,
            FilterDocumentationPass::class,
            DictionaryPass::class,
            DocumentationPass::class,
        ], 'agent-protocol.compiler_passes');

        $this->app->singleton(MetadataCompilerContract::class, function ($app): MetadataCompiler {
            return new MetadataCompiler(
                passes: $app->tagged('agent-protocol.compiler_passes'),
                context: $app->make(MetadataBuildContext::class),
            );
        });

        $this->app->singleton(MetadataRepositoryContract::class, MetadataRepository::class);
    }

    public function boot(): void
    {
        $this->publishes([
            $this->packagePath('config/agent-protocol.php') => config_path('agent-protocol.php'),
        ], 'agent-protocol-config');

        if ((bool) config('agent-protocol.routes.enabled', true)) {
            $this->loadRoutesFrom($this->packagePath('routes/agent.php'));
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                AgentCacheCommand::class,
                AgentClearCommand::class,
                AgentDiscoverCommand::class,
                AgentDocsCommand::class,
                AgentValidateCommand::class,
                AgentExportCommand::class,
                AgentSchemaDiscoverCommand::class,
                AgentSchemaExportCommand::class,
                AgentSchemaValidateCommand::class,
            ]);
        }
    }

    private function packagePath(string $path): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path;
    }
}
