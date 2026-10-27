<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Konteks tenant untuk satu request, job, atau perintah artisan.
 *
 * Gagal tertutup: tanpa tenant dan tanpa mode "tanpa tenant" yang eksplisit,
 * query pada model tenant tidak mengembalikan baris apa pun.
 */
class TenantContext
{
    private ?int $tenantId = null;

    private ?Tenant $tenant = null;

    private bool $unscoped = false;

    public function set(Tenant|int|null $tenant): void
    {
        if ($tenant instanceof Tenant) {
            $this->tenant = $tenant;
            $this->tenantId = $tenant->getKey();
        } else {
            $this->tenant = null;
            $this->tenantId = $tenant;
        }

        $this->unscoped = false;
    }

    public function forget(): void
    {
        $this->tenant = null;
        $this->tenantId = null;
        $this->unscoped = false;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function current(): ?Tenant
    {
        if ($this->tenant === null && $this->tenantId !== null) {
            $this->tenant = Tenant::find($this->tenantId);
        }

        return $this->tenant;
    }

    public function isUnscoped(): bool
    {
        return $this->unscoped;
    }

    /**
     * Jalankan callback dalam konteks tenant tertentu, lalu pulihkan konteks semula.
     */
    public function run(Tenant|int $tenant, callable $callback): mixed
    {
        return $this->swap(fn () => $this->set($tenant), $callback);
    }

    /**
     * Mode lintas tenant yang eksplisit. Hanya untuk seeder, perintah artisan,
     * pencarian token, dan namespace Admin.
     */
    public function withoutTenant(callable $callback): mixed
    {
        return $this->swap(function () {
            $this->forget();
            $this->unscoped = true;
        }, $callback);
    }

    /**
     * Masuk ke mode lintas tenant untuk sisa request (panel Super Admin).
     */
    public function enterUnscoped(): void
    {
        $this->forget();
        $this->unscoped = true;
    }

    private function swap(callable $enter, callable $callback): mixed
    {
        $previous = [$this->tenant, $this->tenantId, $this->unscoped];

        $enter();

        try {
            return $callback();
        } finally {
            [$this->tenant, $this->tenantId, $this->unscoped] = $previous;
        }
    }
}
