<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Attachments\StoreAttachment;
use App\Enums\AttachmentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\PropertyPhotoRequest;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;

class PropertyPhotoController extends Controller
{
    public function store(PropertyPhotoRequest $request, Property $property, StoreAttachment $store): RedirectResponse
    {
        $store($property, $request->file('file'), AttachmentCategory::Photo, $request->user());

        return redirect()->route('owner.properties.edit', $property)->with('status', 'Foto properti diunggah.');
    }
}
