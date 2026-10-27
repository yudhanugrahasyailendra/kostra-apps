<?php

namespace App\Actions\Complaints;

use App\Actions\Attachments\StoreAttachment;
use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AddComplaintPhoto
{
    public function __construct(private readonly StoreAttachment $store) {}

    public function __invoke(Complaint $complaint, UploadedFile $file, User $actor): Attachment
    {
        if (! $complaint->isOpen()) {
            throw ValidationException::withMessages(['file' => 'Tiket sudah ditutup dan tidak menerima foto baru.']);
        }

        return ($this->store)($complaint, $file, AttachmentCategory::ComplaintPhoto, $actor);
    }
}
