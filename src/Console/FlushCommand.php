<?php

declare(strict_types=1);

namespace Gabrieldav1108\ServerAnalytics\Console;

use Gabrieldav1108\ServerAnalytics\EventQueue;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlushCommand extends Command
{
    protected $signature = 'server-analytics:flush';

    protected $description = 'Send the queued analytics events to the configured endpoint.';

    public function handle(EventQueue $queue): int
    {
        $batchSize = (int) config('server-analytics.batch_size');

        while (($batch = $queue->peek($batchSize)) !== []) {
            $freshEvents = $this->withinBacklogWindow($batch);

            if ($freshEvents !== [] && ! $this->send($freshEvents)) {
                return self::FAILURE;
            }

            $queue->discard(count($batch));
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     * @return list<array<string, mixed>>
     */
    private function withinBacklogWindow(array $batch): array
    {
        $oldestAccepted = now()->subDays((int) config('server-analytics.backlog_days'));

        $freshEvents = array_values(array_filter(
            $batch,
            fn (array $event): bool => Carbon::parse((string) $event['occurred_at'])->greaterThanOrEqualTo($oldestAccepted),
        ));

        if (count($freshEvents) < count($batch)) {
            Log::warning('server-analytics: events past the backlog window dropped', ['count' => count($batch) - count($freshEvents)]);
        }

        return $freshEvents;
    }

    /** @param  list<array<string, mixed>>  $events */
    private function send(array $events): bool
    {
        try {
            $response = Http::withToken((string) config('server-analytics.token'))
                ->acceptJson()
                ->timeout((int) config('server-analytics.timeout_seconds'))
                ->post((string) config('server-analytics.endpoint'), ['events' => $events]);
        } catch (ConnectionException $exception) {
            Log::warning('server-analytics: endpoint unreachable', ['error' => $exception->getMessage()]);

            return false;
        }

        if ($response->successful()) {
            return true;
        }

        Log::warning('server-analytics: batch refused by the endpoint', ['status' => $response->status()]);

        return false;
    }
}
