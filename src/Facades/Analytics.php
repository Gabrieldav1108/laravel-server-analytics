<?php

declare(strict_types=1);

namespace Gabrieldav1108\ServerAnalytics\Facades;

use Gabrieldav1108\ServerAnalytics\Recorder;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void action(string $name, ?\Illuminate\Database\Eloquent\Model $subject = null)
 *
 * @see Recorder
 */
class Analytics extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Recorder::class;
    }
}
