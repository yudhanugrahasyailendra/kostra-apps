<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\RoomTypeRequest;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\RoomTypeResource;
use App\Models\Property;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RoomTypeController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', RoomType::class);

        return Inertia::render('Owner/RoomTypes/Index', [
            'roomTypes' => RoomTypeResource::collection(
                RoomType::query()->with('property:id,name')->withCount('rooms')->orderBy('name')->get()
            )->resolve(),
            'properties' => PropertyResource::collection(Property::query()->orderBy('name')->get())->resolve(),
            'can' => ['create' => request()->user()->can('create', RoomType::class)],
        ]);
    }

    public function store(RoomTypeRequest $request): RedirectResponse
    {
        RoomType::create($request->validated());

        return back()->with('success', 'Tipe kamar ditambahkan.');
    }

    /** Mengubah harga dasar tipe tidak menyentuh harga kamar yang sudah ada (PRP-02). */
    public function update(RoomTypeRequest $request, RoomType $roomType): RedirectResponse
    {
        $roomType->update($request->validated());

        return back()->with('success', 'Tipe kamar diperbarui.');
    }

    public function destroy(RoomType $roomType): RedirectResponse
    {
        $this->authorize('delete', $roomType);

        if ($roomType->rooms()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['room_type' => 'Tipe ini masih dipakai kamar. Nonaktifkan saja.']);
        }

        $roomType->delete();

        return back()->with('success', 'Tipe kamar dihapus.');
    }
}
