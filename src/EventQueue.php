<?php

declare(strict_types=1);

namespace Gabrieldav1108\ServerAnalytics;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;

class EventQueue
{
    public function __construct(private readonly RedisFactory $redis) {}

    /** @param  array<string, mixed>  $event */
    public function push(array $event): void
    {
        $this->connection()->rpush($this->key(), (string) json_encode($event));
    }

    /** @return list<array<string, mixed>> */
    public function peek(int $limit): array
    {
        $stop = $limit === PHP_INT_MAX ? -1 : $limit - 1;

        /** @var list<array<string, mixed>> $events */
        $events = array_map(
            fn (string $event): array => json_decode($event, true),
            $this->connection()->lrange($this->key(), 0, $stop),
        );

        return $events;
    }

    public function discard(int $count): void
    {
        $this->connection()->ltrim($this->key(), $count, -1);
    }

    private function connection(): Connection
    {
        return $this->redis->connection(config('server-analytics.redis_connection'));
    }

    private function key(): string
    {
        return (string) config('server-analytics.queue_key');
    }
}
