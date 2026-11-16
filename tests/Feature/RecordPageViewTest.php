<?php

declare(strict_types=1);

use Gabrieldav1108\ServerAnalytics\Actor;
use Gabrieldav1108\ServerAnalytics\Http\Middleware\RecordPageView;
use Gabrieldav1108\ServerAnalytics\ServerAnalytics;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware('web')->get('/invoices/{invoice}/edit', fn (): string => 'ok');
    Route::middleware('web')->post('/livewire/update', fn (): string => 'ok');
});

it('records the route pattern, status and duration of a full page request', function (): void {
    $this->get('/invoices/123/edit?document=12345678900')->assertOk();

    $event = $this->queuedEvents()[0];

    expect($this->queuedEvents())->toHaveCount(1)
        ->and($event['type'])->toBe('page.view')
        ->and($event['name'])->toBe('invoices/{invoice}/edit')
        ->and($event['http_status'])->toBe(200)
        ->and($event['duration_ms'])->toBeInt()->toBeGreaterThanOrEqual(0)
        ->and($event['event_id'])->toBeUuid()
        ->and(json_encode($event))->not->toContain('12345678900')->not->toContain('123/edit');
});

it('identifies the actor through the resolver registered by the application', function (): void {
    ServerAnalytics::resolveActorUsing(fn (): Actor => new Actor(tenant: 'ACME', userId: '42'));

    $this->get('/invoices/1/edit');

    expect($this->queuedEvents()[0])
        ->tenant->toBe('ACME')
        ->user_id->toBe('42');
});

it('falls back to the authenticated user id when no resolver is registered', function (): void {
    Auth::shouldReceive('id')->andReturn(7);

    $this->get('/invoices/1/edit');

    expect($this->queuedEvents()[0])
        ->tenant->toBeNull()
        ->user_id->toBe('7');
});

it('still records the request without actor when the resolver throws', function (): void {
    ServerAnalytics::resolveActorUsing(fn (): Actor => throw new RuntimeException('session expired'));

    $this->get('/invoices/1/edit')->assertOk();

    expect($this->queuedEvents()[0])
        ->tenant->toBeNull()
        ->user_id->toBeNull();
});

it('ignores livewire updates, non GET requests and unmatched routes', function (): void {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $this->post('/livewire/update', [], ['X-Livewire' => '1']);
    $this->get('/missing-route')->assertNotFound();

    expect($this->queuedEvents())->toBeEmpty();
});

it('keeps answering the request when redis is down', function (): void {
    $this->redis->failing = true;

    $this->get('/invoices/1/edit')->assertOk();
});

it('records nothing while the endpoint is not configured or the package is disabled', function (string $key, mixed $value): void {
    config()->set($key, $value);

    $this->get('/invoices/1/edit')->assertOk();

    expect($this->queuedEvents())->toBeEmpty();
})->with([
    'no endpoint' => ['server-analytics.endpoint', null],
    'no token' => ['server-analytics.token', null],
    'disabled' => ['server-analytics.enabled', false],
]);

it('runs first in the configured middleware groups', function (): void {
    expect(app(Kernel::class)->getMiddlewareGroups()['web'][0])->toBe(RecordPageView::class);
});
