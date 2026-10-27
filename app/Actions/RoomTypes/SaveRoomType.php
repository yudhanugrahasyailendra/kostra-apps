<?php

namespace App\Actions\RoomTypes;

use App\Actions\Concerns\ResolvesTenant;
use App\Models\Property;
use App\Models\RoomType;
use App\Services\Plans\PlanLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveRoomType
{
    use ResolvesTenant;

    public function __construct(private readonly PlanLimits $limits) {}

    /**
     * Membuat tipe baru, atau memperbarui tipe yang ada. Properti tidak bisa diganti setelah dibuat.
     * Mengubah base_price tidak menyentuh harga kamar yang sudah ada.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data, ?RoomType $type = null): RoomType
    {
        $tenant = $this->currentTenant();

        return DB::transaction(function () use ($tenant, $data, $type) {
            $this->limits->lockTenant($tenant);

            $propertyId = $type ? $type->property_id : ($data['property_id'] ?? null);

            if ($propertyId !== null) {
                Property::query()->findOrFail($propertyId);
            }

            $duplicate = RoomType::query()
                ->where('name', $data['name'])
                ->when($propertyId === null,
                    fn ($query) => $query->whereNull('property_id'),
                    fn ($query) => $query->where('property_id', $propertyId))
                ->when($type, fn ($query) => $query->whereKeyNot($type->getKey()))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['name' => 'Nama tipe sudah dipakai.']);
            }

            unset($data['property_id']);

            if ($type) {
                $type->update($data);

                return $type;
            }

            return RoomType::create([...$data, 'property_id' => $propertyId]);
        });
    }
}
