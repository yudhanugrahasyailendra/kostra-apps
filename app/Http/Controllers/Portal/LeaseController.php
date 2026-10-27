<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaseController extends PortalController
{
    /**
     * Ringkasan kontrak penghuni sendiri: kamar, masa sewa, dan harga. Bukan data internal pemilik
     * (catatan check-in, deposit, atau catatan kontrak).
     */
    public function __invoke(Request $request, string $token): View
    {
        $leases = $this->resident($request)->leases()
            ->with(['room:id,number', 'property:id,name'])
            ->orderByDesc('start_date')->limit(5)
            ->get(['leases.id', 'leases.property_id', 'leases.room_id', 'leases.status', 'leases.start_date', 'leases.end_date', 'leases.rent_amount', 'leases.due_day', 'leases.billing_cycle']);

        return view('portal.lease', [
            'leases' => $leases,
            'today' => $this->tenant($request)->today(),
        ]);
    }
}
