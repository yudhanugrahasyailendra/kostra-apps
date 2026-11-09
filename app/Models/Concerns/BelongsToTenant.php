<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantMismatchException;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model milik pemilik kos: global scope tenant_id dan pengisian otomatis.
 * Membuat baris tanpa tenant aktif ditolak (gagal tertutup), kecuali di dalam
 * TenantContext::unscoped() atau bila model mengizinkan tenant kosong lewat
 * allowsNullTenant() (hanya Super Admin). tenant_id tidak boleh diubah.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            $context = TenantContext::instance();
            $contextId = $context->id();

            if ($contextId === null) {
                if ($model->tenant_id === null && ! $context->isUnscoped() && ! $model->allowsNullTenant()) {
                    throw new TenantMismatchException('Tidak ada tenant aktif untuk '.$model::class.'. Gunakan TenantContext::runAs().');
                }

                return;
            }

            if ($model->tenant_id === null) {
                $model->tenant_id = $contextId;
            } elseif ((int) $model->tenant_id !== $contextId) {
                throw new TenantMismatchException('tenant_id tidak sama dengan tenant aktif.');
            }
        });

        static::updating(function ($model): void {
            if ($model->isDirty('tenant_id') && $model->getOriginal('tenant_id') !== null) {
                throw new TenantMismatchException('tenant_id tidak boleh diubah.');
            }
        });
    }

    /** Model yang boleh tanpa tenant (mis. Super Admin) menimpa ini. */
    public function allowsNullTenant(): bool
    {
        return false;
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
