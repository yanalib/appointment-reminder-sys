<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class ClientTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function it_can_create_a_client_with_valid_data()
    {
        $clientData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'preferred_notification_method' => 'email',
            'reminder_time_preference' => 30,
        ];

        $client = Client::create($clientData);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals($clientData['name'], $client->name);
        $this->assertEquals($clientData['email'], $client->email);
        $this->assertEquals($clientData['phone'], $client->phone);
        $this->assertEquals($clientData['preferred_notification_method'], $client->preferred_notification_method);
        $this->assertEquals($clientData['reminder_time_preference'], $client->reminder_time_preference);
        $this->assertTrue($client->active);
    }

    /** @test */
    public function it_can_create_multiple_clients_using_factory()
    {
        $clients = Client::factory()->count(3)->create();

        $this->assertCount(3, $clients);
        $this->assertDatabaseCount('clients', 3);
    }

    /** @test */
    public function it_can_create_client_with_email_preference()
    {
        $client = Client::factory()->emailPreference()->create();

        $this->assertEquals('email', $client->preferred_notification_method);
    }

    /** @test */
    public function it_can_create_client_with_sms_preference()
    {
        $client = Client::factory()->smsPreference()->create();

        $this->assertEquals('sms', $client->preferred_notification_method);
    }

    /** @test */
    public function it_can_create_inactive_client()
    {
        $client = Client::factory()->inactive()->create();

        $this->assertFalse($client->active);
    }

    /** @test */
    public function it_cannot_create_client_with_duplicate_email()
    {
        $this->expectException(QueryException::class);

        // Create first client
        Client::factory()->create([
            'email' => 'duplicate@example.com'
        ]);

        // Try to create second client with same email
        Client::factory()->create([
            'email' => 'duplicate@example.com'
        ]);
    }

    /** @test */
    public function it_cannot_create_client_without_required_fields()
    {
        $this->expectException(QueryException::class);

        Client::create([
            'name' => 'John Doe'
            // Missing required fields
        ]);
    }

    /** @test */
    public function it_can_update_client_preferences()
    {
        $client = Client::factory()->create();

        $newPreferences = [
            'preferred_notification_method' => 'sms',
            'reminder_time_preference' => 60
        ];

        $client->update($newPreferences);
        $client->refresh();

        $this->assertEquals('sms', $client->preferred_notification_method);
        $this->assertEquals(60, $client->reminder_time_preference);
    }
}
