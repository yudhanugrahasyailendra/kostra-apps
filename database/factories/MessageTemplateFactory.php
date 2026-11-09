<?php

namespace Database\Factories;

use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MessageTemplate> */
class MessageTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => MessageTemplateKey::InvoiceIssued->value,
            'name' => 'Templat uji',
            'body' => 'Halo {{nama}}, tagihan {{jumlah}} jatuh tempo {{jatuh_tempo}}.',
        ];
    }
}
