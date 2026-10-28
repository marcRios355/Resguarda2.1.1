<?php

namespace Database\Factories;

use App\Models\SosAlert;
use App\Models\SosAlertDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SosAlertDelivery>
 */
class SosAlertDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->safeEmail();

        return [
            'sos_alert_id' => SosAlert::factory(),
            'recipient_hash' => hash_hmac('sha256', mb_strtolower($email), (string) config('app.key')),
            'recipient_email' => $email,
            'status' => 'pending',
            'attempt_count' => 0,
            'first_attempted_at' => null,
            'accepted_at' => null,
            'provider_message_id' => null,
            'failure_code' => null,
        ];
    }
}
