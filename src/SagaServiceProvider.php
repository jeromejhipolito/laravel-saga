<?php

declare(strict_types=1);

namespace JeromeJHipolito\Saga;

use Illuminate\Support\ServiceProvider;
use JeromeJHipolito\Saga\Contracts\SagaTransactionInterface;
use JeromeJHipolito\Saga\Services\SagaTransactionManager;

class SagaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SagaTransactionInterface::class, SagaTransactionManager::class);
        $this->app->bind(SagaTransactionManager::class, SagaTransactionManager::class);
    }

    public function boot(): void
    {
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'saga-migrations');
    }
}
