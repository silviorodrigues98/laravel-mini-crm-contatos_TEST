<?php

namespace App\Jobs;

use Application\UseCases\ProcessScoreUseCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessContactScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $contactId,
    ) {
    }

    public function handle(ProcessScoreUseCase $useCase): void
    {
        sleep(rand(1, 2));
        $useCase->execute($this->contactId);
    }
}
