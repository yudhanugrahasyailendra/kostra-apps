<?php

namespace App\Actions;

use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use App\Services\WhatsApp\MessageTemplateRenderer;
use App\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class SaveMessageTemplate
{
    public function __construct(private MessageTemplateRenderer $renderer) {}

    /**
     * Simpan isi template milik tenant aktif untuk satu key (NTF-02). Template sistem tidak pernah diubah:
     * pemilik mendapat salinan sendiri. Isi dibatasi variabel yang diizinkan renderer, dan category tetap
     * utility (dikunci di model). Salinan yang pernah dinonaktifkan (reset) diaktifkan lagi.
     */
    public function handle(MessageTemplateKey $key, string $body): MessageTemplate
    {
        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Isi pesan tidak boleh kosong.']);
        }

        $this->renderer->validate($body);

        $custom = MessageTemplate::query()->where('key', $key->value)->where('channel', MessageTemplate::CHANNEL)->first();

        if ($custom !== null) {
            $custom->update(['body' => $body, 'is_active' => true]);

            return $custom;
        }

        // Alasan unscoped: nama diambil dari template sistem (tenant_id null) yang tidak terlihat TenantScope.
        $name = TenantContext::instance()->unscoped(fn () => MessageTemplate::query()
            ->whereNull('tenant_id')->where('key', $key->value)->value('name')) ?? $key->label();

        return MessageTemplate::create([
            'key' => $key->value,
            'channel' => MessageTemplate::CHANNEL,
            'name' => $name,
            'body' => $body,
            'is_active' => true,
        ]);
    }

    /** Kembali ke template sistem. Salinan dinonaktifkan, bukan dihapus, karena message_logs menunjuk ke sana. */
    public function reset(MessageTemplateKey $key): void
    {
        MessageTemplate::query()->where('key', $key->value)->where('channel', MessageTemplate::CHANNEL)->update(['is_active' => false]);
    }
}
