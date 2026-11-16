<?php

declare(strict_types=1);

use Gabrieldav1108\ServerAnalytics\EventQueue;
use Gabrieldav1108\ServerAnalytics\Facades\Analytics;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->queueActions = function (int $count): void {
        foreach (range(1, $count) as $index) {
            Analytics::action("action.{$index}");
        }
    };

    $this->scheduledFlushes = function (): array {
        app()->forgetInstance(Schedule::class);

        return collect(app(Schedule::class)->events())
            ->filter(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'server-analytics:flush'))
            ->values()
            ->all();
    };
});

it('sends the queued events in batches and empties the queue', function (): void {
    config()->set('server-analytics.batch_size', 2);
    Http::fake(['analytics.test/*' => Http::response(['accepted' => 2, 'duplicates' => 0, 'rejected' => []])]);
    ($this->queueActions)(3);

    $this->artisan('server-analytics:flush')->assertSuccessful();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://analytics.test/api/v1/events'
        && $request->hasHeader('Authorization', 'Bearer emitter-token')
        && count($request['events']) <= 2);
    expect($this->queuedEvents())->toBeEmpty();
});

it('keeps the events in the queue when the endpoint fails', function (): void {
    Http::fake(['analytics.test/*' => Http::response(null, 503)]);
    ($this->queueActions)(2);

    $this->artisan('server-analytics:flush')->assertFailed();

    expect($this->queuedEvents())->toHaveCount(2);
});

it('keeps the events in the queue when the endpoint is unreachable', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));
    ($this->queueActions)(2);

    $this->artisan('server-analytics:flush')->assertFailed();

    expect($this->queuedEvents())->toHaveCount(2);
});

it('drops events older than the backlog window without sending them', function (): void {
    Http::fake(['analytics.test/*' => Http::response(['accepted' => 1, 'duplicates' => 0, 'rejected' => []])]);
    app(EventQueue::class)->push([
        'event_id' => (string) Str::uuid7(),
        'type' => 'action',
        'name' => 'old',
        'occurred_at' => now()->subDays(8)->toIso8601String(),
    ]);
    ($this->queueActions)(1);

    $this->artisan('server-analytics:flush')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => count($request['events']) === 1
        && $request['events'][0]['name'] === 'action.1');
    expect($this->queuedEvents())->toBeEmpty();
});

it('does nothing when the queue is empty', function (): void {
    Http::fake();

    $this->artisan('server-analytics:flush')->assertSuccessful();

    Http::assertNothingSent();
});

it('schedules the flush every minute', function (): void {
    $flushes = ($this->scheduledFlushes)();

    expect($flushes)->toHaveCount(1)
        ->and($flushes[0]->expression)->toBe('* * * * *')
        ->and($flushes[0]->withoutOverlapping)->toBeTrue();
});

it('does not schedule the flush while the endpoint is not configured', function (): void {
    config()->set('server-analytics.token', null);

    expect(($this->scheduledFlushes)())->toBeEmpty();
});
