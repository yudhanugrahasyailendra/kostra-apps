<?php

namespace Tests;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedPlans(): void
    {
        $this->seed(PlanSeeder::class);
    }

    /**
     * Jalankan callback dalam konteks tenant (query model tenant gagal tertutup di luar konteks).
     */
    protected function asTenant(Tenant $tenant, callable $callback): mixed
    {
        return app(TenantContext::class)->run($tenant, $callback);
    }

    /**
     * Baca data lintas tenant untuk asersi tes.
     */
    protected function withoutTenant(callable $callback): mixed
    {
        return app(TenantContext::class)->withoutTenant($callback);
    }
}
