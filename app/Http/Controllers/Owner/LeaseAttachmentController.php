<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Attachments\StoreAttachment;
use App\Enums\AttachmentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\LeaseAttachmentRequest;
use App\Models\Lease;
use Illuminate\Http\RedirectResponse;

class LeaseAttachmentController extends Controller
{
    /**
     * Foto kondisi kamar saat check-in atau check-out (tidak sensitif).
     */
    public function store(LeaseAttachmentRequest $request, Lease $lease, StoreAttachment $store): RedirectResponse
    {
        $store($lease, $request->file('file'), AttachmentCategory::RoomCondition, $request->user());

        return redirect()->route('owner.leases.show', $lease)->with('status', 'Foto kondisi kamar diunggah.');
    }
}
