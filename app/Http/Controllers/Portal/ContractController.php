<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Ringkasan kontrak untuk penghuni (PRT-07): bukan catatan internal pemilik. */
class ContractController extends PortalController
{
    public function __invoke(Request $request): Response
    {
        $lease = $this->activeLease($this->resident($request));
        $lease?->load('deposit');

        return Inertia::render('Portal/Contract', $this->shell($request) + [
            'lease' => $lease === null ? null : [
                'room_number' => $lease->room?->number,
                'room_type' => $lease->room?->type?->name,
                'property_name' => $lease->property?->name,
                'property_address' => $lease->property?->address,
                'start_date' => $lease->start_date->toDateString(),
                'end_date' => $lease->end_date?->toDateString(),
                'days_remaining' => $lease->daysRemaining(),
                'rent_amount' => $lease->rent_amount,
                'billing_cycle_label' => $lease->billing_cycle->label(),
                'due_day' => $lease->due_day,
                'deposit_amount' => $lease->deposit?->amount,
            ],
        ]);
    }
}
