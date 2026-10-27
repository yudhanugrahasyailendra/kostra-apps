<?php

namespace Database\Factories;

use App\Enums\MessageStatus;
use App\Models\MessageLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Log mentah untuk tes UI. Berikan tenant (dan penghuni bila perlu): MessageLog::factory()->for($tenant).
 *
 * @extends Factory<MessageLog>
 */
class MessageLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'channel' => 'whatsapp',
            'category' => 'utility',
            'recipient' => '+62812'.fake()->numerify('#######'),
            'body' => 'Isi pesan uji',
            'status' => MessageStatus::Queued,
            'attempts' => 0,
            'counted_in_quota' => false,
        ];
    }
}
