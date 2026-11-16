<?php

declare(strict_types=1);

namespace Gabrieldav1108\ServerAnalytics\Tests\Support;

use Closure;
use Illuminate\Redis\Connections\Connection;

final class FakeRedisConnection extends Connection
{
    public function __construct(FakeRedis $client)
    {
        $this->client = $client;
    }

    public function createSubscription($channels, Closure $callback, $method = 'subscribe'): void {}
}
