<?php

declare(strict_types=1);

namespace Gabrieldav1108\ServerAnalytics;

use Closure;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ServerAnalytics
{
    private static ?Closure $actorResolver = null;

    /** @param  (Closure(): ?Actor)|null  $resolver */
    public static function resolveActorUsing(?Closure $resolver): void
    {
        self::$actorResolver = $resolver;
    }

    public static function currentActor(): Actor
    {
        try {
            return self::$actorResolver === null
                ? new Actor(tenant: null, userId: self::stringOrNull(Auth::id()))
                : (self::$actorResolver)() ?? Actor::anonymous();
        } catch (Throwable) {
            return Actor::anonymous();
        }
    }

    public static function enabled(): bool
    {
        return (bool) config('server-analytics.enabled')
            && filled(config('server-analytics.endpoint'))
            && filled(config('server-analytics.token'));
    }

    public static function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }
}
