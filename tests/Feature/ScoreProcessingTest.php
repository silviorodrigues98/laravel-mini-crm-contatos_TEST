<?php

namespace Tests\Feature;

use App\Events\ContactScoreProcessed;
use App\Infrastructure\Models\Contact;
use App\Jobs\ProcessContactScoreJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScoreProcessingTest extends TestCase
{
    use RefreshDatabase;

    const BASE_URL = '/api/contacts';

    public function test_process_score_returns_202_and_dispatches_job(): void
    {
        Queue::fake();

        $contact = Contact::factory()->create(['status' => 'pending']);

        $response = $this->postJson(self::BASE_URL . '/' . $contact->id . '/process-score');

        $response->assertStatus(202);
        $response->assertJson([
            'message' => 'Score processing queued.',
            'contact_id' => $contact->id,
        ]);

        Queue::assertPushed(ProcessContactScoreJob::class, function ($job) use ($contact) {
            return $job->contactId === $contact->id;
        });
    }

    public function test_process_score_returns_404_for_nonexistent_contact(): void
    {
        $response = $this->postJson(self::BASE_URL . '/999/process-score');

        $response->assertStatus(404);
    }

    public function test_process_score_full_integration_with_sync_queue(): void
    {
        $contact = Contact::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@company.com',
            'phone' => '11999999999',
            'status' => 'pending',
            'score' => 0,
        ]);

        $response = $this->postJson(self::BASE_URL . '/' . $contact->id . '/process-score');
        $response->assertStatus(202);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'status' => 'active',
            'score' => 50,
        ]);
    }

    public function test_score_processing_dispatches_contact_score_processed_event(): void
    {
        Event::fake([ContactScoreProcessed::class]);

        $contact = Contact::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@company.com',
            'phone' => '11999999999',
            'status' => 'pending',
        ]);

        $this->postJson(self::BASE_URL . '/' . $contact->id . '/process-score');

        Event::assertDispatched(ContactScoreProcessed::class, function ($event) use ($contact) {
            return $event->contactId === $contact->id
                && $event->email === $contact->email
                && $event->score === 50
                && $event->status === 'active';
        });
    }
}
