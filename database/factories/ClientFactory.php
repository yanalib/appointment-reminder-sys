<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'preferred_notification_method' => $this->faker->randomElement(['email', 'sms', 'both']),
            'reminder_time_preference' => $this->faker->randomElement([15, 30, 60, 120, 1440]), // minutes before appointment
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the client is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Indicate that the client prefers email notifications.
     */
    public function emailPreference(): static
    {
        return $this->state(fn (array $attributes) => [
            'preferred_notification_method' => 'email',
        ]);
    }

    /**
     * Indicate that the client prefers SMS notifications.
     */
    public function smsPreference(): static
    {
        return $this->state(fn (array $attributes) => [
            'preferred_notification_method' => 'sms',
        ]);
    }
}
