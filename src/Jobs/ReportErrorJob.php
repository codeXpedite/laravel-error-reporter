<?php

namespace CodeXpedite\ErrorReporter\Jobs;

use CodeXpedite\ErrorReporter\ErrorReporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReportErrorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $payload;

    protected array $config;

    public $tries = 3;

    public $backoff = [10, 30, 60];

    public function __construct(array $payload, array $config)
    {
        $this->payload = $payload;
        $this->config = $config;

        if ($queueName = $config['queue_name'] ?? null) {
            $this->onQueue($queueName);
        }
    }

    public function handle()
    {
        $reporter = new ErrorReporter($this->config);
        $reporter->sendReport($this->payload);
    }

    public function failed(\Throwable $exception)
    {
        \Log::error('Failed to send error report to webhook', [
            'exception' => $exception->getMessage(),
            'payload' => $this->payload,
        ]);
    }
}
