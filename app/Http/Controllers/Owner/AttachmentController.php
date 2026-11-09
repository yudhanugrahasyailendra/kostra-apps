<?php

namespace App\Http\Controllers\Owner;

use App\Actions\DeleteAttachment;
use App\Actions\StoreAttachment;
use App\Enums\AttachmentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Resident;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class AttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request, Resident $resident, StoreAttachment $store): RedirectResponse
    {
        $store->handle(
            $resident,
            $request->file('file'),
            AttachmentCategory::from($request->validated('category')),
            $request->user(),
        );

        return back()->with('success', 'Dokumen diunggah.');
    }

    /**
     * Sajikan isi berkas. Dijaga tiga lapis: tanda tangan URL (berumur pendek), login + tenant
     * (route binding), dan Policy. Akses berkas sensitif dicatat di audit_logs.
     */
    public function show(Attachment $attachment): Response
    {
        $this->authorize('view', $attachment);

        $contents = $attachment->contents();

        abort_if($contents === null, 404);

        if ($attachment->is_sensitive) {
            app(AuditLogger::class)->record('attachment.viewed', $attachment);
        }

        return response($contents, 200, [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    public function destroy(Attachment $attachment, DeleteAttachment $delete): RedirectResponse
    {
        $this->authorize('delete', $attachment);

        $delete->handle($attachment);

        return back()->with('success', 'Dokumen dihapus.');
    }
}
