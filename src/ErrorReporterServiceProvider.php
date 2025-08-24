<?php

namespace CodeXpedite\ErrorReporter;

use Illuminate\Support\ServiceProvider;
use CodeXpedite\ErrorReporter\Commands\TestErrorReporterCommand;

class ErrorReporterServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/error-reporter.php', 'error-reporter'
        );

        $this->app->singleton('error-reporter', function ($app) {
            return new ErrorReporter(
                config('error-reporter')
            );
        });

        $this->app->alias('error-reporter', ErrorReporter::class);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/error-reporter.php' => config_path('error-reporter.php'),
            ], 'error-reporter-config');

            $this->commands([
                TestErrorReporterCommand::class,
            ]);
        }

        $this->registerExceptionHandler();
    }

    protected function registerExceptionHandler()
    {
        // Event listener'ı kaldırıyoruz çünkü bootstrap/app.php'de zaten handle ediliyor
        // Böylece çift raporlama olmaz
    }
}