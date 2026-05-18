<?php

namespace Tests\Unit\Infrastructure\Listeners;

use App\Events\ContactScoreProcessed;
use App\Listeners\LogContactScoreProcessed;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class LogContactScoreProcessedTest extends TestCase
{
    public function test_logs_correct_contact_data(): void
    {
        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'Contact score processed'
                    && ($context['id'] ?? null) === 1
                    && ($context['email'] ?? null) === 'john@example.com'
                    && ($context['score'] ?? null) === 50
                    && ($context['status'] ?? null) === 'active';
            });

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
