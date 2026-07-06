<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Ronu\LaravelAgentProtocol\Providers\AgentProtocolServiceProvider;
use Ronu\LaravelAgentProtocol\Tests\Fixtures\FakeModel;
use Ronu\LaravelAgentProtocol\Tests\Fixtures\FakeRequest;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [AgentProtocolServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', ['driver' => 'array']);
        $app['config']->set('agent-protocol.routes.middleware', []);
        $app['config']->set('agent-protocol.discovery.routes', false);
        $app['config']->set('rest-generic-class.filtering.max_depth', 3);
        $app['config']->set('rest-generic-class.filtering.max_conditions', 25);
        $app['config']->set('agent-protocol.resources', [
            'security.fake-user' => [
                'module' => 'security',
                'model' => FakeModel::class,
                'request' => FakeRequest::class,
                'endpoint' => '/api/security/fake-users',
                'description' => 'Fake users for ADP tests.',
                'permissions' => ['security.fake-user.view'],
                'operations' => [
                    'delete' => [
                        'method' => 'DELETE',
                        'endpoint' => '/api/security/fake-users/{id}',
                        'permissions' => ['security.fake-user.delete'],
                    ],
                ],
            ],
        ]);
    }
}
