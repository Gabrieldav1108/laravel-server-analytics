<?php

declare(strict_types=1);

namespace Gabrieldav1108\ServerAnalytics\Http\Middleware;

use Closure;
use Gabrieldav1108\ServerAnalytics\Enums\EventType;
use Gabrieldav1108\ServerAnalytics\Recorder;
use Gabrieldav1108\ServerAnalytics\ServerAnalytics;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordPageView
{
    private const STARTED_AT_ATTRIBUTE = 'server-analytics.started_at';

    private const NANOSECONDS_PER_MILLISECOND = 1_000_000;

    public function __construct(private readonly Recorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::STARTED_AT_ATTRIBUTE, hrtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $routePattern = $request->route()?->uri();

        if (! $request->isMethod('GET') || $routePattern === null) {
            return;
        }

        $this->recorder->record(EventType::PageView, $routePattern, ServerAnalytics::currentActor(), [
            'http_status' => $response->getStatusCode(),
            'duration_ms' => $this->elapsedMilliseconds($request),
        ]);
    }

    private function elapsedMilliseconds(Request $request): int
    {
        $startedAt = (int) $request->attributes->get(self::STARTED_AT_ATTRIBUTE, hrtime(true));

        return intdiv(hrtime(true) - $startedAt, self::NANOSECONDS_PER_MILLISECOND);
    }
}
