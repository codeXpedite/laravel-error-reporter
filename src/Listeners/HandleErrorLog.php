<?php

namespace CodeXpedite\ErrorReporter\Listeners;

use Illuminate\Log\Events\MessageLogged;
use CodeXpedite\ErrorReporter\Facades\ErrorReporter;

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