<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;

class BillingSetting extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'invoice_prefix',
        'issue_day',
        'due_day',
        'prorate_first_month',
        'prorate_last_month',
        'late_fee_type',
        'late_fee_value',
        'reminder_offsets',
        'send_window_start',
        'send_window_end',
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

    /**
     * Nilai bawaan untuk tenant baru (config/kostra.php).
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $billing = config('kostra.billing');

        return [
            'invoice_prefix' => $billing['invoice_prefix'],
            'issue_day' => $billing['issue_day'],
            'due_day' => $billing['due_day'],
            'prorate_first_month' => false,
            'prorate_last_month' => false,
            'reminder_offsets' => $billing['reminder_offsets'],
            'send_window_start' => $billing['send_window_start'],
            'send_window_end' => $billing['send_window_end'],
        ];
    }

    /**
     * Pengaturan tenant aktif; dibuat dengan nilai bawaan bila belum ada (tenant lama).
     */
    public static function current(): self
    {
        $tenantId = app(TenantContext::class)->id()
            ?? throw new LogicException('Pengaturan penagihan membutuhkan konteks tenant.');

        $existing = static::query()->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return static::create([...static::defaults(), 'tenant_id' => $tenantId]);
        } catch (UniqueConstraintViolationException) {
            // Proses lain membuatnya lebih dulu.
            return static::query()->firstOrFail();
        }
    }
}
