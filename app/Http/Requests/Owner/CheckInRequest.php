<?php

namespace App\Http\Requests\Owner;

use App\Actions\Leases\CheckInResident;
use App\Http\Requests\Concerns\ParsesRoomInput;
use App\Models\Lease;
use App\Models\Resident;
use App\Models\Room;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        return $this->user()->can('create', Lease::class);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['end_date', 'due_day', 'deposit_received_on', 'checkin_notes', 'notes']);
        $this->normalizeMoney('rent_amount');
        $this->normalizeMoney('deposit_amount');

        $others = array_values(array_filter((array) $this->input('other_resident_ids', []), fn ($id) => filled($id)));
        $this->merge(['other_resident_ids' => $others]);
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', new ExistsInTenant(Room::class, 'Kamar')],
            'primary_resident_id' => ['required', new ExistsInTenant(Resident::class, 'Penghuni')],
            'other_resident_ids' => ['array', 'max:'.(CheckInResident::MAX_RESIDENTS - 1)],
            'other_resident_ids.*' => ['distinct', 'different:primary_resident_id', new ExistsInTenant(Resident::class, 'Penghuni')],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'rent_amount' => ['nullable', 'integer', 'min:1', 'max:'.CheckInResident::MAX_RENT],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'deposit_amount' => ['nullable', 'integer', 'min:0', 'max:'.CheckInResident::MAX_DEPOSIT],
            'deposit_received_on' => ['nullable', 'date_format:Y-m-d'],
            'checkin_notes' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'room_id' => 'Kamar',
            'primary_resident_id' => 'Penanggung jawab',
            'other_resident_ids.*' => 'Penghuni tambahan',
            'start_date' => 'Tanggal mulai',
            'end_date' => 'Tanggal akhir',
            'rent_amount' => 'Harga sewa',
            'due_day' => 'Tanggal jatuh tempo',
            'deposit_amount' => 'Deposit',
            'deposit_received_on' => 'Tanggal deposit diterima',
        ];
    }
}
