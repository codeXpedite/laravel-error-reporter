<?php

namespace CodeXpedite\ErrorReporter\Listeners;

use CodeXpedite\ErrorReporter\Facades\ErrorReporter;
use Illuminate\Log\Events\MessageLogged;

class HandleErrorLog
{
    public function handle(MessageLogged $event)
    {
        if ($event->level === 'error' && isset($event->context['exception'])) {
            $exception = $event->context['exception'];

            if ($exception instanceof \Throwable) {
                ErrorReporter::report($exception, $event->context);
            }
        }
    }
}
