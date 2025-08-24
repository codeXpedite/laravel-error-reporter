# Laravel Error Reporter

A Laravel package that automatically reports errors to webhook endpoints (like n8n, GitHub Issues, etc.) with intelligent deduplication and rate limiting.

## Features

- 🚀 **Automatic Error Reporting**: Automatically captures and reports exceptions to your webhook
- 🔐 **Secure**: Optional secret key authentication for webhook endpoints
- 🎯 **Smart Deduplication**: Uses hash-based identification to prevent duplicate reports
- ⏱️ **Rate Limiting**: Prevents flooding with configurable rate limits
- 📦 **Queue Support**: Asynchronous error reporting via Laravel queues
- 🔧 **Highly Configurable**: Extensive configuration options
- 🧪 **Testing Tools**: Built-in commands for testing your configuration
- 🏷️ **Automatic Tagging**: Generates tags based on error type and custom configuration
- 📊 **Rich Context**: Includes stack traces, request data, and environment information

## Installation

### Step 1: Add the package to your project

Add the package repository to your main `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/codexpedite/laravel-error-reporter"
        }
    ]
}
```

### Step 2: Require the package

```bash
composer require codexpedite/laravel-error-reporter
```

### Step 3: Publish the configuration

```bash
php artisan vendor:publish --tag=error-reporter-config
```

### Step 4: Configure your environment

Add these values to your `.env` file:

```env
# Enable/disable error reporting
ERROR_REPORTER_ENABLED=true

# Your webhook endpoint URL (required)
ERROR_REPORTER_WEBHOOK_URL=https://your-n8n-instance.com/webhook/laravel-errors

# Optional: Repository name (auto-detected from APP_URL if not set)
ERROR_REPORTER_REPOSITORY=your-project-name

# Optional: Secret key for webhook authentication
ERROR_REPORTER_SECRET=your-secret-key-here

# Optional: Use queue for async reporting
ERROR_REPORTER_USE_QUEUE=false
ERROR_REPORTER_QUEUE=default
```

## Usage

### Automatic Error Reporting

Once installed and configured, the package will automatically report all unhandled exceptions in production (or configured environments).

### Manual Error Reporting

You can also manually report errors:

```php
use CodeXpedite\ErrorReporter\Facades\ErrorReporter;

try {
    // Your code here
} catch (\Exception $e) {
    ErrorReporter::report($e, ['custom' => 'context']);
    
    // Still handle the exception as needed
    throw $e;
}
```

### Integration with Exception Handler

To ensure all exceptions are reported, add this to your `app/Exceptions/Handler.php`:

```php
use CodeXpedite\ErrorReporter\Facades\ErrorReporter;

public function report(Throwable $exception)
{
    parent::report($exception);
    
    // Report to webhook if in production
    if ($this->shouldReport($exception) && app()->environment('production')) {
        ErrorReporter::report($exception);
    }
}
```

## Testing

### Test Configuration

Check your configuration without sending data:

```bash
php artisan error-reporter:test --dry-run
```

### Test Webhook Connection

Test the webhook connection with a test payload:

```bash
php artisan error-reporter:test
```

### Send Real Test Exception

Send a real test exception to your webhook:

```bash
php artisan error-reporter:test --real
```

## Configuration Options

The configuration file (`config/error-reporter.php`) provides extensive customization:

### Core Settings

- `enabled`: Enable/disable the error reporter
- `webhook_url`: Your webhook endpoint URL
- `repository`: Repository/project name for identification
- `secret_key`: Optional authentication key
- `environments`: Array of environments where reporting is active

### Performance Options

- `use_queue`: Send reports asynchronously via queue
- `queue_name`: Specific queue to use
- `rate_limiting`: Prevent duplicate reports within time window
- `http.timeout`: HTTP request timeout
- `http.retry_times`: Number of retry attempts
- `http.retry_delay`: Delay between retries (ms)

### Content Options

- `ignore`: Array of exception classes to ignore
- `additional_tags`: Extra tags to add to all reports
- `include_request_data`: Include request information
- `sensitive_keys`: Keys to mask in request data
- `stack_trace_lines`: Number of stack trace lines to include

## Webhook Payload Format

The package sends the following JSON structure to your webhook:

```json
{
    "repository": "your-project-name",
    "issueTitle": "Exception: Error message (file.php line 123)",
    "issueTags": ["bug", "error", "hash-1a2b3c4d", "exception"],
    "issueMessage": "Detailed markdown-formatted error report..."
}
```

### Hash Generation

Each error gets a unique hash based on:
- Exception class
- File path
- Line number

Format: `hash-XXXXXXXX` (8 character hex string)

This allows your webhook handler to identify duplicate errors and update existing issues rather than creating new ones.

## Security Considerations

### Sensitive Data

The package automatically masks sensitive fields in request data:
- password
- password_confirmation
- credit_card
- cvv
- token
- secret
- api_key

You can add more fields in the configuration.

### Authentication

If your webhook requires authentication, set the `ERROR_REPORTER_SECRET` in your `.env`. This will be sent as the `X-Laravel-Secret` header.

## Queue Support

For better performance, enable queue support:

1. Set `ERROR_REPORTER_USE_QUEUE=true` in `.env`
2. Ensure your queue workers are running
3. Optionally set a specific queue with `ERROR_REPORTER_QUEUE`

The job will retry 3 times with exponential backoff (10s, 30s, 60s).

## Troubleshooting

### Webhook not receiving data

1. Check if error reporting is enabled: `ERROR_REPORTER_ENABLED=true`
2. Verify webhook URL is correct
3. Test with: `php artisan error-reporter:test`
4. Check Laravel logs for error reporter messages

### Too many duplicate reports

Adjust rate limiting in config:
```php
'rate_limiting' => [
    'enabled' => true,
    'cache_minutes' => 10, // Increase this value
],
```

### Errors not being reported

Check ignored exceptions in config:
```php
'ignore' => [
    // Remove or comment out exception classes you want to report
],
```

## Example Webhook Handlers

### n8n Webhook Node

1. Create HTTP Request node with webhook trigger
2. Parse the JSON payload
3. Create or update GitHub issues based on hash tag

### Custom Laravel Endpoint

```php
Route::post('/webhook/errors', function (Request $request) {
    $payload = $request->validate([
        'repository' => 'required|string',
        'issueTitle' => 'required|string',
        'issueTags' => 'required|array',
        'issueMessage' => 'required|string',
    ]);
    
    // Process the error report
    // Check for existing issue by hash tag
    // Create or update as needed
    
    return response()->json([
        'success' => true,
        'message' => 'Error report received'
    ]);
});
```

## License

MIT License. See LICENSE file for details.

## Support

For issues or questions, please create an issue on GitHub or contact support.