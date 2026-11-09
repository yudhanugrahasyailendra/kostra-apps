<?php

namespace App\Actions;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Plans\PlanLimits;

class CreateRoom
{
    public function __construct(private PlanLimits $limits) {}

    /** @param  array<string, mixed>  $data */
    public function handle(array $data): Room
    {
        return $this->limits->withinLock(function () use ($data) {
            $this->limits->assertCanAddRooms();

            // Harga kosong mengikuti harga dasar tipe; setelah dibuat, harga kamar berdiri sendiri.
            if (! isset($data['price']) && ! empty($data['room_type_id'])) {
                $data['price'] = RoomType::query()->findOrFail($data['room_type_id'])->base_price;
            }

            $data['status'] ??= RoomStatus::Vacant;

            return Room::create($data);
        });
    }
}
