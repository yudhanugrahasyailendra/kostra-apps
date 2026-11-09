<?php

namespace Database\Seeders;

use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    /** Template sistem (tenant_id null). Idempoten: dijalankan ulang memperbarui isi bawaan, tidak menggandakan. */
    public function run(): void
    {
        $templates = [
            [MessageTemplateKey::InvoiceIssued, "Halo {{nama}}, tagihan sewa kos Anda sebesar {{jumlah}} telah terbit dan jatuh tempo pada {{jatuh_tempo}}.\n\n{{link}}"],
            [MessageTemplateKey::ReminderBeforeDue, "Halo {{nama}}, pengingat: tagihan sewa kos sebesar {{jumlah}} jatuh tempo pada {{jatuh_tempo}}.\n\n{{link}}"],
            [MessageTemplateKey::ReminderDueToday, "Halo {{nama}}, tagihan sewa kos sebesar {{jumlah}} jatuh tempo hari ini ({{jatuh_tempo}}).\n\n{{link}}"],
            [MessageTemplateKey::ReminderOverdue, "Halo {{nama}}, tagihan sewa kos sebesar {{jumlah}} sudah melewati jatuh tempo ({{jatuh_tempo}}). Abaikan pesan ini bila sudah membayar.\n\n{{link}}"],
            [MessageTemplateKey::Receipt, "Halo {{nama}}, pembayaran sebesar {{jumlah}} sudah kami terima. Terima kasih.\n\n{{link}}"],
            [MessageTemplateKey::LeaseExpiring, "Halo {{nama}}, masa sewa kamar Anda akan berakhir pada {{jatuh_tempo}}. Hubungi pengelola untuk perpanjangan.\n\n{{link}}"],
            [MessageTemplateKey::ComplaintStatus, "Halo {{nama}}, status komplain Anda telah diperbarui oleh pengelola.\n\n{{link}}"],
        ];

        // Alasan unscoped: baris sistem bertenant_id null, tidak terlihat dan tidak bisa dibuat dalam konteks tenant.
        TenantContext::instance()->unscoped(function () use ($templates) {
            foreach ($templates as [$key, $body]) {
                MessageTemplate::query()->updateOrCreate(
                    ['tenant_id' => null, 'key' => $key->value, 'channel' => MessageTemplate::CHANNEL],
                    ['name' => $key->label(), 'body' => $body, 'is_active' => true],
                );
            }
        });
    }
}
