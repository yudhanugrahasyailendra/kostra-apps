<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Residents\AnonymizeResident;
use App\Actions\Residents\CreateResident;
use App\Actions\Residents\ImportResidents;
use App\Actions\Residents\UpdateResident;
use App\Enums\AttachmentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ImportResidentsRequest;
use App\Http\Requests\Owner\ResidentIndexRequest;
use App\Http\Requests\Owner\ResidentRequest;
use App\Models\PortalToken;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ResidentController extends Controller
{
    public function index(ResidentIndexRequest $request): View
    {
        Gate::authorize('viewAny', Resident::class);

        $q = trim((string) $request->validated('q'));
        $status = $request->validated('status');

        $residents = Resident::query()
            ->with(['leases' => fn ($lease) => $lease->where('leases.status', 'active')->with('room:id,number')])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $digits = preg_replace('/\D+/', '', $q);

                $query->where(function ($inner) use ($like, $digits) {
                    $inner->where('name', 'like', $like)
                        ->orWhereHas('leases', fn ($lease) => $lease->where('leases.status', 'active')
                            ->whereHas('room', fn ($room) => $room->where('number', 'like', $like)));

                    if ($digits !== '') {
                        $inner->orWhere('phone', 'like', '%'.ltrim($digits, '0').'%');
                    }
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        // Nomor yang dipakai lebih dari satu penghuni, untuk lencana "Nomor ganda" (satu query).
        $sharedPhones = Resident::query()
            ->select('phone')->groupBy('phone')->havingRaw('count(*) > 1')
            ->pluck('phone')->flip();

        return view('owner.residents.index', [
            'residents' => $residents,
            'sharedPhones' => $sharedPhones,
            'q' => $q,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Resident::class);

        return view('owner.residents.create');
    }

    public function store(ResidentRequest $request, CreateResident $create): RedirectResponse
    {
        $resident = $create($request->validated());

        $redirect = redirect()->route('owner.residents.show', $resident)->with('status', 'Penghuni ditambahkan.');

        if ($resident->sharesPhoneWithOthers()) {
            $redirect->with('warning', 'Nomor WhatsApp ini juga dipakai penghuni lain. Pastikan memang benar.');
        }

        return $redirect;
    }

    public function show(Resident $resident): View
    {
        Gate::authorize('view', $resident);

        return view('owner.residents.show', [
            'resident' => $resident,
            'attachments' => $resident->attachments()->orderBy('category')->latest('id')->get(),
            'leases' => $resident->leases()->with(['room:id,number,property_id', 'room.property:id,name'])
                ->orderByDesc('start_date')->orderByDesc('leases.id')->get(),
            'sharesPhone' => $resident->sharesPhoneWithOthers(),
            'categories' => AttachmentCategory::residentValues(),
            'portalTokens' => PortalToken::query()->where('resident_id', $resident->getKey())->active()->latest('id')->get(),
        ]);
    }

    public function edit(Resident $resident): View
    {
        Gate::authorize('update', $resident);

        return view('owner.residents.edit', ['resident' => $resident]);
    }

    public function update(ResidentRequest $request, Resident $resident, UpdateResident $update): RedirectResponse
    {
        $update($resident, $request->validated());

        return redirect()->route('owner.residents.show', $resident)->with('status', 'Data penghuni diperbarui.');
    }

    public function destroy(Resident $resident, AnonymizeResident $anonymize): RedirectResponse
    {
        Gate::authorize('delete', $resident);

        $anonymize($resident);

        return redirect()->route('owner.residents.index')
            ->with('status', 'Data penghuni dianonimkan dan lampirannya dihapus. Catatan keuangan tetap tersimpan tanpa identitas.');
    }

    public function import(ImportResidentsRequest $request, ImportResidents $import): RedirectResponse
    {
        $result = $import($request->file('file')->getRealPath());

        return redirect()->route('owner.residents.index')
            ->with('status', "{$result->created} penghuni diimpor, ".count($result->failures).' baris gagal, '.count($result->duplicates).' nomor ganda ditandai.')
            ->with('import_failures', array_slice($result->failures, 0, 100))
            ->with('import_duplicates', array_slice($result->duplicates, 0, 100));
    }
}
