<?php

namespace App\Actions\Payments;

use App\Enums\PaymentAccountType;
use App\Models\PaymentAccount;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePaymentAccount
{
    /**
     * Membuat atau memperbarui rekening pembayaran. Hanya satu rekening bawaan per tenant: menjadikan satu
     * bawaan melepas yang lain. Rekening nonaktif tidak bisa menjadi bawaan. Rekening tidak dihapus (dipakai
     * pembayaran lama), hanya dinonaktifkan.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data, ?PaymentAccount $account = null): PaymentAccount
    {
        $type = PaymentAccountType::tryFrom((string) ($data['type'] ?? $account?->type->value));

        if ($type === null) {
            throw ValidationException::withMessages(['type' => 'Jenis rekening tidak valid.']);
        }

        if (isset($data['property_id']) && $data['property_id'] !== null) {
            Property::query()->findOrFail($data['property_id']);
        }

        return DB::transaction(function () use ($data, $account, $type) {
            $isActive = (bool) ($data['is_active'] ?? $account?->is_active ?? true);
            $wantsDefault = (bool) ($data['is_default'] ?? $account?->is_default ?? false);
            $isDefault = $isActive && $wantsDefault;

            $fields = [
                'property_id' => $data['property_id'] ?? $account?->property_id,
                'type' => $type,
                'bank_name' => $data['bank_name'] ?? $account?->bank_name,
                'account_number' => $data['account_number'] ?? $account?->account_number,
                'account_name' => $data['account_name'] ?? $account?->account_name,
                'instructions' => $data['instructions'] ?? $account?->instructions,
                'is_active' => $isActive,
                'is_default' => $isDefault,
            ];

            if ($isDefault) {
                PaymentAccount::query()
                    ->when($account, fn ($query) => $query->whereKeyNot($account->getKey()))
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $saved = $account ?? new PaymentAccount;
            $saved->fill($fields)->save();

            // Rekening aktif pertama otomatis menjadi bawaan.
            if ($saved->is_active && ! PaymentAccount::query()->where('is_default', true)->exists()) {
                $saved->update(['is_default' => true]);
            }

            return $saved;
        });
    }
}
