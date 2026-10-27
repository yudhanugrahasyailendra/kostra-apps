<?php

namespace App\Actions\Residents;

use App\Actions\Attachments\DeleteAttachment;
use App\Actions\Audit\RecordAuditLog;
use App\Enums\AuditEvent;
use App\Models\Complaint;
use App\Models\ComplaintComment;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\PortalToken;
use App\Models\Resident;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnonymizeResident
{
    public function __construct(
        private readonly DeleteAttachment $deleteAttachment,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * Permintaan hapus data diproses dengan anonimisasi: data pribadi dan lampiran dihapus,
     * baris tetap ada (soft delete) supaya catatan keuangan tidak putus.
     * phone NOT NULL di skema, jadi diisi penanda.
     */
    public function __invoke(Resident $resident): void
    {
        if ($resident->hasActiveLease()) {
            throw ValidationException::withMessages([
                'general' => 'Penghuni masih punya kontrak aktif. Selesaikan check-out lebih dulu.',
            ]);
        }

        $attachments = $resident->attachments()->get();

        DB::transaction(function () use ($resident) {
            // Tanpa event model: nilai lama (data pribadi) tidak boleh masuk ke jejak audit.
            $resident->forceFill([
                'name' => 'Penghuni Dihapus',
                'phone' => 'anonim-'.$resident->getKey(),
                'email' => null,
                'occupation' => null,
                'emergency_name' => null,
                'emergency_relation' => null,
                'emergency_phone' => null,
                'notes' => null,
                'is_active' => false,
                'whatsapp_opt_out_at' => $resident->whatsapp_opt_out_at ?? now(),
            ])->saveQuietly();

            // Data pribadi yang tersalin ke tabel lain ikut dibersihkan. Pembaruan massal sengaja (tanpa event model)
            // supaya nilai lama tidak masuk jejak audit. Baris keuangan tetap; hanya teks bebasnya yang dikosongkan.
            $id = $resident->getKey();
            MessageLog::query()->where('resident_id', $id)->update(['recipient' => '', 'body' => '[dihapus]']);
            Complaint::query()->where('resident_id', $id)->update(['title' => '[dihapus]', 'description' => '[dihapus]']);
            ComplaintComment::query()->where('resident_id', $id)->update(['body' => '[dihapus]']);
            PaymentProof::query()->where('resident_id', $id)->update(['sender_note' => null]);
            Payment::query()->where('resident_id', $id)->update(['notes' => null]);
            PortalToken::query()->where('resident_id', $id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

            ($this->audit)(AuditEvent::Deleted, $resident, null, ['anonymized' => true]);

            $resident->deleteQuietly();
        });

        foreach ($attachments as $attachment) {
            ($this->deleteAttachment)($attachment, force: true);
        }
    }
}
