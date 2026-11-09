<?php

namespace App\Actions;

use App\Enums\AttachmentCategory;
use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\Resident;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateComplaint
{
    public function __construct(private StoreAttachment $store) {}

    /**
     * Staf membuat tiket atas nama penghuni (portal menyusul di Fase 10). Kamar dan penghuni dicari lewat
     * scope tenant, jadi id tenant lain gagal dengan 404. property_id diambil dari kamar.
     *
     * @param  array{room_id: int, resident_id: int, category: string, title: string, description: string}  $data
     * @param  list<UploadedFile>  $photos
     */
    public function handle(array $data, User $by, array $photos = []): Complaint
    {
        $room = Room::query()->findOrFail((int) $data['room_id']);
        $resident = Resident::query()->findOrFail((int) $data['resident_id']);

        return DB::transaction(function () use ($data, $by, $room, $resident, $photos) {
            $complaint = Complaint::create([
                'property_id' => $room->property_id,
                'room_id' => $room->id,
                'resident_id' => $resident->id,
                'category' => ComplaintCategory::from($data['category']),
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'status' => ComplaintStatus::New,
            ]);

            foreach ($photos as $photo) {
                $this->store->handle($complaint, $photo, AttachmentCategory::ComplaintPhoto, $by);
            }

            return $complaint;
        });
    }
}
