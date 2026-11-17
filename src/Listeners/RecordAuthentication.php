<?php

declare(strict_types=1);

namespace Gabrieldav1108\ServerAnalytics\Listeners;

use Gabrieldav1108\ServerAnalytics\Actor;
use Gabrieldav1108\ServerAnalytics\Enums\EventType;
use Gabrieldav1108\ServerAnalytics\Recorder;
use Gabrieldav1108\ServerAnalytics\ServerAnalytics;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;

class RecordAuthentication
{
    public function __construct(private readonly Recorder $recorder) {}

    public function login(Login $event): void
    {
        $this->recorder->record(EventType::Login, $event->guard, $this->actorFor($event->user));
    }

    public function logout(Logout $event): void
    {
        $this->recorder->record(EventType::Logout, $event->guard, $this->actorFor($event->user));
    }

    public function failed(Failed $event): void
    {
        $this->recorder->record(EventType::LoginFailed, $event->guard, Actor::anonymous());
    }

    private function actorFor(?Authenticatable $user): Actor
    {
        $currentActor = ServerAnalytics::currentActor();

        if ($user === null) {
            return $currentActor;
        }

        return new Actor(
            tenant: $currentActor->tenant,
            userId: ServerAnalytics::stringOrNull($user->getAuthIdentifier()),
        );
    }
}
