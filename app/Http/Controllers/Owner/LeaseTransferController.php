<?php

namespace App\Http\Controllers\Owner;

use App\Actions\TransferRoom;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\TransferRoomRequest;
use App\Models\Lease;
use Illuminate\Http\RedirectResponse;

class LeaseTransferController extends Controller
{
    public function store(TransferRoomRequest $request, Lease $lease, TransferRoom $transfer): RedirectResponse
    {
        $transfer->handle($lease, $request->validated(), $request->user());

        return redirect()->route('owner.leases.show', $lease)->with('success', 'Pindah kamar dicatat. Harga baru berlaku untuk tagihan berikutnya.');
    }
}
