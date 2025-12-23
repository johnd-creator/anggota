<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Queue;
use App\Models\ActivityLog;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register AuditService as singleton to maintain state across request lifecycle
        $this->app->singleton(\App\Services\AuditService::class, function ($app) {
            return new \App\Services\AuditService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Inertia::version(null);

        Queue::failing(function ($event) {
            try {
                ActivityLog::create([
                    'actor_id' => null,
                    'action' => 'notification_failed',
                    'subject_type' => $event->job?->resolveName() ?? 'queue_job',
                    'subject_id' => null,
                    'payload' => [
                        'connection' => $event->connectionName,
                        'queue' => method_exists($event->job, 'getQueue') ? $event->job->getQueue() : null,
                        'exception' => $event->exception?->getMessage(),
                    ],
                ]);
            } catch (\Throwable $e) {
                // swallow
            }
        });
        \Laravel\Socialite\Facades\Socialite::extend('microsoft', function ($app) {
            $config = $app['config']['services.microsoft'];
            return $app->make(\Laravel\Socialite\Contracts\Factory::class)->buildProvider(
                \App\Socialite\MicrosoftProvider::class,
                $config
            );
        });
    }
}
