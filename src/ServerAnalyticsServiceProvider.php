<?php

declare(strict_types=1);

namespace Gabrieldav1108\ServerAnalytics;

use Gabrieldav1108\ServerAnalytics\Console\FlushCommand;
use Gabrieldav1108\ServerAnalytics\Http\Middleware\RecordPageView;
use Gabrieldav1108\ServerAnalytics\Listeners\RecordAuthentication;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ServerAnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/server-analytics.php', 'server-analytics');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/server-analytics.php' => config_path('server-analytics.php'),
        ], 'server-analytics-config');

        Event::listen(Login::class, [RecordAuthentication::class, 'login']);
        Event::listen(Logout::class, [RecordAuthentication::class, 'logout']);
        Event::listen(Failed::class, [RecordAuthentication::class, 'failed']);

        $this->callAfterResolving(Kernel::class, $this->registerMiddleware(...));

        if ($this->app->runningInConsole()) {
            $this->commands([FlushCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, $this->scheduleFlush(...));
    }

    private function registerMiddleware(Kernel $kernel): void
    {
        if (! $kernel instanceof HttpKernel) {
            return;
        }

        $availableGroups = array_keys($kernel->getMiddlewareGroups());

        foreach (array_intersect((array) config('server-analytics.middleware_groups'), $availableGroups) as $group) {
            $kernel->prependMiddlewareToGroup($group, RecordPageView::class);
        }
    }

    private function scheduleFlush(Schedule $schedule): void
    {
        if (! ServerAnalytics::enabled()) {
            return;
        }

        $schedule->command(FlushCommand::class)->everyMinute()->withoutOverlapping()->runInBackground();
    }
}
