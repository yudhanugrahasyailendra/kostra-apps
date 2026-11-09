<?php

namespace App\Actions;

use App\Models\PaymentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePaymentAccount
{
    /**
     * Buat atau ubah rekening. Hanya satu rekening aktif yang boleh menjadi bawaan; menjadikan
     * satu rekening bawaan melepas status itu dari yang lain. Rekening bawaan harus aktif, dan
     * rekening pertama otomatis menjadi bawaan.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?PaymentAccount $account = null): PaymentAccount
    {
        return DB::transaction(function () use ($data, $account) {
            $account ??= new PaymentAccount;
            $account->fill($data);

            if (! $account->exists && ! PaymentAccount::query()->active()->where('is_default', true)->exists()) {
                $account->is_default = true;
            }

            if ($account->is_default && ! $account->is_active) {
                throw ValidationException::withMessages(['is_default' => 'Rekening nonaktif tidak dapat menjadi bawaan.']);
            }

            $account->save();

            if ($account->is_default) {
                PaymentAccount::query()->whereKeyNot($account->id)->where('is_default', true)->update(['is_default' => false]);
            }

            return $account;
        });
    }
}
