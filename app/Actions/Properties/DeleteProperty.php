<?php

namespace App\Actions\Properties;

use App\Actions\Concerns\ResolvesTenant;
use App\Models\Property;
use App\Models\Room;
use App\Services\Plans\PlanLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteProperty
{
    use ResolvesTenant;

    public function __construct(private readonly PlanLimits $limits) {}

    public function __invoke(Property $property): void
    {
        $tenant = $this->currentTenant();

        DB::transaction(function () use ($tenant, $property) {
            // Serialkan dengan pembuatan kamar supaya properti tidak terhapus saat kamar baru masuk.
            $this->limits->lockTenant($tenant);

            if (Room::query()->where('property_id', $property->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'general' => 'Properti masih punya kamar. Hapus kamarnya dulu, atau nonaktifkan properti ini.',
                ]);
            }

            $property->delete();
        });
    }
}
