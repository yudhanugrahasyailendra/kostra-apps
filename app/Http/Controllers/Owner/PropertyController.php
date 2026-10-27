<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Properties\CreateProperty;
use App\Actions\Properties\DeleteProperty;
use App\Actions\Properties\UpdateProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\PropertyRequest;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Property::class);

        return view('owner.properties.index', [
            'properties' => Property::query()->with('photo')->withCount('rooms')->orderBy('name')->get(),
        ]);
    }

    public function store(PropertyRequest $request, CreateProperty $create): RedirectResponse
    {
        $create($request->validated());

        return redirect()->route('owner.properties.index')->with('status', 'Properti ditambahkan.');
    }

    public function edit(Property $property): View
    {
        Gate::authorize('update', $property);

        return view('owner.properties.edit', ['property' => $property->load('photo')]);
    }

    public function update(PropertyRequest $request, Property $property, UpdateProperty $update): RedirectResponse
    {
        $update($property, $request->validated());

        return redirect()->route('owner.properties.index')->with('status', 'Properti diperbarui.');
    }

    public function destroy(Property $property, DeleteProperty $delete): RedirectResponse
    {
        Gate::authorize('delete', $property);

        $delete($property);

        return redirect()->route('owner.properties.index')->with('status', 'Properti dihapus.');
    }
}
