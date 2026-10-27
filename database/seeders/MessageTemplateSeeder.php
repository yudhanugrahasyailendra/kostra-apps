<?php

namespace Database\Seeders;

use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    /**
     * Template bawaan sistem (tenant_id null). Idempoten; isi yang sudah ada tidak ditimpa supaya perubahan
     * manual oleh Super Admin tidak hilang.
     */
    public function run(): void
    {
        // Baris sistem tidak punya tenant, jadi dibuat di luar konteks tenant.
        app(TenantContext::class)->withoutTenant(function () {
            foreach (MessageTemplateKey::cases() as $key) {
                MessageTemplate::query()->firstOrCreate(
                    ['tenant_id' => null, 'key' => $key->value, 'channel' => MessageTemplate::CHANNEL],
                    ['name' => $key->label(), 'body' => $key->defaultBody(), 'is_active' => true],
                );
            }
        });
    }
}
