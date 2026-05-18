<?php

namespace Tests\Unit\Infrastructure\Listeners;

use App\Events\ContactScoreProcessed;
use App\Listeners\LogContactScoreProcessed;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class LogContactScoreProcessedTest extends TestCase
{
    public function test_logs_contact_score_processed(): void
    {
        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->once();

        Log::shouldReceive('channel')
            ->with('contact')
            ->once()
            ->andReturn($logger);

        $event = new ContactScoreProcessed(
            contactId: 1,
            email: 'john@example.com',
            score: 50,
            status: 'active',
        );

        $listener = new LogContactScoreProcessed();
        $listener->handle($event);
    }

    public function test_logs_correct_context(): void
    {
        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->once();

        Log::shouldReceive('channel')
            ->with('contact')
            ->once()
            ->andReturn($logger);

        $event = new ContactScoreProcessed(
            contactId: 1,
            email: 'john@example.com',
            score: 50,
            status: 'active',
        );

        $listener = new LogContactScoreProcessed();
        $listener->handle($event);
    }
}
