<?php

declare(strict_types=1);

return [

    /*
    | Set to false to stop recording and sending events. Recording also stays
    | off while "endpoint" or "token" is empty, which is the expected setup
    | for local and development environments.
    */
    'enabled' => (bool) env('SERVER_ANALYTICS_ENABLED', true),

    /*
    | Full URL that receives the batches, and the Bearer token sent with them.
    | See "Endpoint contract" in the README.
    */
    'endpoint' => env('SERVER_ANALYTICS_ENDPOINT'),

    'token' => env('SERVER_ANALYTICS_TOKEN'),

    /*
    | Redis connection and list that hold the events until they are sent.
    */
    'redis_connection' => env('SERVER_ANALYTICS_REDIS_CONNECTION', 'default'),

    'queue_key' => 'server-analytics:events',

    /*
    | Events per request to the endpoint, and request timeout in seconds.
    */
    'batch_size' => 500,

    'timeout_seconds' => 10,

    /*
    | Events older than this are dropped instead of sent, with a warning in
    | the log. Keep it in line with what the endpoint accepts.
    */
    'backlog_days' => 7,

    /*
    | Middleware groups that record page views. Use [] to register
    | RecordPageView manually.
    */
    'middleware_groups' => ['web'],

];
