# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Essential Commands

### Development & Testing
```bash
# Test the error reporter configuration
php artisan error-reporter:test --dry-run  # Test configuration without sending data
php artisan error-reporter:test             # Test webhook connection with test payload
php artisan error-reporter:test --real      # Send a real test exception to webhook

# Run PHPUnit tests (when implemented)
vendor/bin/phpunit

# Run tests with Testbench
composer test
```

### Package Development
```bash
# Install dependencies
composer install

# Update autoload after changes
composer dump-autoload

# Publish configuration (from main Laravel app)
php artisan vendor:publish --tag=error-reporter-config
```

## High-Level Architecture

### Package Structure
This is a **Laravel package** for automatic error reporting to webhook endpoints (n8n, GitHub Issues, etc.) with intelligent deduplication and rate limiting.

### Core Components

#### Service Provider (`ErrorReporterServiceProvider`)
- Registers the error reporter singleton
- Publishes configuration file
- Registers console commands
- Sets up event listener for Laravel's error events

#### Main Reporter (`ErrorReporter`)
- Core error reporting logic with hash-based deduplication
- Rate limiting using Laravel's cache
- Configurable webhook payload generation
- Support for both synchronous and queued reporting

#### Queue Job (`ReportErrorJob`)
- Asynchronous error reporting via Laravel queues
- Exponential backoff retry logic (10s, 30s, 60s)
- Maximum 3 retry attempts

#### Event Listener (`HandleErrorLog`)
- Automatically captures Laravel's `MessageLogged` events
- Filters for error-level messages with exceptions
- Integrates with the main reporter

### Key Features Implementation

#### Hash-Based Deduplication
```php
// Hash generation based on:
// - Exception class name
// - File path
// - Line number
// Format: hash-XXXXXXXX (8 character hex)
$identifier = sprintf('%s:%s:%d', get_class($exception), $exception->getFile(), $exception->getLine());
$hash = 'hash-' . substr(md5($identifier), 0, 8);
```

#### Rate Limiting Pattern
- Uses Laravel's cache to prevent duplicate reports
- Configurable time window (default: 5 minutes)
- Cache key format: `error_reporter_hash-XXXXXXXX`

#### Webhook Payload Structure
```json
{
    "repository": "project-name",
    "issueTitle": "Exception: Message (file.php line 123)",
    "issueTags": ["bug", "error", "hash-XXXXXXXX", "exception-type"],
    "issueMessage": "Markdown-formatted error details..."
}
```

## Configuration

### Environment Variables
- `ERROR_REPORTER_ENABLED`: Enable/disable reporting
- `ERROR_REPORTER_WEBHOOK_URL`: Target webhook endpoint
- `ERROR_REPORTER_REPOSITORY`: Project identifier (auto-detected from APP_URL if not set)
- `ERROR_REPORTER_SECRET`: Optional authentication key (sent as X-Laravel-Secret header)
- `ERROR_REPORTER_USE_QUEUE`: Enable async reporting
- `ERROR_REPORTER_QUEUE`: Queue name for async jobs

### Configuration Arrays
- `environments`: Array of environments where reporting is active
- `ignore`: Exception classes to ignore
- `sensitive_keys`: Request data keys to mask
- `additional_tags`: Extra tags for all reports

## Integration Points

### Laravel Exception Handler
The package can be integrated in three ways:
1. **Automatic**: Via event listener (registers automatically)
2. **Manual**: Direct calls to `ErrorReporter::report($exception)`
3. **Exception Handler**: Add to `app/Exceptions/Handler.php`

### Queue System
- Uses Laravel's queue system for async reporting
- Configurable queue name
- Job implements retry logic with exponential backoff

### HTTP Client
- Uses Laravel's HTTP facade with retry logic
- Configurable timeout and retry attempts
- Optional authentication via headers

## Testing Strategy

### Test Command Features
1. **Configuration Test** (`--dry-run`): Validates configuration and generates sample payload
2. **Connection Test** (default): Sends test payload to webhook
3. **Real Exception Test** (`--real`): Reports actual test exception

### Validation Points
- Configuration completeness
- Webhook URL accessibility
- Authentication header presence
- Payload structure validity

## Security Considerations

### Sensitive Data Protection
- Automatic masking of sensitive request fields
- Configurable sensitive keys list
- No storage of error data locally

### Authentication
- Optional secret key authentication
- Header-based authentication (X-Laravel-Secret)
- Webhook endpoint validation

## Common Development Tasks

### Adding New Ignored Exception
Edit `config/error-reporter.php`:
```php
'ignore' => [
    // Add new exception class here
    \Your\Custom\Exception::class,
]
```

### Customizing Payload
Override methods in `ErrorReporter` class:
- `formatTitle()`: Customize issue title format
- `generateTags()`: Modify tag generation logic
- `formatMessage()`: Change message structure

### Debugging Reports
Check Laravel logs for detailed debug information:
- Rate limiting decisions
- Environment checks
- Webhook response details

## Package Dependencies
- Laravel 9.x, 10.x, 11.x, or 12.x
- PHP 8.0+
- Illuminate Support, HTTP, and Queue components
- Orchestra Testbench for testing (dev dependency)