<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronu\LaravelAgentProtocol\Http\Controllers\AgentController;

$prefix = config('agent-protocol.routes.prefix', 'agent');
$middleware = config('agent-protocol.routes.middleware', ['api']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('agent-protocol.')
    ->group(function (): void {
        Route::get('/', [AgentController::class, 'index'])->name('index');
        Route::get('/bundle', [AgentController::class, 'bundle'])->name('bundle');
        Route::get('/modules', [AgentController::class, 'modules'])->name('modules');
        Route::get('/resources', [AgentController::class, 'resources'])->name('resources');
        Route::get('/resources/{resource}/operations', [AgentController::class, 'operations'])
            ->where('resource', '[A-Za-z0-9_.:-]+')
            ->name('resources.operations');
        Route::get('/resources/{resource}/operations/{scenario}', [AgentController::class, 'operation'])
            ->where('resource', '[A-Za-z0-9_.:-]+')
            ->where('scenario', '[A-Za-z0-9_.:-]+')
            ->name('resources.operations.show');
        Route::get('/resources/{resource}', [AgentController::class, 'resource'])
            ->where('resource', '[A-Za-z0-9_.:-]+')
            ->name('resources.show');
        Route::get('/documentation/filter', [AgentController::class, 'filterDocumentation'])->name('documentation.filter');
        Route::get('/documentation/errors', [AgentController::class, 'errorDocumentation'])->name('documentation.errors');
        Route::get('/dictionary', [AgentController::class, 'dictionary'])->name('dictionary');
    });
