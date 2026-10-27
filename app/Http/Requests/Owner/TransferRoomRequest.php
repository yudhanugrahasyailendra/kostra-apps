<?php

namespace App\Http\Requests\Owner;

use App\Actions\Leases\CheckInResident;
use App\Http\Requests\Concerns\ParsesRoomInput;
use App\Models\Room;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class TransferRoomRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        $lease = $this->route('lease');

        if (! $this->user()->can('operate', $lease)) {
            return false;
        }

        // Mengubah harga sewa menyangkut uang: hanya pemilik.
        return ! (filled($this->input('new_rent_amount')) && ! $this->user()->can('decideMoney', $lease));
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['reason']);
        $this->normalizeMoney('new_rent_amount');
    }

    public function rules(): array
    {
        return [
            'to_room_id' => ['required', new ExistsInTenant(Room::class, 'Kamar tujuan')],
            'transferred_on' => ['required', 'date_format:Y-m-d'],
            'new_rent_amount' => ['nullable', 'integer', 'min:1', 'max:'.CheckInResident::MAX_RENT],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'to_room_id' => 'Kamar tujuan',
            'transferred_on' => 'Tanggal pindah',
            'new_rent_amount' => 'Harga sewa baru',
        ];
    }
}
