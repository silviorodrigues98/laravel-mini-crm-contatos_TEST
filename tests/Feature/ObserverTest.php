<?php

namespace Tests\Feature;

use App\Infrastructure\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_observer_normalizes_phone_on_saving(): void
    {
        $contact = Contact::factory()->create([
            'phone' => '(11) 99999-9999',
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'phone' => '11999999999',
        ]);
    }

    public function test_observer_does_not_modify_already_clean_phone(): void
    {
        $contact = Contact::factory()->create([
            'phone' => '11999999999',
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'phone' => '11999999999',
        ]);
    }
}
