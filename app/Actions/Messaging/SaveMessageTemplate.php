<?php

namespace App\Actions\Messaging;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use App\Services\Messaging\MessageTemplateRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveMessageTemplate
{
    use ResolvesTenant;

    public function __construct(private readonly MessageTemplateRenderer $renderer) {}

    /**
     * Menyimpan isi template milik tenant. Template bawaan sistem tidak pernah diubah: tenant yang mengedit
     * pertama kali mendapat salinan miliknya. Isi hanya boleh memakai variabel yang diizinkan, dan category
     * selalu utility (diatur model, bukan input).
     *
     * @param  array{name: string, body: string, is_active?: bool}  $data
     */
    public function __invoke(MessageTemplateKey $key, array $data): MessageTemplate
    {
        $tenant = $this->currentTenant();

        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Nama template wajib diisi (maksimal 120 karakter).']);
        }

        $this->renderer->assertValid((string) ($data['body'] ?? ''));

        return DB::transaction(function () use ($tenant, $key, $name, $data) {
            $own = MessageTemplate::query()->forKey($key)->where('tenant_id', $tenant->getKey())->first();
            $isActive = (bool) ($data['is_active'] ?? true);
            $fields = ['name' => $name, 'body' => trim($data['body']), 'is_active' => $isActive];

            if ($own !== null) {
                $own->update($fields);

                return $own;
            }

            $system = MessageTemplate::resolve($key);

            return MessageTemplate::create($fields + [
                'tenant_id' => $tenant->getKey(),
                'key' => $key->value,
                'channel' => MessageTemplate::CHANNEL,
                // Nama dan status persetujuan di penyedia dibawa dari bawaan sistem.
                'provider_template_name' => $system?->provider_template_name,
                'provider_status' => $system?->provider_status,
            ]);
        });
    }
}
