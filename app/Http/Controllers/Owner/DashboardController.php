<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $rooms = Room::query()
            ->with(['property:id,name', 'roomType:id,name', 'activeLease.primaryResident:id,name'])
            ->orderBy('property_id')
            ->orderBy('floor')
            ->orderByRaw('LENGTH(number)')
            ->orderBy('number')
            ->limit(300)
            ->get();

        return view('owner.dashboard', [
            // Denah: properti, lalu lantai.
            'floorPlan' => $rooms->groupBy(fn (Room $room) => $room->property->name)
                ->map(fn ($group) => $group->groupBy(fn (Room $room) => $room->floor ?? 0)),
        ]);
    }
}
