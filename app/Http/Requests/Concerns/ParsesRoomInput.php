<?php

namespace App\Http\Requests\Concerns;

use App\Support\Rupiah;

trait ParsesRoomInput
{
    /**
     * Ubah input uang ("1.850.000") menjadi integer; kosong menjadi null.
     */
    protected function normalizeMoney(string $key): void
    {
        $value = $this->input($key);

        $this->merge([$key => filled($value) ? (Rupiah::parse($value) ?? $value) : null]);
    }

    /**
     * Ubah teks dipisah koma ("AC, Kamar mandi dalam") menjadi daftar; kosong menjadi null.
     */
    protected function normalizeList(string $key): void
    {
        $value = $this->input($key);

        if (is_string($value)) {
            $items = array_values(array_filter(array_map('trim', explode(',', $value)), fn ($item) => $item !== ''));
            $this->merge([$key => $items === [] ? null : $items]);
        }
    }

    /**
     * Ubah isian kosong menjadi null supaya aturan nullable bekerja.
     *
     * @param  list<string>  $keys
     */
    protected function blankToNull(array $keys): void
    {
        $this->merge(collect($keys)->mapWithKeys(fn ($key) => [$key => filled($this->input($key)) ? $this->input($key) : null])->all());
    }
}
