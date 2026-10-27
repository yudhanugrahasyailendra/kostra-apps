<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Messaging\SetWhatsAppOptOut;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\WhatsAppOptOutRequest;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;

class ResidentWhatsAppController extends Controller
{
    public function update(WhatsAppOptOutRequest $request, Resident $resident, SetWhatsAppOptOut $setOptOut): RedirectResponse
    {
        $optOut = (bool) $request->validated('opt_out');

        $setOptOut($resident, $optOut);

        return redirect()->route('owner.residents.show', $resident)
            ->with('status', $optOut ? 'Penghuni ditandai berhenti menerima pesan otomatis.' : 'Penghuni kembali menerima pesan otomatis.');
    }
}
