# Laravel Server Analytics

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gabrieldav1108/laravel-server-analytics.svg?style=flat-square)](https://packagist.org/packages/gabrieldav1108/laravel-server-analytics)
[![Tests](https://img.shields.io/github/actions/workflow/status/Gabrieldav1108/laravel-server-analytics/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Gabrieldav1108/laravel-server-analytics/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/gabrieldav1108/laravel-server-analytics.svg?style=flat-square)](LICENSE)

**Know which screens your users open, who logs in and what they do, without a
tracking script and without handing the data to a third party.**

Laravel Server Analytics records usage on the server: every page view, every
login, logout and failed login, and the business actions you name in your
code. Events wait in Redis and are sent every minute, in batches, to an
endpoint you own. Ad blockers do not affect it, and the browser never sees it.

It collects and ships. Storing and charting the events is up to the endpoint,
which follows the [contract](#endpoint-contract) below.

## Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Configuration](#configuration)
- [Endpoint contract](#endpoint-contract)
- [Privacy](#privacy)
- [Development](#development)
- [Changelog](#changelog)
- [License](#license)

## Features

- **Page views.** The route pattern (`invoices/{invoice}/edit`), the HTTP
  status and the response time of every full page request. The real URL and
  the query string are never recorded.
- **Sessions.** Login, logout and failed login, from Laravel's authentication
  events.
- **Business actions.** `Analytics::action('invoice.cancelled', $invoice)`
  records the action name and the model type and key.
- **Multi-tenant aware.** Tag every event with the current tenant and user.
- **Never in the way.** Page views are recorded after the response is sent.
  If Redis is down, the event is dropped with a warning and the request goes
  on. If the endpoint is down, events wait in Redis and are retried the next
  minute.
- **Off by default.** Nothing is recorded until an endpoint and a token are
  set, so local and development environments stay quiet.

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.2 or higher |
| Laravel | 12.x or 13.x |
| Redis | Any version supported by Laravel |
| Scheduler | `php artisan schedule:run` in the cron |

## Installation

Install the package with Composer:

```bash
composer require gabrieldav1108/laravel-server-analytics
```

Point it to your endpoint:

```env
SERVER_ANALYTICS_ENDPOINT=https://analytics.example.com/api/v1/events
SERVER_ANALYTICS_TOKEN=your-token
```

The service provider is auto-discovered. Out of the box it:

- prepends the `RecordPageView` middleware to the `web` middleware group;
- listens to the `Login`, `Logout` and `Failed` authentication events;
- schedules `server-analytics:flush` every minute, without overlapping, while
  the endpoint and the token are set.

Optionally, publish the configuration file:

```bash
php artisan vendor:publish --tag=server-analytics-config
```

## Usage

### Business actions

```php
use Gabrieldav1108\ServerAnalytics\Facades\Analytics;

Analytics::action('invoice.cancelled', $invoice);
Analytics::action('report.exported');
```

Only the action name and the model type and key are recorded, never its
attributes. Name actions as `entity.past_participle`.

### Identifying the tenant and the user

Without a resolver, events carry `Auth::id()` and no tenant. Register a
resolver to set both:

```php
use Gabrieldav1108\ServerAnalytics\Actor;
use Gabrieldav1108\ServerAnalytics\ServerAnalytics;

public function boot(): void
{
    ServerAnalytics::resolveActorUsing(fn (): Actor => new Actor(
        tenant: auth()->user()?->company_id,
        userId: (string) auth()->id(),
    ));
}
```

A resolver that throws is treated as anonymous, and the request goes on.

### Routes outside the `web` group

Only the groups listed in `middleware_groups` record page views. Add the
middleware to any other route or group yourself:

```php
use Gabrieldav1108\ServerAnalytics\Http\Middleware\RecordPageView;

Route::middleware(['api', RecordPageView::class])->group(...);
```

### Sending

`server-analytics:flush` runs every minute:

- sends batches of up to 500 events until the queue is empty;
- keeps the events in Redis when the endpoint fails or cannot be reached, and
  tries again the next minute;
- drops events older than `backlog_days`, with a warning in the log.

## Configuration

| Option | Env | Default | Description |
|---|---|---|---|
| `enabled` | `SERVER_ANALYTICS_ENABLED` | `true` | Turns recording and sending off |
| `endpoint` | `SERVER_ANALYTICS_ENDPOINT` | `null` | Full URL that receives the batches |
| `token` | `SERVER_ANALYTICS_TOKEN` | `null` | Sent as `Authorization: Bearer` |
| `redis_connection` | `SERVER_ANALYTICS_REDIS_CONNECTION` | `default` | Redis connection that holds the queue |
| `queue_key` | | `server-analytics:events` | Redis list name |
| `batch_size` | | `500` | Events per request |
| `timeout_seconds` | | `10` | Request timeout |
| `backlog_days` | | `7` | Older events are dropped instead of sent |
| `middleware_groups` | | `['web']` | Groups that record page views |

## Endpoint contract

`POST {endpoint}` with `Authorization: Bearer {token}` and a JSON body:

```json
{
  "events": [
    {
      "event_id": "0196...",
      "type": "page.view",
      "name": "invoices/{invoice}/edit",
      "tenant": "ACME",
      "user_id": "42",
      "occurred_at": "2026-11-16T14:03:27.123-03:00",
      "duration_ms": 120,
      "http_status": 200,
      "ip": "203.0.113.10",
      "session_hash": "<sha256>"
    }
  ]
}
```

| Field | Content |
|---|---|
| `event_id` | UUID v7. The same event may arrive twice after a retry; use it to deduplicate. |
| `type` | `page.view`, `session.login`, `session.logout`, `session.login_failed` or `action` |
| `name` | Route pattern for `page.view`, guard name for `session.*`, action name for `action` |
| `tenant`, `user_id` | From the resolver. `null` when unknown. |
| `occurred_at` | ISO 8601 with milliseconds and offset |
| `duration_ms`, `http_status` | Only on `page.view` |
| `subject_type`, `subject_id` | Only on `action`: the model's morph class and key |
| `ip` | Client IP, as resolved by Laravel (configure trusted proxies) |
| `session_hash` | HMAC-SHA256 of the session ID with the application key, to group a visit |

Any `2xx` response removes the batch from the queue. Anything else, or no
response, keeps it for the next run. Reject invalid events inside a `2xx`
response rather than refusing the whole batch, because a refused batch is sent
again.

## Privacy

- The real URL, the query string, request bodies and model attributes are
  never recorded.
- A failed login records the guard, the IP and the time, never the typed
  credentials.
- The session ID is never sent, only a keyed hash of it.
- The IP is sent because access logs usually need it. Drop it on the endpoint
  if you do not.

## Development

```bash
composer install
composer test
composer analyse
composer format
```

Tests run against an in-memory fake of Redis (`tests/Support/FakeRedis.php`).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE](LICENSE).
