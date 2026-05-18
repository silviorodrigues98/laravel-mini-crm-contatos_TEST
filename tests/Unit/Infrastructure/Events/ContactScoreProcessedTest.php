<?php

namespace Tests\Unit\Infrastructure\Events;

use App\Events\ContactScoreProcessed;
use PHPUnit\Framework\TestCase;

class ContactScoreProcessedTest extends TestCase
{
    public function test_event_creates_with_correct_data(): void
    {
        $event = new ContactScoreProcessed(
            contactId: 1,
            email: 'john@example.com',
            score: 50,
            status: 'active',
        );

        $this->assertSame(1, $event->contactId);
        $this->assertSame('john@example.com', $event->email);
        $this->assertSame(50, $event->score);
        $this->assertSame('active', $event->status);
    }

    public function test_event_broadcasts_on_correct_channel(): void
    {
        $event = new ContactScoreProcessed(
            contactId: 1,
            email: 'john@example.com',
            score: 50,
            status: 'active',
        );

        $this->assertSame('contacts.1', $event->broadcastOn()->name);
    }
}
