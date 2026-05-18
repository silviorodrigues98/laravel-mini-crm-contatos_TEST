<?php

namespace App\Listeners;

use App\Events\ContactScoreProcessed;
use Illuminate\Support\Facades\Log;

class LogContactScoreProcessed
{
    public function handle(ContactScoreProcessed $event): void
    {
        Log::channel('contact')->info('Contact score processed', [
            'id' => $event->contactId,
            'email' => $event->email,
            'score' => $event->score,
            'status' => $event->status,
        ]);
    }
}
