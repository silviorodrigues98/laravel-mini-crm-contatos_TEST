<?php

namespace App\Jobs;

use App\Events\ContactScoreProcessed;
use Application\UseCases\ProcessScoreUseCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessContactScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 2;

    public function __construct(
        public readonly int $contactId,
    ) {
    }

    /**
     * Seconds to wait between retry attempts.
     * Provides exponential backoff for transient failures.
     */
    public function backoff(): array
    {
        return [2, 5, 15];
    }

    public function handle(ProcessScoreUseCase $useCase): void
    {
        sleep(rand(1, 2));

        try {
            $contact = $useCase->execute($this->contactId);

            event(new ContactScoreProcessed(
                contactId: $contact->id(),
                email: $contact->email()->value,
                score: $contact->score()->value,
                status: $contact->status()->value,
            ));
        } catch (\Throwable $e) {
            // Fallback: if execute() persisted "processing" but the
            // terminal save failed, we must explicitly persist "failed"
            // to prevent the contact from being stuck in "processing"
            // permanently (CR-02).
            try {
                $useCase->markAsFailed($this->contactId);
            } catch (\Throwable $inner) {
                // Secondary failure is unrecoverable — the contact may
                // remain stuck in "processing". Logging is needed for
                // operational visibility.
                Log::channel('contact')->error(
                    'Failed to mark contact as failed after processing error',
                    [
                        'contact_id' => $this->contactId,
                        'original_error' => $e->getMessage(),
                        'fallback_error' => $inner->getMessage(),
                    ]
                );
            }

            // Permanent conditions (deleted contact, already processed)
            // should not retry or pollute failed_jobs.
            if ($e instanceof \DomainException) {
                return;
            }

            if (str_contains($e->getMessage(), 'Contact not found')) {
                return;
            }

            throw $e;
        }
    }
}
