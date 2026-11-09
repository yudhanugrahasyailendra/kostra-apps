<?php

namespace App\Http\Controllers\Owner;

use App\Actions\RenewLease;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\RenewLeaseRequest;
use App\Models\Lease;
use Illuminate\Http\RedirectResponse;

class LeaseRenewController extends Controller
{
    public function store(RenewLeaseRequest $request, Lease $lease, RenewLease $renew): RedirectResponse
    {
        $new = $renew->handle($lease, $request->validated());

        return redirect()->route('owner.leases.show', $new)->with('success', 'Kontrak diperpanjang. Kontrak baru menyambung kontrak sebelumnya.');
    }
}
