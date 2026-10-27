<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateTenant
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Mengaktifkan kembali tenant yang ditangguhkan. Status kembali Percobaan bila langganan berjalannya masih
     * percobaan, selain itu Aktif. Tidak membayar tagihan apa pun: yang menunggak tetap menunggak.
     */
    public function __invoke(Tenant $tenant): Tenant
    {
        return DB::transaction(function () use ($tenant) {
            $locked = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === TenantStatus::Closed) {
                throw ValidationException::withMessages(['general' => 'Tenant yang sudah ditutup tidak bisa diaktifkan.']);
            }

            $trialing = $this->context->run($locked, fn () => Subscription::query()->current()->latest('id')->first()?->status === SubscriptionStatus::Trialing);

            $locked->update([
                'status' => $trialing ? TenantStatus::Trial : TenantStatus::Active,
                'suspended_at' => null,
            ]);

            return $locked;
        });
    }
}
