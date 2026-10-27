<?php

namespace App\Actions\Complaints;

use App\Actions\Attachments\StoreAttachment;
use App\Enums\AttachmentCategory;
use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Events\ComplaintCreated;
use App\Models\Complaint;
use App\Models\Lease;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateComplaint
{
    public function __construct(private readonly StoreAttachment $store) {}

    /**
     * Membuat tiket atas nama penghuni (staf di fase ini; portal menyusul di Fase 10). Kamar dan properti
     * diambil dari kontrak aktif penghuni, jadi tiket tidak bisa menunjuk kamar yang bukan miliknya.
     *
     * @param  array{resident_id: int, category: string, title: string, description: string}  $data
     */
    public function __invoke(array $data, ?UploadedFile $photo = null, User|Resident|null $actor = null): Complaint
    {
        $resident = Resident::query()->findOrFail($data['resident_id']);

        $category = ComplaintCategory::tryFrom((string) ($data['category'] ?? ''));

        if ($category === null) {
            throw ValidationException::withMessages(['category' => 'Kategori tidak valid.']);
        }

        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
            throw ValidationException::withMessages(['title' => 'Judul wajib diisi (3 sampai 150 karakter).']);
        }

        if (mb_strlen($description) < 3 || mb_strlen($description) > 5000) {
            throw ValidationException::withMessages(['description' => 'Deskripsi wajib diisi (3 sampai 5000 karakter).']);
        }

        $lease = Lease::query()->active()
            ->whereHas('residents', fn ($query) => $query->whereKey($resident->getKey()))
            ->latest('start_date')->latest('id')
            ->first();

        if ($lease === null) {
            throw ValidationException::withMessages(['resident_id' => 'Penghuni ini tidak punya kontrak aktif, jadi kamarnya tidak diketahui.']);
        }

        $complaint = DB::transaction(function () use ($resident, $lease, $category, $title, $description, $photo, $actor) {
            $complaint = Complaint::create([
                'property_id' => $lease->property_id,
                'room_id' => $lease->room_id,
                'resident_id' => $resident->getKey(),
                'category' => $category,
                'title' => $title,
                'description' => $description,
                'status' => ComplaintStatus::New,
            ]);

            if ($photo !== null) {
                ($this->store)($complaint, $photo, AttachmentCategory::ComplaintPhoto, $actor);
            }

            return $complaint;
        });

        event(new ComplaintCreated($complaint->getKey(), $complaint->tenant_id));

        return $complaint;
    }
}
