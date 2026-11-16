<?php

declare(strict_types=1);

namespace Gabrieldav1108\ServerAnalytics\Tests\Support;

use Illuminate\Contracts\Redis\Factory;
use RuntimeException;

final class FakeRedis implements Factory
{
    /** @var array<string, list<string>> */
    private array $lists = [];

    public bool $failing = false;

    public function connection($name = null): FakeRedisConnection
    {
        return new FakeRedisConnection($this);
    }

    public function rpush(string $key, string ...$values): int
    {
        $this->failWhenRequested();

        $this->lists[$key] = [...($this->lists[$key] ?? []), ...$values];

        return count($this->lists[$key]);
    }

    /** @return list<string> */
    public function lrange(string $key, int $start, int $stop): array
    {
        $this->failWhenRequested();

        $list = $this->lists[$key] ?? [];
        $length = $stop < 0 ? count($list) + $stop + 1 - $start : $stop - $start + 1;

        return array_slice($list, $start, max(0, $length));
    }

    public function ltrim(string $key, int $start, int $stop): bool
    {
        $this->lists[$key] = $this->lrange($key, $start, $stop);

        return true;
    }

    private function failWhenRequested(): void
    {
        if ($this->failing) {
            throw new RuntimeException('Connection refused');
        }
    }
}
