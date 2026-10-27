<?php

namespace App\Http\Requests\Admin;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.manage');
    }

    protected function prepareForValidation(): void
    {
        // Kosong = tanpa batas (batas) atau tidak tersedia (harga tahunan).
        foreach (['price_yearly', 'max_properties', 'max_rooms', 'max_users'] as $key) {
            $this->merge([$key => filled($this->input($key)) ? $this->input($key) : null]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'features' => array_values((array) $this->input('features', [])),
        ]);
    }

    public function rules(): array
    {
        /** @var Plan $plan */
        $plan = $this->route('plan');

        return [
            'name' => ['required', 'string', 'max:60'],
            // Paket Gratis tetap gratis dan aktif: menjadi tujuan kembali bagi tenant yang percobaannya habis.
            'price_monthly' => ['required', 'integer', 'min:0', 'max:100000000', $plan->key === 'free' ? Rule::in([0]) : 'min:0'],
            'price_yearly' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'max_properties' => ['nullable', 'integer', 'min:1', 'max:65000'],
            'max_rooms' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:65000'],
            'monthly_message_quota' => ['required', 'integer', 'min:0', 'max:10000000'],
            'features' => ['array'],
            'features.*' => ['string', Rule::in(array_keys((array) config('kostra.features')))],
            'is_active' => ['boolean', $plan->key === 'free' ? 'accepted' : 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'price_monthly.in' => 'Harga paket Gratis harus 0.',
            'is_active.accepted' => 'Paket Gratis harus tetap aktif.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama',
            'price_monthly' => 'Harga bulanan',
            'price_yearly' => 'Harga tahunan',
            'max_properties' => 'Batas properti',
            'max_rooms' => 'Batas kamar',
            'max_users' => 'Batas pengguna',
            'monthly_message_quota' => 'Kuota pesan',
            'sort_order' => 'Urutan',
        ];
    }
}
