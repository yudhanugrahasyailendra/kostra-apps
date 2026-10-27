<?php

namespace App\Http\Requests\Owner;

use App\Models\Property;
use App\Models\Tenant;
use App\Rules\ExistsInTenant;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    /** Rentang kustom dibatasi supaya laporan tetap ringan. */
    public const MAX_RANGE_DAYS = 366;

    public function authorize(): bool
    {
        return $this->user()->can('finance.view');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['property_id' => filled($this->input('property_id')) ? $this->input('property_id') : null]);
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'property_id' => ['nullable', new ExistsInTenant(Property::class, 'Properti')],
            'sort' => ['nullable', Rule::in(['amount', 'age'])],
            'format' => ['nullable', Rule::in(['xlsx', 'pdf'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('from') && $this->filled('to') && ! $validator->errors()->hasAny(['from', 'to'])) {
                $tz = $this->user()->tenant->timezone;
                $from = Dates::date($this->input('from'), $tz);
                $to = Dates::date($this->input('to'), $tz);

                if ($from === null || $to === null) {
                    $validator->errors()->add('from', 'Tanggal tidak valid.');
                } elseif ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
                    $validator->errors()->add('to', 'Rentang tanggal maksimal '.self::MAX_RANGE_DAYS.' hari.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ];
    }

    public function propertyId(): ?int
    {
        $id = $this->validated('property_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Periode laporan: rentang tanggal kustom bila diisi, selain itu satu bulan (bawaan bulan berjalan).
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable, label: string}
     */
    public function period(Tenant $tenant): array
    {
        $tz = $tenant->timezone;
        $validated = $this->validated();

        if (! empty($validated['from']) && ! empty($validated['to'])) {
            $from = Dates::date($validated['from'], $tz);
            $to = Dates::date($validated['to'], $tz);

            return ['from' => $from, 'to' => $to, 'label' => $this->day($from).' sampai '.$this->day($to)];
        }

        $month = Dates::month($validated['month'] ?? null, $tz) ?? $tenant->today()->startOfMonth();

        return ['from' => $month, 'to' => $month->endOfMonth()->startOfDay(), 'label' => $month->locale('id')->translatedFormat('F Y')];
    }

    /**
     * Tanggal acuan (okupansi): bawaan hari ini menurut zona waktu tenant.
     */
    public function asOf(Tenant $tenant): CarbonImmutable
    {
        return Dates::date($this->validated('date') ?? null, $tenant->timezone) ?? $tenant->today();
    }

    private function day(CarbonImmutable $date): string
    {
        return $date->locale('id')->translatedFormat('j F Y');
    }
}
