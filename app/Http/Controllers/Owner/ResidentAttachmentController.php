<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Attachments\StoreAttachment;
use App\Enums\AttachmentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreAttachmentRequest;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;

class ResidentAttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request, Resident $resident, StoreAttachment $store): RedirectResponse
    {
        $store(
            $resident,
            $request->file('file'),
            AttachmentCategory::from($request->validated('category')),
            $request->user(),
        );

        return redirect()->route('owner.residents.show', $resident)->with('status', 'Dokumen diunggah.');
    }
}
