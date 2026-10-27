<?php

namespace App\Models;

use App\Enums\MessageTemplateKey;
use App\Models\Concerns\Auditable;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantOrSystemScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Template bawaan sistem (tenant_id null) dan salinan milik tenant. Tenant yang mengedit membuat salinan
 * miliknya sendiri; baris sistem tidak berubah. Tidak memakai BelongsToTenant karena scope-nya harus
 * menyertakan baris sistem (lihat TenantOrSystemScope).
 */
class MessageTemplate extends Model
{
    use Auditable;

    public const CATEGORY = 'utility';

    public const CHANNEL = 'whatsapp';

    protected $fillable = [
        'tenant_id',
        'key',
        'channel',
        'name',
        'body',
        'category',
        'provider_template_name',
        'provider_status',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantOrSystemScope);

        static::creating(function (self $template) {
            $context = app(TenantContext::class);

            // Kategori selalu utility, apa pun yang dikirim pemanggil.
            $template->category = self::CATEGORY;

            if ($context->isUnscoped() || $context->id() === null) {
                return;
            }

            if ($template->tenant_id === null) {
                $template->tenant_id = $context->id();

                return;
            }

            if ((int) $template->tenant_id !== $context->id()) {
                throw new LogicException('tenant_id tidak sama dengan tenant yang sedang aktif.');
            }
        });

        static::saving(function (self $template) {
            $template->category = self::CATEGORY;
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isSystem(): bool
    {
        return $this->tenant_id === null;
    }

    public function templateKey(): ?MessageTemplateKey
    {
        return MessageTemplateKey::tryFrom($this->key);
    }

    /**
     * Template yang berlaku bagi tenant aktif untuk satu kunci: salinan tenant bila ada, selain itu bawaan sistem.
     */
    public static function resolve(MessageTemplateKey $key): ?self
    {
        return static::query()
            ->where('key', $key->value)
            ->where('channel', self::CHANNEL)
            ->orderByRaw('tenant_id IS NULL')
            ->first();
    }

    public function scopeForKey(Builder $query, MessageTemplateKey $key): void
    {
        $query->where('key', $key->value)->where('channel', self::CHANNEL);
    }
}
