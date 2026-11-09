<?php

namespace Database\Factories;

use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * attachable_type dan attachable_id wajib diberikan lewat state. Berkas fisik tidak dibuat di sini;
 * pakai Action StoreAttachment untuk berkas nyata.
 *
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category' => AttachmentCategory::Photo,
            'disk' => 'attachments',
            'path' => 'uji/'.fake()->uuid().'.bin',
            'original_name' => 'foto.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'is_sensitive' => false,
        ];
    }
}
