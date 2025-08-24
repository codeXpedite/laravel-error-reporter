<?php

namespace CodeXpedite\ErrorReporter\Commands;

use Illuminate\Console\Command;
use CodeXpedite\ErrorReporter\Facades\ErrorReporter;
use Illuminate\Support\Facades\Http;

class TestErrorReporterCommand extends Command
{
    protected $signature = 'error-reporter:test 
                            {--real : Send a real test exception to the webhook}
                            {--dry-run : Test the configuration without sending}';

    protected $description = 'Test the error reporter configuration and webhook';

    public function handle()
    {
        $this->info('Testing Error Reporter Configuration...');
        $this->newLine();

        $config = config('error-reporter');

        $this->table(
            ['Configuration', 'Value'],
            [
                ['Enabled', $config['enabled'] ? '✓ Yes' : '✗ No'],
                ['Webhook URL', $config['webhook_url'] ?: '✗ Not configured'],
                ['Repository', $config['repository'] ?: 'Auto-detect from APP_URL'],
                ['Secret Key', $config['secret_key'] ? '✓ Configured' : '✗ Not set'],
                ['Use Queue', $config['use_queue'] ? '✓ Yes' : '✗ No'],
                ['Rate Limiting', $config['rate_limiting']['enabled'] ? '✓ Enabled' : '✗ Disabled'],
                ['Environment', app()->environment()],
                ['Active Environments', implode(', ', $config['environments'])],
            ]
        );

        if (!$config['enabled']) {
            $this->error('Error Reporter is disabled. Set ERROR_REPORTER_ENABLED=true in .env');
            return 1;
        }

        if (!$config['webhook_url']) {
            $this->error('Webhook URL is not configured. Set ERROR_REPORTER_WEBHOOK_URL in .env');
            return 1;
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->testPayloadGeneration();
        } elseif ($this->option('real')) {
            $this->sendRealTest();
        } else {
            $this->testWebhookConnection();
        }

        return 0;
    }

    protected function testPayloadGeneration()
    {
        $this->info('Generating test payload...');
        
        try {
            throw new \Exception('This is a test exception from error-reporter:test command');
        } catch (\Exception $e) {
            $reporter = app('error-reporter');
            $reflection = new \ReflectionClass($reporter);
            $method = $reflection->getMethod('preparePayload');
            $method->setAccessible(true);
            
            $payload = $method->invoke($reporter, $e, ['test' => true]);
            
            $this->info('Generated payload:');
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));
        }
    }

    protected function testWebhookConnection()
    {
        $this->info('Testing webhook connection...');
        
        $testPayload = [
            'repository' => config('error-reporter.repository') ?: str_replace('.', '-', parse_url(config('app.url'), PHP_URL_HOST)),
            'issueTitle' => 'Test Error: Connection test from error-reporter:test',
            'issueTags' => ['test', 'error-reporter', 'hash-test' . substr(md5(time()), 0, 4)],
            'issueMessage' => "**Test Error**\n\nThis is a test message from the Laravel Error Reporter package.\n\n" .
                             "**Time:** " . now()->toDateTimeString() . "\n" .
                             "**Environment:** " . app()->environment() . "\n\n" .
                             "*This is a test message and can be safely ignored.*"
        ];

        try {
            $request = Http::timeout(10);
            
            if ($secretKey = config('error-reporter.secret_key')) {
                $request->withHeaders(['X-Laravel-Secret' => $secretKey]);
            }

            $response = $request->post(config('error-reporter.webhook_url'), $testPayload);

            if ($response->successful()) {
                $this->info('✓ Webhook test successful!');
                $this->line('Response: ' . $response->body());
            } else {
                $this->error('✗ Webhook test failed!');
                $this->line('Status: ' . $response->status());
                $this->line('Response: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('✗ Connection failed: ' . $e->getMessage());
        }
    }

    protected function sendRealTest()
    {
        $this->info('Sending real test exception to webhook...');
        
        try {
            throw new \RuntimeException('Test exception from Laravel Error Reporter - This is a test error that can be safely ignored.');
        } catch (\RuntimeException $e) {
            ErrorReporter::report($e, ['source' => 'test_command', 'test' => true]);
            $this->info('✓ Test exception sent to webhook!');
            $this->line('Check your webhook endpoint for the error report.');
        }
    }
}