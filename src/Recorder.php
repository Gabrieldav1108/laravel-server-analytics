<?php

declare(strict_types=1);

namespace Gabrieldav1108\ServerAnalytics;

use Gabrieldav1108\ServerAnalytics\Enums\EventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class Recorder
{
    public function __construct(private readonly EventQueue $queue) {}

    public function action(string $name, ?Model $subject = null): void
    {
        $this->record(EventType::Action, $name, ServerAnalytics::currentActor(), [
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => ServerAnalytics::stringOrNull($subject?->getKey()),
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    public function record(EventType $type, string $name, Actor $actor, array $attributes = []): void
    {
        if (! ServerAnalytics::enabled()) {
            return;
        }

        try {
            $this->queue->push([
                'event_id' => (string) Str::uuid7(),
                'type' => $type->value,
                'name' => $name,
                'tenant' => $actor->tenant,
                'user_id' => $actor->userId,
                'occurred_at' => now()->format('Y-m-d\TH:i:s.vP'),
                'ip' => request()->ip(),
                'session_hash' => $this->sessionHash(),
                ...$attributes,
            ]);
        } catch (Throwable $exception) {
            Log::warning('server-analytics: event dropped', ['type' => $type->value, 'error' => $exception->getMessage()]);
        }
    }

    private function sessionHash(): ?string
    {
        if (! request()->hasSession()) {
            return null;
        }

        return hash_hmac('sha256', request()->session()->getId(), (string) config('app.key'));
    }
}
