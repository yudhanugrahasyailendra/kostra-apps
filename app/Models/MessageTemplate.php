<?php

namespace App\Models;

use App\Enums\MessageTemplateKey;
use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Template pesan. tenant_id null = bawaan sistem (seeder, hanya terbaca); tenant boleh menyimpan salinan
 * sendiri per key. category selalu utility, apa pun yang dikirim klien.
 */
class MessageTemplate extends Model implements TenantExportable
{
    /** @use HasFactory<MessageTemplateFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    public const CATEGORY = 'utility';

    public const CHANNEL = 'whatsapp';

    protected $fillable = [
        'key', 'channel', 'name', 'body', 'category', 'provider_template_name', 'provider_status', 'is_active',
    ];

    protected $attributes = ['channel' => self::CHANNEL, 'category' => self::CATEGORY, 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Kategori dikunci di model, jadi tidak ada jalur (formulir, tinker, impor) yang bisa mengubahnya.
        static::saving(function (self $template): void {
            $template->category = self::CATEGORY;
        });
    }

    /** Baris sistem (tenant_id null) boleh ada; hanya seeder yang membuatnya. */
    public function allowsNullTenant(): bool
    {
        return true;
    }

    public function isSystem(): bool
    {
        return $this->tenant_id === null;
    }

    /**
     * Template yang berlaku untuk tenant aktif: salinan tenant bila ada dan aktif, selain itu bawaan sistem.
     */
    public static function effective(MessageTemplateKey $key): ?self
    {
        $tenantId = TenantContext::instance()->id();

        // Alasan unscoped: template sistem punya tenant_id null sehingga tidak terlihat oleh TenantScope.
        // Filter tenant dipasang manual: hanya baris tenant ini atau baris sistem.
        return TenantContext::instance()->unscoped(fn () => self::query()
            ->where('key', $key->value)
            ->where('channel', self::CHANNEL)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tenant_id')->when($tenantId !== null, fn ($q) => $q->orWhere('tenant_id', $tenantId)))
            // Salinan tenant (tenant_id tidak null) didahulukan atas bawaan sistem.
            ->orderByRaw('tenant_id is null')
            ->first());
    }

    public static function exportName(): string
    {
        return 'templat_pesan';
    }

    public static function exportColumns(): array
    {
        return ['id', 'key', 'channel', 'name', 'body', 'category', 'is_active', 'created_at'];
    }
}
