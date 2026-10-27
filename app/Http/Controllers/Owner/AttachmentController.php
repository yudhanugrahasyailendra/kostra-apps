<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Attachments\DeleteAttachment;
use App\Actions\Attachments\ServeAttachment;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Lease;
use App\Models\PaymentAccount;
use App\Models\Property;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AttachmentController extends Controller
{
    /**
     * Rute ini dilindungi middleware signed (tautan 5 menit), auth, tenant, dan Policy.
     */
    public function show(Attachment $attachment, ServeAttachment $serve): Response
    {
        Gate::authorize('view', $attachment);

        return $serve($attachment);
    }

    public function destroy(Attachment $attachment, DeleteAttachment $delete): RedirectResponse
    {
        Gate::authorize('delete', $attachment);

        $parent = $attachment->attachable;

        // Foto properti hanya boleh diubah pemilik properti (hak tulis).
        if ($parent instanceof Property) {
            Gate::authorize('update', $parent);
        }

        // Gambar QRIS rekening hanya boleh diubah pengguna berizin pengaturan.
        if ($parent instanceof PaymentAccount) {
            Gate::authorize('manage', $parent);
        }

        $delete($attachment);

        return match (true) {
            $parent instanceof Resident => redirect()->route('owner.residents.show', $parent)->with('status', 'Dokumen dihapus.'),
            $parent instanceof Property => redirect()->route('owner.properties.edit', $parent)->with('status', 'Foto dihapus.'),
            $parent instanceof Lease => redirect()->route('owner.leases.show', $parent)->with('status', 'Foto dihapus.'),
            $parent instanceof PaymentAccount => redirect()->route('owner.payment-accounts.edit', $parent)->with('status', 'Gambar QRIS dihapus.'),
            default => redirect()->route('owner.dashboard'),
        };
    }
}
