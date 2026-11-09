<?php

namespace App\Http\Controllers\Owner;

use App\Actions\CreateProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\PropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Services\Plans\PlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function index(PlanLimits $limits): Response
    {
        $this->authorize('viewAny', Property::class);

        return Inertia::render('Owner/Properties/Index', [
            'properties' => PropertyResource::collection(Property::query()->withCount('rooms')->orderBy('name')->get())->resolve(),
            'usage' => ['used' => $limits->propertiesUsed(), 'max' => $limits->maxProperties()],
            'can' => ['create' => request()->user()->can('create', Property::class)],
        ]);
    }

    public function store(PropertyRequest $request, CreateProperty $createProperty): RedirectResponse
    {
        $createProperty->handle($request->validated());

        return back()->with('success', 'Properti ditambahkan.');
    }

    public function update(PropertyRequest $request, Property $property): RedirectResponse
    {
        $property->update($request->validated());

        return back()->with('success', 'Properti diperbarui.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $this->authorize('delete', $property);

        if ($property->rooms()->exists()) {
            throw ValidationException::withMessages(['property' => 'Properti masih punya kamar. Hapus atau pindahkan kamarnya lebih dulu.']);
        }

        $property->delete();

        return back()->with('success', 'Properti dihapus.');
    }
}
