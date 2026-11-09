<?php

namespace App\Models;

use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Aturan penagihan, satu baris per tenant. Baris dibuat dengan nilai bawaan saat pertama dibaca. */
class BillingSetting extends Model implements TenantExportable
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'invoice_prefix', 'issue_day', 'due_day', 'prorate_first_month', 'prorate_last_month',
        'late_fee_type', 'late_fee_value', 'reminder_offsets', 'send_window_start', 'send_window_end',
    ];

    /** Nilai bawaan juga dipakai sebelum baris disimpan. */
    protected $attributes = [
        'invoice_prefix' => 'INV',
        'issue_day' => 1,
        'due_day' => 5,
        'prorate_first_month' => true,
        'prorate_last_month' => true,
        'reminder_offsets' => '[-3,0,3]',
        'send_window_start' => '08:00:00',
        'send_window_end' => '20:00:00',
    ];

    protected function casts(): array
    {
        return [
            'issue_day' => 'integer',
            'due_day' => 'integer',
            'prorate_first_month' => 'boolean',
            'prorate_last_month' => 'boolean',
            'late_fee_value' => 'integer',
            'reminder_offsets' => 'array',
        ];
    }

    /** Pengaturan tenant aktif; dibuat dengan nilai bawaan bila belum ada. */
    public static function current(): self
    {
        $defaults = config('kostra.billing');

        return self::query()->firstOrCreate([], [
            'invoice_prefix' => $defaults['invoice_prefix'],
            'issue_day' => $defaults['issue_day'],
            'due_day' => $defaults['due_day'],
            'reminder_offsets' => $defaults['reminder_offsets'],
            'send_window_start' => $defaults['send_window_start'],
            'send_window_end' => $defaults['send_window_end'],
        ]);
    }

    public static function exportName(): string
    {
        return 'pengaturan_penagihan';
    }

    public static function exportColumns(): array
    {
        return [
            'id', 'invoice_prefix', 'issue_day', 'due_day', 'prorate_first_month', 'prorate_last_month',
            'reminder_offsets', 'send_window_start', 'send_window_end', 'created_at',
        ];
    }
}
