<?php

namespace App\Http\Controllers\Owner;

use App\Actions\CheckOutResident;
use App\Contracts\OutstandingBalance;
use App\Enums\LeaseEndReason;
use App\Enums\LeaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\CheckOutRequest;
use App\Http\Resources\LeaseDetailResource;
use App\Models\Lease;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LeaseCheckOutController extends Controller
{
    public function create(Lease $lease, OutstandingBalance $outstanding): Response|RedirectResponse
    {
        $this->authorize('checkOut', $lease);

        if ($lease->status !== LeaseStatus::Active) {
            return redirect()->route('owner.leases.show', $lease)->with('success', 'Kontrak ini sudah berakhir.');
        }

        $lease->load(['room', 'property', 'primaryResident', 'deposit', 'leaseResidents.resident']);

        return Inertia::render('Owner/Leases/CheckOut', [
            'lease' => (new LeaseDetailResource($lease))->resolve(),
            'outstanding' => $outstanding->forLease($lease),
            'reasons' => array_map(fn (LeaseEndReason $r) => ['value' => $r->value, 'label' => $r->label()], LeaseEndReason::cases()),
        ]);
    }

    public function store(CheckOutRequest $request, Lease $lease, CheckOutResident $checkOut): RedirectResponse
    {
        $checkOut->handle($lease, $request->validated());

        return redirect()->route('owner.leases.show', $lease)->with('success', 'Check-out selesai. Kontrak ditutup dan kamar dikosongkan.');
    }
}
