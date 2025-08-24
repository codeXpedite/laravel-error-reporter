<?php

namespace CodeXpedite\ErrorReporter;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use CodeXpedite\ErrorReporter\Jobs\ReportErrorJob;

class ErrorReporter
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function report(\Throwable $exception, array $context = []): void
    {
        if (!$this->shouldReport($exception)) {
            return;
        }

        $payload = $this->preparePayload($exception, $context);

        if ($this->config['use_queue'] ?? false) {
            dispatch(new ReportErrorJob($payload, $this->config));
        } else {
            $this->sendReport($payload);
        }
    }

    public function sendReport(array $payload): void
    {
        if (empty($this->config['webhook_url'])) {
            Log::warning('Error Reporter: Webhook URL is not configured');
            return;
        }

        try {
            $request = Http::timeout($this->config['http']['timeout'] ?? 10)
                ->retry(
                    $this->config['http']['retry_times'] ?? 3,
                    $this->config['http']['retry_delay'] ?? 100
                );

            if ($secretKey = $this->config['secret_key'] ?? null) {
                $request->withHeaders([
                    'X-Laravel-Secret' => $secretKey
                ]);
            }

            $response = $request->post($this->config['webhook_url'], $payload);

            if ($response->successful()) {
                Log::info('Error reported successfully', [
                    'hash' => $payload['issueTags'][2] ?? 'unknown',
                    'response' => $response->json()
                ]);
            } else {
                Log::error('Failed to report error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error Reporter exception: ' . $e->getMessage());
        }
    }

    protected function shouldReport(\Throwable $exception): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        if (!in_array(app()->environment(), $this->config['environments'] ?? ['production'])) {
            return false;
        }

        foreach ($this->config['ignore'] ?? [] as $ignoredClass) {
            if ($exception instanceof $ignoredClass) {
                return false;
            }
        }

        if ($this->config['rate_limiting']['enabled'] ?? true) {
            $hashTag = $this->generateHash($exception);
            $cacheKey = 'error_reporter_' . $hashTag;
            
            if (Cache::has($cacheKey)) {
                return false;
            }

            Cache::put($cacheKey, true, now()->addMinutes(
                $this->config['rate_limiting']['cache_minutes'] ?? 5
            ));
        }

        return true;
    }

    protected function preparePayload(\Throwable $exception, array $context = []): array
    {
        return [
            'repository' => $this->getRepositoryName(),
            'issueTitle' => $this->formatTitle($exception),
            'issueTags' => $this->generateTags($exception),
            'issueMessage' => $this->formatMessage($exception, $context)
        ];
    }

    protected function getRepositoryName(): string
    {
        if ($repository = $this->config['repository'] ?? null) {
            return $repository;
        }

        $url = config('app.url');
        $host = parse_url($url, PHP_URL_HOST) ?? 'unknown';
        
        return str_replace('.', '-', $host);
    }

    protected function formatTitle(\Throwable $exception): string
    {
        $file = basename($exception->getFile());
        $message = Str::limit($exception->getMessage(), 80);

        return sprintf(
            '%s: %s (%s line %d)',
            class_basename($exception),
            $message,
            $file,
            $exception->getLine()
        );
    }

    protected function generateTags(\Throwable $exception): array
    {
        $tags = [
            'bug',
            'error',
            $this->generateHash($exception),
            strtolower(class_basename($exception))
        ];

        if ($additionalTags = $this->config['additional_tags'] ?? []) {
            $tags = array_merge($tags, $additionalTags);
        }

        return array_unique($tags);
    }

    protected function generateHash(\Throwable $exception): string
    {
        $identifier = sprintf(
            '%s:%s:%d',
            get_class($exception),
            $exception->getFile(),
            $exception->getLine()
        );

        return 'hash-' . substr(md5($identifier), 0, 8);
    }

    protected function formatMessage(\Throwable $exception, array $context = []): string
    {
        $trace = $this->formatStackTrace($exception);
        
        $message = sprintf(
            "**Error:** %s\n\n" .
            "**File:** %s\n" .
            "**Line:** %d\n\n" .
            "**Stack Trace:**\n```\n%s\n```\n\n",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $trace
        );

        if ($this->config['include_request_data'] ?? true) {
            $message .= $this->formatRequestData();
        }

        if (!empty($context)) {
            $message .= "\n\n**Context:**\n```json\n" . json_encode($context, JSON_PRETTY_PRINT) . "\n```";
        }

        $message .= sprintf(
            "\n\n**Environment:** %s\n" .
            "**PHP Version:** %s\n" .
            "**Laravel Version:** %s\n" .
            "**Time:** %s UTC",
            app()->environment(),
            PHP_VERSION,
            app()->version(),
            now()->toDateTimeString()
        );

        return $message;
    }

    protected function formatStackTrace(\Throwable $exception): string
    {
        $lines = $this->config['stack_trace_lines'] ?? 10;
        
        return collect($exception->getTrace())
            ->take($lines)
            ->map(fn($item, $index) => sprintf(
                "#%d %s(%d): %s%s%s()",
                $index,
                $item['file'] ?? 'unknown',
                $item['line'] ?? 0,
                $item['class'] ?? '',
                isset($item['class']) ? ($item['type'] ?? '::') : '',
                $item['function'] ?? 'unknown'
            ))->implode("\n");
    }

    protected function formatRequestData(): string
    {
        if (!app()->runningInConsole() && request()) {
            $requestData = request()->all();
            
            foreach ($this->config['sensitive_keys'] ?? [] as $key) {
                if (isset($requestData[$key])) {
                    $requestData[$key] = '***MASKED***';
                }
            }

            return sprintf(
                "**URL:** %s\n" .
                "**Method:** %s\n" .
                "**IP:** %s\n" .
                "**User Agent:** %s\n" .
                "**User:** %s\n" .
                "**Request Data:**\n```json\n%s\n```\n",
                request()->fullUrl() ?? 'N/A',
                request()->method() ?? 'N/A',
                request()->ip() ?? 'N/A',
                request()->userAgent() ?? 'N/A',
                auth()->check() ? (auth()->user()->email ?? auth()->id()) : 'Guest',
                !empty($requestData) ? json_encode($requestData, JSON_PRETTY_PRINT) : 'N/A'
            );
        }

        return "**Running in Console**\n";
    }
}