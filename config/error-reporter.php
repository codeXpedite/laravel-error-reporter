<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Error Reporter Enabled
    |--------------------------------------------------------------------------
    |
    | This option determines whether the error reporter is enabled.
    | You can disable it completely by setting this to false.
    |
    */
    'enabled' => env('ERROR_REPORTER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Webhook URL
    |--------------------------------------------------------------------------
    |
    | The webhook URL where error reports will be sent.
    | This should be your n8n webhook endpoint or any other webhook service.
    |
    */
    'webhook_url' => env('ERROR_REPORTER_WEBHOOK_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Repository Name
    |--------------------------------------------------------------------------
    |
    | The repository name that will be sent with error reports.
    | If not set, it will try to use the app URL domain.
    |
    */
    'repository' => env('ERROR_REPORTER_REPOSITORY', null),

    /*
    |--------------------------------------------------------------------------
    | Secret Key
    |--------------------------------------------------------------------------
    |
    | Optional secret key for webhook authentication.
    | If set, it will be sent as X-Laravel-Secret header.
    |
    */
    'secret_key' => env('ERROR_REPORTER_SECRET', null),

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    |
    | List of environments where error reporting is active.
    | By default, only production environment is enabled.
    |
    */
    'environments' => ['production'],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Whether to use queue for sending error reports.
    | If true, reports will be sent asynchronously.
    |
    */
    'use_queue' => env('ERROR_REPORTER_USE_QUEUE', false),

    'queue_name' => env('ERROR_REPORTER_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting configuration to prevent flooding.
    | cache_minutes: How long to cache the same error (in minutes)
    |
    */
    'rate_limiting' => [
        'enabled' => true,
        'cache_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the HTTP client.
    |
    */
    'http' => [
        'timeout' => 10,
        'retry_times' => 3,
        'retry_delay' => 100, // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    |
    | List of exception classes that should not be reported.
    |
    */
    'ignore' => [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Validation\ValidationException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional Tags
    |--------------------------------------------------------------------------
    |
    | Additional tags that will be added to all error reports.
    |
    */
    'additional_tags' => [],

    /*
    |--------------------------------------------------------------------------
    | Include Request Data
    |--------------------------------------------------------------------------
    |
    | Whether to include request data in error reports.
    | Be careful with sensitive data!
    |
    */
    'include_request_data' => true,

    /*
    |--------------------------------------------------------------------------
    | Sensitive Data Keys
    |--------------------------------------------------------------------------
    |
    | List of keys that should be masked in request data.
    |
    */
    'sensitive_keys' => [
        'password',
        'password_confirmation',
        'credit_card',
        'cvv',
        'token',
        'secret',
        'api_key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stack Trace Lines
    |--------------------------------------------------------------------------
    |
    | Number of stack trace lines to include in the error report.
    |
    */
    'stack_trace_lines' => 10,
];
