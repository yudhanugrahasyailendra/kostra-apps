<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Closure;

/**
 * Tenant yang sedang aktif untuk request, job, atau perintah ini.
 *
 * Aturan: tanpa tenant dan tanpa `unscoped()` eksplisit, query model tenant
 * ditolak (hasil kosong). Job dan perintah artisan wajib memakai `runAs()`;
 * jangan mengandalkan pengguna yang login.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $unscoped = false;

    public static function instance(): self
    {
        return app(self::class);
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function isUnscoped(): bool
    {
        return $this->unscoped;
    }

    /**
     * Jalankan blok kode sebagai tenant tertentu, lalu pulihkan konteks semula.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(Tenant $tenant, Closure $callback): mixed
    {
        $previousTenant = $this->tenant;
        $previousUnscoped = $this->unscoped;

        $this->tenant = $tenant;
        $this->unscoped = false;

        try {
            return $callback();
        } finally {
            $this->tenant = $previousTenant;
            $this->unscoped = $previousUnscoped;
        }
    }

    /**
     * Konteks lintas tenant yang disengaja (Super Admin, seeder, autentikasi).
     * Pakai seperlunya dan beri komentar alasannya di tempat pemanggilan.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function unscoped(Closure $callback): mixed
    {
        $previous = $this->unscoped;
        $this->unscoped = true;

        try {
            return $callback();
        } finally {
            $this->unscoped = $previous;
        }
    }
}
