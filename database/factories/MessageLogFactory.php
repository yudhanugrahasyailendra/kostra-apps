<?php

namespace Database\Factories;

use App\Enums\MessageStatus;
use App\Models\MessageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MessageLog> */
class MessageLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel' => 'whatsapp',
            'category' => 'utility',
            'recipient' => '+62812'.fake()->numerify('########'),
            'body' => 'Pesan uji',
            'status' => MessageStatus::Queued,
            'provider' => 'fake',
            'attempts' => 0,
            'counted_in_quota' => false,
        ];
    }
}
