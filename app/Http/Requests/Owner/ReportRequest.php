<?php

namespace App\Http\Requests\Owner;

use App\Models\Property;
use App\Services\Billing\BillingClock;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Parameter halaman dan ekspor laporan. Hanya pengguna dengan izin keuangan. */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view-finance');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'type' => ['nullable', Rule::in(array_keys(ReportBuilder::TYPES))],
            'period' => ['nullable', 'date_format:Y-m'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('tenant_id', $tenantId)],
            // Properti yang sudah diarsipkan tetap boleh dipilih agar laporan historisnya bisa dibuka.
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.in' => 'Jenis laporan tidak dikenal.',
            'period.date_format' => 'Periode tidak valid.',
            'date.date_format' => 'Tanggal tidak valid.',
            'property_id.exists' => 'Properti tidak ditemukan.',
        ];
    }

    public function reportType(): string
    {
        return $this->validated('type') ?? 'rekap';
    }

    public function filters(): ReportFilters
    {
        $today = BillingClock::today();
        $propertyId = $this->filled('property_id') ? $this->integer('property_id') : null;

        return new ReportFilters(
            ReportPeriod::month($this->validated('period') ?? $today->format('Y-m')),
            $this->filled('date') ? CarbonImmutable::createFromFormat('!Y-m-d', $this->validated('date'), 'UTC') : $today,
            $propertyId,
            $propertyId === null ? 'Semua properti' : (Property::withTrashed()->findOrFail($propertyId)->name),
        );
    }
}
