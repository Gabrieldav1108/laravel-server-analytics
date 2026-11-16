<?php

declare(strict_types=1);

namespace Gabrieldav1108\ServerAnalytics\Tests;

use Gabrieldav1108\ServerAnalytics\EventQueue;
use Gabrieldav1108\ServerAnalytics\ServerAnalytics;
use Gabrieldav1108\ServerAnalytics\ServerAnalyticsServiceProvider;
use Gabrieldav1108\ServerAnalytics\Tests\Support\FakeRedis;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected FakeRedis $redis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redis = new FakeRedis;
        $this->app->instance(RedisFactory::class, $this->redis);
    }

    protected function tearDown(): void
    {
        ServerAnalytics::resolveActorUsing(null);

        parent::tearDown();
    }

    /** @return list<array<string, mixed>> */
    protected function queuedEvents(): array
    {
        return app(EventQueue::class)->peek(PHP_INT_MAX);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('app.timezone', 'America/Sao_Paulo');
        $app['config']->set('server-analytics.endpoint', 'https://analytics.test/api/v1/events');
        $app['config']->set('server-analytics.token', 'emitter-token');
    }

    protected function getPackageProviders($app): array
    {
        return [ServerAnalyticsServiceProvider::class];
    }
}
