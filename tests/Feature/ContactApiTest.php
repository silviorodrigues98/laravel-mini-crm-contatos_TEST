<?php

namespace Tests\Feature;

use App\Infrastructure\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    const BASE_URL = '/api/contacts';

    public function test_can_create_a_contact(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '11999999999',
        ];

        $response = $this->postJson(self::BASE_URL, $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'phone', 'score', 'status'],
            ]);

        $response->assertJsonPath('data.name', 'John Doe');
        $response->assertJsonPath('data.email', 'john@example.com');
        $response->assertJsonPath('data.phone', '11999999999');
        $response->assertJsonPath('data.score', 0);
        $response->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('contacts', [
            'email' => 'john@example.com',
            'phone' => '11999999999',
            'score' => 0,
            'status' => 'pending',
        ]);
    }

    public function test_create_returns_validation_errors(): void
    {
        $response = $this->postJson(self::BASE_URL, [
            'name' => '',
            'email' => 'not-an-email',
            'phone' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'phone']);
    }

    public function test_create_returns_422_for_duplicate_email(): void
    {
        Contact::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->postJson(self::BASE_URL, [
            'name' => 'Another Person',
            'email' => 'duplicate@example.com',
            'phone' => '21988887777',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_can_list_contacts_with_pagination(): void
    {
        Contact::factory()->count(15)->create();

        $response = $this->getJson(self::BASE_URL);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $response->assertJsonPath('meta.total', 15);
        $response->assertJsonPath('meta.per_page', 15);
        $response->assertJsonPath('meta.current_page', 1);
    }

    public function test_phone_normalization_on_create(): void
    {
        $response = $this->postJson(self::BASE_URL, [
            'name' => 'Phone Test',
            'email' => 'phone@test.com',
            'phone' => '(11) 99999-9999',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('contacts', [
            'email' => 'phone@test.com',
            'phone' => '11999999999',
        ]);
    }

    public function test_can_show_a_contact(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->getJson(self::BASE_URL . '/' . $contact->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $contact->id);
        $response->assertJsonPath('data.name', $contact->name);
    }

    public function test_show_returns_404_for_nonexistent_contact(): void
    {
        $response = $this->getJson(self::BASE_URL . '/999');

        $response->assertStatus(404);
    }

    public function test_can_update_a_contact(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->putJson(self::BASE_URL . '/' . $contact->id, [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '21988887777',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.email', 'updated@example.com');
        $response->assertJsonPath('data.phone', '21988887777');

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_update_returns_validation_errors(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->putJson(self::BASE_URL . '/' . $contact->id, [
            'name' => '',
            'email' => 'not-an-email',
            'phone' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'phone']);
    }

    public function test_can_soft_delete_a_contact(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->deleteJson(self::BASE_URL . '/' . $contact->id);

        $response->assertStatus(204);

        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    }

    public function test_show_returns_404_after_delete(): void
    {
        $contact = Contact::factory()->create();
        $contact->delete();

        $response = $this->getJson(self::BASE_URL . '/' . $contact->id);

        $response->assertStatus(404);
    }
}
